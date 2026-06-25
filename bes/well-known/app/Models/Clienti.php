<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clienti extends Model
{
    use HasFactory;

    protected $table = 'clienti';
    protected $primaryKey = 'idclienti';
    public $timestamps = false;

    protected $fillable = [
        'nume',
        'companie'
    ];

    public function incasari()
    {
        return $this->hasMany(Incasari::class, 'idclient', 'idclienti');
    }
}