<?php

use adrianciacob\Siruta\Services\SirutaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {

    config(['siruta.table' => 'siruta']);

    Schema::create('siruta', function (Blueprint $table) {
        $table->string('siruta', 10)->primary();
        $table->string('name');
        $table->string('normalized_name');
        $table->string('postal_code', 10)->nullable();
        $table->string('county_code', 10);
        $table->string('parent_id', 10);
        $table->unsignedTinyInteger('type');
        $table->unsignedTinyInteger('level');
        $table->unsignedTinyInteger('environment');
        $table->string('region', 10)->nullable();
        $table->string('fsj', 20)->nullable();
        $table->string('nuts', 20)->nullable();
        $table->string('lau', 30)->nullable();
        $table->timestamps();
    });

    DB::table('siruta')->insert([
        [
            'siruta' => '1000',
            'name' => 'Cluj',
            'normalized_name' => 'cluj',
            'county_code' => '12',
            'parent_id' => '0',
            'type' => 0,
            'level' => 1,
            'environment' => 0,
        ],
        [
            'siruta' => '1001',
            'name' => 'Cluj-Napoca',
            'normalized_name' => 'cluj napoca',
            'county_code' => '12',
            'parent_id' => '1000',
            'type' => 1,
            'level' => 2,
            'environment' => 1,
        ],
        [
            'siruta' => '1002',
            'name' => 'Florești',
            'normalized_name' => 'floresti',
            'county_code' => '12',
            'parent_id' => '1001',
            'type' => 3,
            'level' => 3,
            'environment' => 0,
        ],
    ]);
});

test('it resolves county names', function () {

    $service = app(SirutaService::class);

    expect($service->resolveCounty('Cluj'))
        ->toBe('1000');
});

test('it resolves county names with prefixes', function () {

    $service = app(SirutaService::class);

    expect($service->resolveCounty('Județul Cluj'))
        ->toBe('1000');
});

test('it resolves locality names', function () {

    $service = app(SirutaService::class);

    expect(
        $service->resolve('Cluj-Napoca', 'Cluj')
    )->toBe('1001');
});

test('it ignores Romanian diacritics', function () {

    $service = app(SirutaService::class);

    expect(
        $service->resolve('Floresti', 'Cluj')
    )->toBe('1002');
});

test('it returns null for missing localities', function () {

    $service = app(SirutaService::class);

    expect(
        $service->resolve('Unknown locality', 'Cluj')
    )->toBeNull();
});

test('it retrieves locality hierarchy', function () {

    $service = app(SirutaService::class);

    $hierarchy = $service->getHierarchy('1002');

    expect($hierarchy)->toHaveCount(3)
        ->and($hierarchy[0]->name)->toBe('Cluj')
        ->and($hierarchy[1]->name)->toBe('Cluj-Napoca')
        ->and($hierarchy[2]->name)->toBe('Florești');
});

test('it searches partial locality names', function () {

    $service = app(SirutaService::class);

    $results = $service->search('Flore', 'Cluj');

    expect($results)->toHaveCount(1)
        ->and($results->first()->siruta)->toBe('1002');
});
