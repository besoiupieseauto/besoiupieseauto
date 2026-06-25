<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'comenzi'; // Ensure this matches your database table name

    protected $primaryKey = 'idcmd'; // Set primary key if different from 'id'

    protected $fillable = [
        'idcomanda',
        'idclient',
        'total',
        'idmachine',
        'return',
        'stare',
        'data',
        'cont_awb',
        'invoice_id',
        'location_mgz',
        'customer_name',
        'phone',
        'address',
        'address_id',
        'brand',
        'transport',
        'awb',
        'status',
    ];

    public $timestamps = true;

    /**
     * Get the products for the order.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('quantity', 'price', 'color_status', 'supplier')
            ->withTimestamps();
    }

    /**
     * Get the address for the order.
     */
    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get the status text.
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            1 => 'Comandat',
            2 => 'Sosit',
            3 => 'Cash',
            4 => 'Avans',
            5 => 'Retur',
            6 => 'Card',
            7 => 'FD',
        ];
        
        return $statuses[$this->status] ?? 'Necunoscut';
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            1 => 'rgb(235,147,22)', // Comandat - Orange
            2 => 'rgb(42,171,210)', // Sosit - Blue
            3 => 'btn-success',     // Cash - Green
            4 => 'rgb(193,46,42)',  // Avans - Red
            5 => 'rgb(101,78,240)', // Retur - Purple
            6 => 'rgb(18,38,18)',   // Card - Dark Green
            7 => 'rgb(220,125,13)', // FD - Dark Orange
        ];

        return $colors[$this->status] ?? 'btn-default';
    }
}

// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
    ];

    /**
     * Get the orders for the product.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class)
            ->withPivot('quantity', 'price', 'color_status', 'supplier')
            ->withTimestamps();
    }
}

// app/Models/Address.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the orders for the address.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

// app/Models/Supplier.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];
}
