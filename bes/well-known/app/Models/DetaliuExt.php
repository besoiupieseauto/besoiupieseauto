<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetaliuExt extends Model
{
    use HasFactory;

    // Specify the table name if it does not follow Laravel's naming conventions
    protected $table = 'detaliu_ext';

    // Specify the primary key if it does not follow Laravel's convention of 'id'
    protected $primaryKey = 'iddetaliu';

    // Disable auto-increment if the primary key is not auto-incrementing
    public $incrementing = false;

    // Specify the fillable fields (to prevent mass-assignment vulnerability)
    protected $fillable = [
        'idprodus',
        'idcomanda',
        'cantitate',
        'pret',
        'culoare',
        'furnizor',
		'created_at'
    ];

    // Define the default values for some fields (if necessary)
    protected $attributes = [
        'culoare' => 'FFFFFF',
        'furnizor' => '__',
    ];

    // You can add any specific casts or date attributes here if necessary
    protected $casts = [
        'created_at' => 'datetime',
    ];
}
