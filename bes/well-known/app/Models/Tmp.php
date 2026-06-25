<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tmp extends Model
{
    use HasFactory;

    protected $table = 'tmp'; // Table name define karna zaroori hai

    protected $primaryKey = 'id_tmp'; // Custom primary key

    public $timestamps = false; // Agar `created_at` aur `updated_at` nahi chahiye

    protected $fillable = [
        'id_produs',
        'cantitate_tmp',
        'pret_tmp',
        'session_id',
        'culoare',
        'furnizor',
        'tva',  
       'tva_tmp'        // Add VAT amount field
    ];
    
      public function product()
    {
        return $this->belongsTo(Produse::class, 'id_produs', 'idprodus');
    }
    
    
}










