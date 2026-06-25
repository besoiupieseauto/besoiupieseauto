<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produse extends Model
{
    use HasFactory;

    protected $table = 'produse';
    protected $primaryKey = 'idprodus';
    public $timestamps = false; // Disable Laravel's automatic timestamps

    protected $fillable = [
        'denumire', 'cod_produs', 'pret', 'TVA', 'um', 'created_at'
    ];

}
