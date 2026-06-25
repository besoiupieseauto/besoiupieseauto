<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierOrder extends Model
{
    protected $table = 'supplier_orders';

    protected $fillable = [
        'supplier',
        'order_number',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];
}
