<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierSavedCart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'phone', 'vin', 'cart', 'alreadygenerated', 'created_at', 'updated_at'];

    protected $casts = [
        'cart' => 'array', // Automatically cast JSON to array
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'Id');
    }
}