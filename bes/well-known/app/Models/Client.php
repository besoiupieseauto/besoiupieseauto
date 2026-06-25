<?php

// app/Models/Client.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model {
    use HasFactory;
 
    protected $table = 'clienti';
    protected $primaryKey = 'idclienti';
    public $timestamps = false; // Disable Laravel's automatic timestamps

    protected $fillable = [
		'nume',
		'adresa',
		'telefon',
		'idmasina',
		'marca',
		'sasiu',
		'nr_inmat',
		'idlocalitate',
		'companie',
		'cif',
		'regcom',
		'email',
		'cont_banca',
		'nume_banca',

        'localitate_livrare',
        'adresa_livrare',
        'localitate_facturare',
        'adresa_facturare',
		'created_at'
	];




  public function facturi()
    {
        return $this->hasMany(Factura::class, 'CustomerID', 'idclienti');
    }
    
  
}