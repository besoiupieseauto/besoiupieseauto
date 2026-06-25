<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Detaliu extends Model
{
    use HasFactory;
 
    protected $table = 'detaliu'; // Table name

    protected $primaryKey = 'iddetaliu'; // Primary key

    public $timestamps = false; // Since `created_at` is present but `updated_at` is missing

    protected $fillable = [
        'idprodus',
        'idcomanda',
        'cantitate',
        'pret',
        'culoare',
        'furnizor',
        'created_at'
    ];

    protected $casts = [
        'cantitate' => 'float',
        'pret' => 'float',
        'created_at' => 'datetime',
    ];
}
