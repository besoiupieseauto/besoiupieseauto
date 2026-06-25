<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comenzi extends Model
{
    use HasFactory;

    protected $table = 'comenzi';
    protected $primaryKey = 'idcmd';
    public $timestamps = false;

    protected $fillable = [
        'idcomanda',
        'idclient',
        'userid',
        'data',
        'idmasina',
        'total',
        'stare',
        'cont_awb',
        'locatie_mgz',
        'marca',
		'observations',
		'created_at'
    ];


    public function incasari()
    {
        return $this->hasMany(Incasari::class, 'idcomanda', 'idcomanda');
    }
	
	public function user()
	{
		return $this->belongsTo(User::class, 'userid', 'Id');
	}   
}
