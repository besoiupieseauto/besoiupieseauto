<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipPlata extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'tip_plata';

    // Primary key
    protected $primaryKey = 'id_plata';

    // Disable timestamps if they aren't part of the table structure
    public $timestamps = false;

    // Columns that are mass assignable
    protected $fillable = [
        'id_plata',
        'denumire',
    ];

    // Casts for specific data types (optional)
    protected $casts = [
        'id_plata' => 'integer',
        'denumire' => 'string',
    ];
}
