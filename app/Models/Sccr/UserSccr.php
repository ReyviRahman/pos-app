<?php

namespace App\Models\Sccr;

// Pastikan meng-import class User untuk Auth
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserSccr extends Authenticatable
{
    use Notifiable;

    // Arahkan ke koneksi database sccr_db yang sudah disetting di config/database.php
    protected $connection = 'sccr_db'; 
    protected $table = 'auth_users';
    
    // Opsional: Jika nama tabel di sccr_db bukan 'users', definisikan di sini
    // protected $table = 'nama_tabel_user_sccr';

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
