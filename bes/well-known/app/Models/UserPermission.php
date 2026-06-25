<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    protected $fillable = ['user_id', 'menu_key', 'permission'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'Id');
    }
}
