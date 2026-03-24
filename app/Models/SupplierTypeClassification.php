<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $supplier_code
 * @property string $type_raw
 * @property string $route_bucket footwear|hat|socks|generic
 */
final class SupplierTypeClassification extends Model
{
    protected $fillable = [
        'supplier_code',
        'type_raw',
        'route_bucket',
    ];
}
