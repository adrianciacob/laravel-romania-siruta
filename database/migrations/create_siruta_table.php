<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            config('siruta.table', 'siruta'),
            function (Blueprint $table) {

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

                $table->index('normalized_name');

                $table->index(
                    ['normalized_name', 'county_code'],
                    'siruta_name_county_idx'
                );

                $table->index(
                    ['parent_id', 'level'],
                    'siruta_parent_level_idx'
                );

                $table->index(
                    ['county_code', 'level'],
                    'siruta_county_level_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            config('siruta.table', 'siruta')
        );
    }
};
