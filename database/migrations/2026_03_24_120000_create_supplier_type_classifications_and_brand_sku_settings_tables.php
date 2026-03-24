<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_type_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 64);
            $table->string('type_raw', 255);
            $table->string('route_bucket', 32);
            $table->timestamps();

            $table->unique(['supplier_code', 'type_raw']);
            $table->index('supplier_code');
        });

        Schema::create('brand_sku_settings', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 64)->unique();
            /** file_then_formula | file_only | always_formula */
            $table->string('mode', 32)->default('file_then_formula');
            /** @var array<int,string>|null order of template columns for size token in formula */
            $table->json('size_column_priority')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_sku_settings');
        Schema::dropIfExists('supplier_type_classifications');
    }
};
