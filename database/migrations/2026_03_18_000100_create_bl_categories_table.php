<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bl_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('id'); // BaseLinker category_id
            $table->unsignedBigInteger('inventory_id');
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->primary(['inventory_id', 'id']);
            $table->index(['inventory_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_categories');
    }
};

