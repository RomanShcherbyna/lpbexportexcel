<?php

namespace App\BaseLinkerTest\Models;

use Illuminate\Database\Eloquent\Model;

final class BlProduct extends Model
{
    protected $table = 'bl_products';

    /** @var array<int,string> */
    protected $fillable = [
        'id',
        'inventory_id',
        'parent_id',
        'name',
        'sku',
        'ean',
        'price',
        'stock',
        'category_id',
        'image',
        'prices_json',
        'stock_json',
    ];

    protected $casts = [
        'prices_json' => 'array',
        'stock_json' => 'array',
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public $incrementing = false;
    protected $keyType = 'int';
}

