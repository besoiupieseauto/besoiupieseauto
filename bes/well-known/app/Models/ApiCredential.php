<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ApiCredential extends Model
{
    protected $table = 'api_credentials';

    protected $fillable = ['service_name', 'data_key', 'data_value'];

    protected function dataValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) =>
                $value ? decrypt($value) : '',

            set: fn ($value) =>
                encrypt($value ?? ''),
        );
    }
}
