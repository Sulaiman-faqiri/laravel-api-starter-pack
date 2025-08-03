# 🛠️ Laravel Command: `make:MRC`

> Artisan command to auto-generate **Model**, **Form Request**, **Controller**, **Seeder**, and **API routes** from an existing **migration file**.
>
> 💡 Designed to speed up scaffolding CRUD-based resources with clean Laravel conventions and smart logic parsing migration structure.

---

## 📌 Command Signature

```bash
php artisan make:MRC {table}
```

| Parameter | Description                                                        |
| --------- | ------------------------------------------------------------------ |
| `table`   | The name of the database table (as defined in your migration file) |

---

## 🚀 What This Command Does

Given a migration file, this command will:

1. **Parse the migration** to extract:

   * Table structure and column types
   * Modifiers like `nullable`, `default`, `unsigned`, etc.
   * Foreign key relationships

2. **Automatically generate** the following files:

   * ✅ **Model** (with `HasBaseBuilder` and `belongsTo` relationships)
   * ✅ **Form Request** (`App\Http\Requests\ModelNameRequest`)
   * ✅ **Controller** (`App\Http\Controllers\ModelNameController`)
   * ✅ **Seeder** with realistic sample data
   * ✅ **Reverse relationships** in related models (e.g., `hasMany`)
   * ✅ **API resource routes** in `routes/api.php`
   * ✅ **Seeder registration** in `DatabaseSeeder.php`

---

## 🧱 Folder Structure

Here’s where everything goes:

| File Type  | Location                                       |
| ---------- | ---------------------------------------------- |
| Model      | `app/Models/ModelName.php`                     |
| Request    | `app/Http/Requests/ModelNameRequest.php`       |
| Controller | `app/Http/Controllers/ModelNameController.php` |
| Seeder     | `database/seeders/ModelNameSeeder.php`         |
| Routes     | Appended to `routes/api.php`                   |

---

## 📂 Example Usage

### Migration file: `create_products_table.php`

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('category_id')->constrained();
    $table->decimal('price', 8, 2)->nullable();
    $table->timestamps();
});
```

### Command:

```bash
php artisan make:MRC products
```

### Result:

* ✅ `Product.php` with `belongsTo(Category::class)`
* ✅ `ProductRequest.php` with validation rules
* ✅ `ProductController.php` with full CRUD
* ✅ `ProductSeeder.php` with 5 sample records
* ✅ Route:

  ```php
  Route::apiResource('products', ProductController::class);
  ```

---

## 📋 Validation Rules

Smart rule generation based on:

* Column type (`string`, `integer`, `boolean`, etc.)
* Naming conventions (`*_email`, `*_phone`, `*_price`, `*_date`, etc.)
* Nullable and default values
* Foreign keys → `exists:table,id`

---

## 🔗 Relationship Handling

* Auto-generates `belongsTo()` for foreign keys in the current model
* Inserts reverse `hasMany()` relationships in related models (if they exist)

---

## 📦 Seeder Details

* Seeds 5 sample rows with realistic dummy data
* Handles:

  * Email, phone, prices, dates, and foreign IDs
  * Random/fixed values for demo purposes
* Auto-registers the seeder in `DatabaseSeeder.php`

---

## 🔄 Route Integration

* Adds API resource route for the model:

  ```php
  Route::apiResource('products', ProductController::class);
  ```
* Also includes (commented) individual route declarations with method names, useful for assigning permissions.

---

## 🧠 Developer Notes

* **Assumes migration exists** with `Schema::create()` call.
* **Does not modify existing models** unless reverse relationships need to be added.
* **Non-destructive**: Existing code remains untouched unless explicitly modified.
* Requires `HasBaseBuilder` trait in model for base querying/filtering.

---

## 🧪 Tip: Test It Safely

Run the command on a test table first to see how it works:

```bash
php artisan make:MRC test_table
```

Inspect the output files and route before applying to production models.

---

## ✅ Requirements

* Laravel 9+
* Migration file must exist under `database/migrations`
* Model should use standard Laravel conventions (plural snake\_case table names, singular StudlyCase model names)

---

## 📁 TODO (Future Improvements)

* [ ] Support `enum` columns with value validation
* [ ] Detect soft deletes (`deleted_at`)
* [ ] Optional route middleware/permission mapping
* [ ] Auto-generate tests

---

## 👤 Author

Sulaiman Faqiri
if you found this command useful star the repository thanks.
