<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'email',
        'password',
        'role',
        'is_anonymous',
        'anon_token',
        'anon_pin_hash',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'anon_pin_hash',
        'email'
    ];

    protected function casts(): array
    {
        return [
            'email'          => 'encrypted',
            'password'       => 'hashed',
            'anon_pin_hash'  => 'hashed',
            'is_anonymous'   => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}