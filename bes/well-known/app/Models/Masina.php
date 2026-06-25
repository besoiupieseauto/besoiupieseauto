<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Masina extends Model
{
    use HasFactory;

    protected $table = 'masina';
    protected $primaryKey = 'idmasina';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'marca',
        'sasiu'
    ];
}
