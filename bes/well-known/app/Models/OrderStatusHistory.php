<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'old_status',
        'new_status',
        'user_id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'old_status' => 'integer',
        'new_status' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that this status history belongs to
     */
    public function order()
    {
        return $this->belongsTo(Comenzi::class, 'order_id', 'idcomanda');
    }

    /**
     * Get the user who made the status change
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'Id');
    }
}

