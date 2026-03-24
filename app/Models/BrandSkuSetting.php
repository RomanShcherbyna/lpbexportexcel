<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $supplier_code
 * @property string $mode file_then_formula|file_only|always_formula
 * @property array<int,string>|null $size_column_priority
 */
final class BrandSkuSetting extends Model
{
    protected $fillable = [
        'supplier_code',
        'mode',
        'size_column_priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_column_priority' => 'array',
        ];
    }
}
