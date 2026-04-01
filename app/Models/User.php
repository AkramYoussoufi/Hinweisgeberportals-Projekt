<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids, HasApiTokens;

    protected $fillable = [
        'email',
        'email_hash',
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
        'email',
        'email_hash',
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

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function routeNotificationForMail(): string
    {
        try {
            return $this->email ?? '';
        } catch (\Exception $e) {
            return '';
        }
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
