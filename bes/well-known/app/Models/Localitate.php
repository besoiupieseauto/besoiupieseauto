<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Localitate extends Model
{
    use HasFactory;

    protected $table = 'localitati'; // Table name

    protected $primaryKey = 'idlocatie'; // Primary key

    public $timestamps = false; // Agar created_at & updated_at nahi chahiye

    protected $fillable = [
        'judet',
        'localitate',
        'idlocatie',
        'codrutare',
    ];
}
