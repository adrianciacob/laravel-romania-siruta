<<<<<<< HEAD
# Laravel Romania SIRUTA

![Latest Version on Packagist](https://img.shields.io/packagist/v/adrianciacob/laravel-romania-siruta.svg) ![License](https://img.shields.io/packagist/l/adrianciacob/laravel-romania-siruta.svg)

A Laravel package for working with Romanian administrative-territorial units using the official [SIRUTA](https://ro.wikipedia.org/wiki/SIRUTA) classification.

It provides a local database of Romanian localities, counties, municipalities, towns and villages, with support for searching and resolving locality names to their numeric SIRUTA identifiers.

Data is imported from the official [INS (Institutul Național de Statistică)](https://insse.ro) dataset.

No external API is required. All lookups are performed against your application's database.

## Features

- Import Romanian SIRUTA data from the official INS dataset.
- Store data locally in your Laravel database.
- Resolve locality names to numeric SIRUTA IDs.
- Search localities using normalized names.
- Support Romanian diacritics and accent-insensitive searches.
- Access county, parent locality and administrative information.
- Artisan command for importing and updating data.
- Laravel service container integration.
- No external API dependency.
- Reusable across multiple Laravel projects.

## Requirements

- PHP 8.3+
- Laravel 13+
- MySQL, MariaDB, PostgreSQL or SQLite.
- Internet connection for importing the INS dataset.

## Installation

Install through Composer:

```bash
composer require adrianciacob/laravel-romania-siruta
```

Laravel package auto-discovery registers the service provider automatically.

If auto-discovery is disabled, register:

```php
adrianciacob\Siruta\SirutaServiceProvider::class,
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=siruta-config
```

The configuration file is:

`config/siruta.php`

It controls the data source, database table and import settings.

The default configuration uses the official INS dataset source configured by the package.

You can customize the source URL if INS changes its download location.

## Database

Publish the package migrations:

```bash
php artisan vendor:publish --tag=siruta-migrations
```

Run migrations:

```bash
php artisan migrate
```

The package creates the `siruta` table.

The table contains the administrative information required for resolving and searching localities.

Main columns:

| Column          | Description                                   |
| --------------- | --------------------------------------------- |
| siruta          | Numeric SIRUTA identifier                     |
| name            | Official locality or administrative unit name |
| normalized_name | Name normalized for searching                 |
| county_code     | County code                                   |
| parent_id       | Parent SIRUTA identifier                      |
| postal_code     | Postal code                                   |
| type            | Administrative unit type                      |
| level           | Administrative hierarchy level                |
| region          | Development region                            |
| nuts            | NUTS identifier                               |
| lau             | LAU identifier                                |
| fsj             | Additional administrative classification      |
| environment     | Environment classification                    |

**Important:** When upgrading an existing installation, make sure your database has all columns expected by the current importer, including `environment`.

Run any new package migrations before importing data.

## Importing SIRUTA data

Import the dataset:

```bash
php artisan siruta:import
```

The command:

1. Downloads the configured INS dataset.
2. Reads and validates the CSV structure.
3. Normalizes locality names.
4. Maps administrative information.
5. Imports records into the `siruta` table.
6. Updates existing records where applicable.

The importer should preserve the numeric SIRUTA identifiers.

You can run the command again when updated INS data becomes available.

### Troubleshooting imports

If you encounter:

```text
Missing CSV columns
```

The downloaded CSV format may have changed.

Check the actual CSV headers and update the importer's column mapping.

If you encounter:

```text
SQLSTATE[42S22]: Column not found
```

Your database schema does not match the current importer.

Run:

```bash
php artisan migrate
```

If the missing column is not covered by an existing migration, update the package migration or create an additional migration.

For example, to add the `environment` column to an existing installation:

```php
Schema::table('siruta', function (Blueprint $table) {
    $table->unsignedTinyInteger('environment')
        ->default(0);
});
```

## Usage

### Resolve a locality name

Inject the package service into your application:

```php
use adrianciacob\Siruta\Services\SirutaService;

class AddressController
{
    public function __construct(
        protected SirutaService $siruta
    ) {}

    public function lookup()
    {
        $locality = $this->siruta->findByName('Cluj-Napoca');

        return $locality;
    }
}
```

### Resolve a locality to its numeric SIRUTA ID

```php
$locality = app(SirutaService::class)
    ->findByName('Cluj-Napoca');

$sirutaId = $locality?->siruta;
```

### Search localities

```php
$results = app(SirutaService::class)
    ->search('Cluj');
```

### Handle missing localities

```php
$locality = app(SirutaService::class)
    ->findByName('Bucharest');

if (! $locality) {
    return response()->json([
        'message' => 'Locality not found',
    ], 404);
}
```

**Note:** The examples above assume the service exposes `findByName()` and `search()`. Check the installed service implementation for the exact available method signatures.

## Name normalization

Romanian locality names can contain diacritics.

For example:

- București
- Bucuresti
- Bacău
- Bacau
- Timișoara
- Timisoara

The package stores normalized names to facilitate accent-insensitive searches.

Normalization is useful when importing data from forms, external systems or addresses entered without Romanian diacritics.

## Data source

The package uses SIRUTA data published by:

**Institutul Național de Statistică (INS)**

SIRUTA stands for _Sistemul Informatic al Registrului Unităților Teritorial-Administrative_.

The imported data is maintained locally in your application's database.

The package does not depend on an external API for resolving locality names.

Always check the official INS publication for the latest dataset and applicable data-use conditions.

## Updating the dataset

When a new INS dataset becomes available:

```bash
php artisan siruta:import
```

The importer should update existing records using their SIRUTA identifiers rather than creating duplicate entries.

Keep a database backup before importing a substantially different dataset.

## Package development

Clone the repository:

```bash
git clone https://github.com/adrianciacob/laravel-romania-siruta.git

cd laravel-romania-siruta
```

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

## License

This package is licensed under the **Apache License 2.0**.

You are free to use, modify, distribute and integrate this package into personal, commercial and open-source projects, subject to the terms of the license.

**Attribution:** Any reuse, redistribution or adaptation must retain the original copyright and attribution notices, credit the original author, Adrian C. IACOB, and comply with the Apache License 2.0.

See LICENSE for the complete license terms.

## Author

**Adrian C. IACOB**

GitHub: @adrianciacob

Packagist: adrianciacob/laravel-romania-siruta

---

Made with care for the Laravel and Romanian developer communities.
=======
# laravel-romania-siruta
Romanian [SIRUTA](https://insse.ro/cms/ro/siruta) database and locality resolver for Laravel.
>>>>>>> 4d031571e58144c6ec4e722c910df07001f531a4
