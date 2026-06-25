<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class FacturiDetail extends Model
{
    use HasFactory;
    
    protected $table = 'facturidetails'; // Table name
    protected $primaryKey = 'OrderID'; // Primary key
    public $timestamps = false; // If there are no created_at and updated_at fields
    
    protected $fillable = [
        'OrderID',
        'ProductID',
        'ProductId',
        'UnitPrice',
        'Quantity',
        'Discount',
        'tva',
        'total',
        'culoare',
        'furnizor'
    ];
    
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'OrderID', 'OrderID');
    }
    
    public function product()
    {
        return $this->belongsTo(Produse::class, 'ProductID', 'idprodus');
    }
    
    
    
    public function invoice()
    {
        return $this->belongsTo(Factura::class, 'OrderID', 'OrderID');
    }
}