<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'report_id',
        'actor_id',
        'action',
        'old_value',
        'new_value',
        'ip_address',
    ];

    protected $hidden = [
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}