<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cart',
    ];

    protected $casts = [
        'cart' => 'array',
    ];
}
