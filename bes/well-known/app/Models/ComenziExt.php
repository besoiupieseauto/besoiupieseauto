<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComenziExt extends Model
{
    use HasFactory;

    protected $table = 'comenzi_ext';
    protected $primaryKey = 'idcmd';
    public $timestamps = false;

    protected $fillable = [
        'idcomanda',
        'idclient',
        'userid',
        'idprodus',
        'cantitate',
        'total',
        'idmasina',
        'stare',
        'retur',
        'data',
        'awb',
        'cont_awb',
        'id_factura',
        'created_at',
    ];

    protected $casts = [
        'cantitate' => 'float',
        'total' => 'float',
        'stare' => 'integer',
        'retur' => 'integer',
        'data' => 'date',
        //'created_at' => 'datetime',
    ];
    
    public function client()
	{
		return $this->belongsTo(Client::class, 'idclient', 'idclienti');
	}
    
	public function user()
	{
		return $this->belongsTo(User::class, 'userid', 'Id');
	}    
}
