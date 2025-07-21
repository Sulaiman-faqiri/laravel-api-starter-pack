<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeModelRequestRouteControllerSeeder extends Command

{
    // New version (MakeDatabaseModelRequest)
    protected $signature = 'make:MRC {table}';
    protected $description = 'Create model and request and controller from existing migration';
    public function handle()
    {
        $tableName = $this->argument('table');
        $migrationPath = $this->findMigrationFile( $tableName);

        if (!$migrationPath) $this->error('Migration file not found!');

        $content = file_get_contents($migrationPath);

        $columns = [];

        preg_match('/create\([\'"](.+?)[\'"]/', $content, $matches);
        $tableName = $matches[1] ?? null;

        if (!$tableName)  $this->error('Table name not found in migration!');

        //  migration column parsing
        preg_match_all('/\$table->(\w+)\s*\(\s*(([\'"])(.+?)\3|([^)\s,]+))(?:,\s*((?:\d+(?:,\s*\d+)*|[\'"][^\'"]*[\'"]|[^)]+)))?\s*\)((?:\s*->\s*(?:nullable|unsigned|default|index|unique|comment|\w+)\s*\(?\s*(?:[\'"][^\'"]*[\'"]|[^)]*)?\s*\)?\s*)*)/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $rawType = $match[1];
            $column = $match[4] ?? $match[5];
            $params = $match[6] ?? null;
            $modifiers = $match[7] ?? '';

            $isUnsignedType = str_starts_with($rawType, 'unsigned');
            $baseType = $isUnsignedType ? lcfirst(str_replace('unsigned', '', $rawType)) : $rawType;

            // Directly extract default value from modifiers
            $default = null;
            if (preg_match('/->default\(\s*([\'"]?)(.*?)\1\s*\)/', $modifiers, $defaultMatch)) {
                $default = $defaultMatch[2];
            }
            $columnDef = [
                'type' => $baseType,
                'unsigned' => $isUnsignedType || str_contains($modifiers, 'unsigned()'),
                'nullable' => str_contains($modifiers, 'nullable()'),
                'default' => $default,
                'length' => $params
            ];
            $columns[$column] = $columnDef;
        }
        preg_match_all(
            '/\$table->(?:foreignId|foreign)\(\s*([\'"])(\w+)\1\s*\)'
                . '(?:'
                . '(?:->references\(\s*([\'"])(\w+)\3\s*\))?'
                . '(?:->on\(\s*([\'"])(\w+)\5\s*\))?'
                . '(?:->constrained\(\s*([\'"])(\w+)\7\s*\))?'
                . '(?:->on(Update|Delete)\(\s*[\'"](.*?)[\'"]\s*\))?'
                . '(?:->nullable\(\))?'
                . ')*/',
            $content,
            $foreignKeys,
            PREG_SET_ORDER
        );

        foreach ($foreignKeys as $fk) {
            $column = $fk[2];
            $table = $fk[8] ?? $fk[6] ?? $fk[5] ?? Str::plural(Str::replaceLast('_id', '', $column));
            $columns[$column] = [
                'type' => 'foreignId',
                'table' => $table,
                'nullable' => str_contains($fk[0], '->nullable()'),
            ];
        }

        $modelName = Str::studly(Str::singular($tableName));


        if (!file_exists(app_path("Models"))) {
            mkdir(app_path("Models"), 0777, true);
        }

        // Generate and save Model with capitalized database name
        file_put_contents(
            app_path("Models/{$modelName}.php"),
            $this->generateModelContent( $modelName)
        );

        // Create and save Request (keeping original case)
        if (!file_exists(app_path("Http/Requests"))) {
            mkdir(app_path("Http/Requests"), 0777, true);
        }

        file_put_contents(
            app_path("Http/Requests/{$modelName}Request.php"),
            $this->generateRequestContent( $modelName, $columns)
        );

        // Create and save Controller (keeping original case)
        if (!file_exists(app_path("Http/Controllers"))) {
            mkdir(app_path("Http/Controllers"), 0777, true);
        }

        file_put_contents(
            app_path("Http/Controllers/{$modelName}Controller.php"),
            $this->generateControllerContent($modelName)
        );

        // Create and save Seeder
        if (!file_exists(database_path("seeders"))) {
            mkdir(database_path("seeders"), 0777, true);
        }

        file_put_contents(
            database_path("seeders/{$modelName}Seeder.php"),
            $this->generateSeederContent($modelName, $columns)
        );


        $this->updateMasterSeeder($modelName);

        $this->addRoutes( $modelName);


        $this->info('Model, Request, Controller, Seeder, Routes created successfully!');
    }


    private function generateRule($column, $columnInfo)
    {
        $rules = [];

        // Basic properties
        $isNullable = $columnInfo['nullable'] ?? false;
        $defaultValue = $columnInfo['default'] ?? null;
        $type = $columnInfo['type'];
        $unsigned = $columnInfo['unsigned'] ?? false;
        $table = $columnInfo['table'] ?? null;
        $unique = $columnInfo['unique'] ?? false;
        $enum = $columnInfo['enum'] ?? null;
        $database = $columnInfo['database'] ?? null;
        // Required or nullable
        $rules[] = match (true) {
            $isNullable || $defaultValue !== null => 'nullable',
            default => 'required'
        };
        // Type-based rules
        $typeRules = match (true) {
            $type === 'string' => ['string', 'max:' . (isset($columnInfo['length']) && is_numeric($columnInfo['length']) ? $columnInfo['length'] : 255)],
            $type === 'text' => ['string', 'max:65535'],
            $type === 'tinyInteger' => ['integer', 'min:' . ($unsigned ? '0' : '-128'), 'max:' . ($unsigned ? '255' : '127')],
            $type === 'smallInteger' => ['integer', 'min:' . ($unsigned ? '0' : '-32768'), 'max:' . ($unsigned ? '65535' : '32767')],
            $type === 'integer' => ['integer', 'min:' . ($unsigned ? '0' : '-2147483648'), 'max:' . ($unsigned ? '4294967295' : '2147483647')],
            $type === 'bigInteger' => ['integer', 'min:' . ($unsigned ? '0' : '-9223372036854775808'), 'max:' . ($unsigned ? '18446744073709551615' : '9223372036854775807')],
            in_array($type, ['decimal', 'double', 'float']) => ['numeric', 'min:0'],
            $type === 'boolean' => ['boolean'],
            in_array($type, ['date', 'datetime', 'timestamp']) => ['string', 'max:20'],
            $type === 'time' => ['string', 'max:8'],
            $type === 'year' => ['string', 'max:4'],
            $type === 'month' => ['integer', 'min:1', 'max:12'],
            $type === 'json' => ['json'],
            $type === 'array' => ['array'],
            $type === 'foreignId' => ["exists:{$database}.{$table},id"],
            $type === 'unsignedBigInteger' => ['integer', 'min:0', 'max:18446744073709551615'],
            $type === 'uuid' => ['uuid'],
            default => []
        };

        $rules = [...$rules, ...$typeRules];

        // Column name pattern rules
        $rules = [...$rules, ...match (true) {
            str_ends_with($column, '_date') => ['string', 'max:20'],
            str_ends_with($column, '_email') => ['email:rfc,dns', 'max:255'],
            str_ends_with($column, '_phone') => ['string', 'regex:/^\+?[0-9]{7,15}$/'],
            str_ends_with($column, '_price'), str_ends_with($column, '_amount') => ['numeric', 'min:0'],
            str_ends_with($column, '_percentage') => ['numeric', 'min:0', 'max:100'],
            str_ends_with($column, '_image'), str_ends_with($column, '_photo') => ['image', 'max:2048'],
            str_ends_with($column, '_file') => ['file', 'max:10240'],
            str_ends_with($column, '_password') => ['string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
            default => []
        }];

        // Add unique rule if specified
        if ($unique) {
            $rules[] = "unique:$table,$column";
        }

        // Add enum validation if specified
        if ($enum && is_array($enum)) {
            $rules[] = 'in:' . implode(',', $enum);
        }

        // Special fields
        if ($column === 'deleted_at') {
            $rules = ['nullable', 'string', 'max:20'];
        }

        if (in_array($column, ['created_at', 'updated_at'])) {
            $rules = ['nullable', 'string', 'max:20'];
        }

        return implode('|', array_unique($rules));
    }


    private function findMigrationFile( $tableName)
    {
        $migrationDir = database_path("migrations");

        $migrationFiles = glob($migrationDir . '/*.php');

        if (empty($migrationFiles)) $this->error("No migration files found in: {$migrationDir}");

        foreach ($migrationFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match("/Schema::create\(['\"]{$tableName}['\"]/", $content)) {
                return $file;
            }
        }

        return null;
    }

    private function generateModelContent($modelName)
    {
        $namespace = "App\\Models";
        return <<<PHP
<?php

namespace {$namespace};
use App\Traits\HasBaseBuilder;
use Illuminate\Database\Eloquent\Model;

class {$modelName} extends Model
{
    use HasBaseBuilder;

    protected \$guarded = [];
}
PHP;
    }

    private function generateRequestContent($modelName, $columns)
    {
        $rules = [];
        foreach ($columns as $column => $columnInfo) {
            // Add database to columnInfo for foreignId validation
            $columnInfo['database'] = '';
            $rule = $this->generateRule($column, $columnInfo);
            $rules[] = "            '$column' => '$rule'";
        }

        $rulesStr = implode(",\n", $rules);
        $namespace = "App\\Http\\Requests";
        return <<<PHP
<?php

namespace {$namespace};
use App\Http\Requests\BaseFormRequest;
class {$modelName}Request extends BaseFormRequest
{
    public function baseRules(): array
    {
        return [
{$rulesStr}
        ];
    }
}
PHP;
    }
    private function generateControllerContent($modelName)
    {
        $namespace = "App\\Http\\Controllers";
        $modelNamespace = "App\\Models\\$modelName";
        $requestNamespace = "App\\Http\\Requests\\{$modelName}Request";

        // Get table name from the model
        $tableName = (new $modelNamespace)->getTable();

        return <<<PHP
<?php

namespace {$namespace};

use App\Http\Controllers\Controller;
use {$modelNamespace};
use {$requestNamespace};
use Illuminate\Http\Request;

class {$modelName}Controller extends Controller
{

    protected \$model;

    public function __construct({$modelName} \$model)
    {
        \$this->model = \$model;
    }

    public function index(Request \$request)
    {
        \$query = \$this->model->query()->select('{$tableName}.*');

    }

    public function store({$modelName}Request \$request)
    {
        return \$this->model->create(\$request->validated());
    }

    public function show(\$id)
    {
        return \$this->model->findOrFail(\$id);
    }

    public function update({$modelName}Request \$request, \$id)
    {
        \$model = \$this->model->findOrFail(\$id);
        \$model->update(\$request->validated());
        return \$model;

    }

    public function destroy(\$id)
    {
        \$model = \$this->model->findOrFail(\$id);
        \$model->delete();
        return \$id;

    }
}
PHP;
    }


    private function generateSeederContent( $modelName, $columns)
    {
        $namespace = "Database\\Seeders";
        $modelNamespace = "App\\Models\\{$modelName}";

        // Get table name from the model class
        $modelClass = $modelNamespace;
        $tableName = (new $modelClass)->getTable();

        // Generate sample data based on column types
        $sampleData = [];
        for ($i = 1; $i <= 5; $i++) {
            $row = [];
            foreach ($columns as $column => $columnInfo) {
                $row[$column] = $this->generateSampleValue($column, $columnInfo, $i);
            }
            $sampleData[] = $row;
        }

        $sampleDataStr = $this->formatSampleData($sampleData);

        return <<<PHP
    <?php

    namespace {$namespace};

    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\DB;

    class {$modelName}Seeder extends Seeder
    {
        public function run()
        {
            DB::table('{$tableName}')->insert({$sampleDataStr});
        }
    }
    PHP;
    }

    private function generateSampleValue($column, $columnInfo, $index)
    {
        $type = $columnInfo['type'];
        $isNullable = $columnInfo['nullable'] ?? false;
        $default = $columnInfo['default'] ?? null;

        // If column is nullable and we're generating a null value
        if ($isNullable && $index % 5 === 0) {
            return 'null';
        }

        // If there's a default value, use it
        if ($default !== null) {
            return is_string($default) ? "'$default'" : $default;
        }

        // Generate sample data based on column type and name
        return match (true) {
            // Handle special column names
            str_ends_with($column, '_email') => "'sample{$index}@example.com'",
            str_ends_with($column, '_phone') => "'+93" . rand(700000000, 799999999) . "'",
            str_ends_with($column, '_date') => "'" . date('Y-m-d') . "'",
            str_ends_with($column, '_price') || str_ends_with($column, '_amount') => rand(100, 10000),
            str_ends_with($column, '_percentage') => rand(0, 100),
            str_ends_with($column, '_url') => "'https://example.com/sample{$index}'",

            // Handle column types
            $type === 'string' => "'Sample " . ucfirst($column) . " {$index}'",
            $type === 'text' => "'This is a sample text for {$column} {$index}'",
            $type === 'integer' || $type === 'bigInteger' => $index * 100,
            $type === 'boolean' => ($index % 2 === 0) ? 'true' : 'false',
            $type === 'date' => "'" . date('Y-m-d') . "'",
            $type === 'datetime' || $type === 'timestamp' => "'" . date('Y-m-d H:i:s') . "'",
            $type === 'time' => "'" . date('H:i:s') . "'",
            $type === 'json' => "'{\"key\": \"value{$index}\"}'",
            $type === 'foreignId' => $index,

            default => "'Sample {$index}'"
        };
    }

    private function formatSampleData($sampleData)
    {
        $formatted = "[\n";
        foreach ($sampleData as $row) {
            $formatted .= "            [\n";
            foreach ($row as $key => $value) {
                $formatted .= "                '{$key}' => {$value},\n";
            }
            $formatted .= "            ],\n";
        }
        $formatted .= "        ]";
        return $formatted;
    }

private function updateMasterSeeder($modelName)
{
    $seederPath = database_path('seeders/DatabaseSeeder.php');

    if (!file_exists($seederPath)) {
        $this->error('DatabaseSeeder.php not found!');
        return;
    }

    $content = file_get_contents($seederPath);

    // Check if the seeder is already included
    if (str_contains($content, "{$modelName}Seeder::class")) {
        return; // Already added
    }

    // Add `use` statement if missing
    $useStatement = "use Database\\Seeders\\{$modelName}Seeder;\n";
    if (!str_contains($content, $useStatement)) {
        // Insert after the opening <?php and any existing use statements
        if (preg_match('/<\?php\s*(?:\n|.)*?namespace [^;]+;(\s*)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1] + strlen($matches[0][0]);
            $content = substr_replace($content, $useStatement, $insertPos, 0);
        } else {
            // Fallback: insert just after <?php
            $content = preg_replace('/<\?php\s*/', "<?php\n{$useStatement}", $content, 1);
        }
    }

    // Try to find an existing $this->call([...]);
    if (preg_match('/\$this->call\(\[([\s\S]*?)\]\);/', $content, $matches)) {
        $existing = $matches[1];
        // Check if already present (shouldn't be, but double check)
        if (strpos($existing, "{$modelName}Seeder::class") === false) {
            // Add the new seeder
            $updated = rtrim($existing) . "\n            {$modelName}Seeder::class,\n        ";
            $content = str_replace($matches[0], "\$this->call([\n        {$updated}\n    ]);", $content);
        }
    } else {
        // No $this->call([...]); found, so append it to the end of the run() method
        if (preg_match('/public function run\(\)\s*:\s*void\s*\{([\s\S]*?)\n\s*\}/', $content, $matches)) {
            $runBody = rtrim($matches[1]);
            $newRunBody = $runBody . "\n\n        \$this->call([\n            {$modelName}Seeder::class,\n        ]);";
            $content = str_replace($matches[0], "public function run(): void\n    {{$newRunBody}\n    }", $content);
        } else {
            // Fallback: replace the run() method entirely
            $content = preg_replace(
                '/public function run\(\)[^{]*\{[^}]*\}/',
                "public function run(): void\n    {\n        \$this->call([\n            {$modelName}Seeder::class,\n        ]);\n    }",
                $content
            );
        }
    }

    file_put_contents($seederPath, $content);
}





    private function addRoutes($modelName)
    {
        $routeFile = base_path("routes/api.php");
        $controllerName = "App\\Http\\Controllers\\{$modelName}Controller";
        $modelNamespace = "App\\Models\\{$modelName}";
        // Get table name from the model
        $tableName = (new $modelNamespace)->getTable();



        // Read the route file content
        $content = file_get_contents($routeFile);

        // Check if the routes already exist
        if (strpos($content, $tableName) !== false) {
            return; // Routes already exist
        }

        // Add the use statement at the top of the file
        $useStatement = "use {$controllerName};\n";
        // Check if controller is already used
        if (!str_contains($content, $useStatement)) {
            // Find position to insert after all use statements
            $pattern = '/(<\?php\s*(?:\n|.)*?)(\nuse\s+[^\n;]+;)*(\n)/i';
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr_replace($content, $useStatement, $insertPos, 0);
            } else {
                // Fallback: insert just after <?php
                $content = preg_replace('/<\?php\s*/', "<?php\n{$useStatement}", $content, 1);
            }
        }

        // Generate the API resource route
        $apiResourceRoute = <<<PHP

        // {$modelName} Routes
        Route::apiResource('{$tableName}', {$modelName}Controller::class);

        /*
        // Individual {$modelName} Routes with permissions
        Route::get('{$tableName}', [{$modelName}Controller::class, 'index']);
        Route::post('{$tableName}', [{$modelName}Controller::class, 'store']);
        Route::get('{$tableName}/{id}', [{$modelName}Controller::class, 'show']);
        Route::patch('{$tableName}/{id}', [{$modelName}Controller::class, 'update']);
        Route::delete('{$tableName}/{id}', [{$modelName}Controller::class, 'destroy']);
        */
        PHP;

        // Add the new routes to the file
        file_put_contents($routeFile, $content . $apiResourceRoute);
    }
}
