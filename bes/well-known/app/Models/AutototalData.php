<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutototalData extends Model
{
    protected $table = 'autototal_data';

    protected $fillable = [
        'itemkey',
        'art_article_nr',
        'sup_brand',
        'pret',
        'code_echiv',
        'sup_brand_echiv',
        'devumire',
    ];
}