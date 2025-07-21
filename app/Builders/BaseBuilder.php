<?php
// app/Builders/BaseBuilder.php
namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class BaseBuilder extends Builder
{
    public function search(?string $term, array $columns): self
    {
        if (!$term || empty($columns)) {
            return $this;
        }

        $this->where(function ($query) use ($term, $columns) {
            foreach ($columns as $column) {
                // Support joined tables: 'table.column'
                if (str_contains($column, '.')) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ?", ['%' . strtolower($term) . '%']);
                } else {
                    $query->orWhere($column, 'like', "%{$term}%");
                }
            }
        });

        return $this;
    }

    /**
     * Apply dynamic filters to the query builder based on provided key-value pairs.
     *
     * ->filter($request->only(['status', 'type']))->get();
     * This method supports:
     * - Simple equality: ['status' => 'active']
     * - Like search: ['name' => ['like' => 'apple']]
     * - Between ranges: ['created_at' => ['from' => '2024-01-01', 'to' => '2024-12-31']]
     * - WhereIn clauses: ['category_id' => [1, 2, 3]]
     * - Custom operators: ['price' => ['operator' => '>=', 'value' => 100]]
     *
     * Skips any filters with null or empty string values.
     *
     * @param array $filters Array of field => value or advanced conditions
     * @return static
     */
    public function filter(array $filters): self
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Date range support: ['created_at' => ['from' => '...', 'to' => '...']]
            if (is_array($value) && isset($value['from']) || isset($value['to'])) {
                $from = $value['from'] ?? null;
                $to = $value['to'] ?? null;

                if ($from && $to) {
                    $this->whereBetween($field, [$from, $to]);
                } elseif ($from) {
                    $this->where($field, '>=', $from);
                } elseif ($to) {
                    $this->where($field, '<=', $to);
                }

                continue;
            }

            // Like operator: ['name' => ['like' => 'keyword']]
            if (is_array($value) && isset($value['like'])) {
                $this->where($field, 'like', "%{$value['like']}%");
                continue;
            }

            // Custom operator: ['price' => ['operator' => '>=', 'value' => 100]]
            if (is_array($value) && isset($value['operator'], $value['value'])) {
                $this->where($field, $value['operator'], $value['value']);
                continue;
            }

            // WhereIn support: ['category_id' => [1, 2, 3]]
            if (is_array($value)) {
                $this->whereIn($field, $value);
                continue;
            }

            // Default equality
            $this->where($field, $value);
        }

        return $this;
    }
}
