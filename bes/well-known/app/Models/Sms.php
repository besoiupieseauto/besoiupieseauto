<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sms extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sms';
    
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'idsms';
    
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'idcomanda',
        'idprimit',
        'status',
        'data_exp',
        'cost',
        'idcomanda_ext',
        'telefon',
        'mesaj',
        'data'
    ];
}