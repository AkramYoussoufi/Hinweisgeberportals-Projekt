<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'reference_number',
        'category',
        'status',
        'subject',
        'description',
        'incident_date',
        'incident_location',
        'involved_persons',
        'is_anonymous',
        'closed_at',
    ];

    protected $hidden = [
        'user_id',
    ];


    protected static function booted(): void
    {
        static::deleting(function (Report $report) {
            foreach ($report->attachments as $attachment) {
                $path = 'attachments/' . $report->id . '/' . $attachment->stored_filename;
                Storage::disk('local')->delete($path);
            }
            Storage::disk('local')->deleteDirectory('attachments/' . $report->id);
        });
    }

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'closed_at'     => 'datetime',
            'is_anonymous'  => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
