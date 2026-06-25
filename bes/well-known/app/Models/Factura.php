<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;
    
    // Table name
    protected $table = 'facturi';
    
    // Primary key (if different from the default 'id')
    protected $primaryKey = 'OrderID';  // Change this if the primary key is different
    
    // Disable timestamps if they aren't part of the table structure
    public $timestamps = false;
    
    // Columns that are mass assignable
    protected $fillable = [
        'OrderID',
        'CustomerID',
        'EmployeeID',
        'OrderDate',
        'RequiredDate',
        'seria',
        'valid',
        'tip_incas',
        'id_comanda',
        'tip_comanda',
        'id_chitanta',
        'id_oferta',
        'id_proforma',
        'id_aviz',
        'id_fact',
		'negative_issued',
        'created_at'
    ];
    
    // Casts for specific data types (optional)
    protected $casts = [
        'OrderDate' => 'date',
        'RequiredDate' => 'date',
        //'created_at' => 'datetime',
    ];
    
    // In your Factura model
    public function client()
    {
        return $this->belongsTo(Client::class, 'CustomerID', 'idclienti');
    }
    
    public function details()
    {
        return $this->hasMany(FacturiDetail::class, 'OrderID', 'OrderID');
    }
}