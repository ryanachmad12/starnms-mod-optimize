<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'is_active', 'last_login_at'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['is_active' => 'boolean', 'last_login_at' => 'datetime', 'password' => 'hashed'];

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }
}
