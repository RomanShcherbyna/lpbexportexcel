<?php

namespace App\BaseLinkerTest\Models;

use Illuminate\Database\Eloquent\Model;

final class BlCategory extends Model
{
    protected $table = 'bl_categories';

    /** @var array<int,string> */
    protected $fillable = [
        'id',
        'inventory_id',
        'name',
        'parent_id',
    ];

    public $incrementing = false;
    protected $keyType = 'int';
}

