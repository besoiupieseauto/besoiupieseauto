<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incasari extends Model 
{
    use HasFactory;

    protected $table = 'incasari';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idcmd',
        'userid',
        'idclient',
        'cstmtext',
        'suma',
        'data',
        'data_time',
        'idstare',
        'locatie_mgz'
    ];

    public function client()
    {
        return $this->belongsTo(Clienti::class, 'idclient', 'idclienti');
    }
	
    public function user()
    {
		return $this->belongsTo(User::class, 'userid', 'Id');
    }

    public function comanda()
    {
        return $this->belongsTo(Comenzi::class, 'idcmd', 'idcomanda');
    }
}