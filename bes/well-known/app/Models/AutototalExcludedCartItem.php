<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutototalExcludedCartItem extends Model
{
    protected $table = 'autototal_excluded_cart_items';

    protected $fillable = [
        'user_id',
        'order_from',
        'cart_item_key',
        'supplier',
        'product_code',
        'variant_code',
        // itemkey is the AutoTotal Availability request key used to fetch latest prices
        'itemkey',
        'product_name',
        'manufacturer',
        'qty',
        'price',
        'currency',
        'stock',
        'livrare',
        'depozit',
    ];
}

