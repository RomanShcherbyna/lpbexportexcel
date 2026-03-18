<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bl_products', function (Blueprint $table) {
            $table->unsignedBigInteger('id'); // BaseLinker product_id
            $table->unsignedBigInteger('inventory_id');
            $table->unsignedBigInteger('parent_id')->default(0);

            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->string('ean')->nullable()->index();

            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock')->nullable();

            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('image')->nullable();

            $table->json('prices_json')->nullable();
            $table->json('stock_json')->nullable();

            $table->timestamps();

            $table->primary(['inventory_id', 'id']);
            $table->index(['inventory_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_products');
    }
};

