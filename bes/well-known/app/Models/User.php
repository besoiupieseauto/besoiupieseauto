<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'username',
        'email',
        'nume_complet',
        'telefon', 
        'magazin_id',
        'avatar',
        'rol',
        'password',
        'nume',
        'prenume',
        'is_admin',
        'active',
        'email_verified',
        'verification_code',
        'verification_sent_at',
        'verified_at',
        'requires_2fa',
        'failed_attempts',
        'last_failed_login',
        'blocked_until',
        'last_login',
        'last_password_change',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified' => 'datetime',
        'requires_2fa' => 'boolean',
        'blocked_until' => 'datetime',
        'last_failed_login' => 'datetime',
    ];

    // If you're using username for login instead of email, override the getAuthIdentifierName method
    public function getAuthIdentifierName()
    {
        return 'username'; // Or 'email' if you prefer email
    }
	
	public function permissions()
	{
		return $this->hasMany(UserPermission::class, 'user_id', 'Id');
	}

	public function hasPermission($menuKey)
	{
		if ($this->rol === 'manager') {
			return true;
		}
		return $this->permissions()->where('menu_key', $menuKey)->where('permission', 1)->exists();
	}

    // You may also need to override the getAuthPassword method if the password column has any customizations
}

