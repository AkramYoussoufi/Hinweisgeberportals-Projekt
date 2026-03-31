<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'report_id',
        'original_filename',
        'stored_filename',
        'mime_type',
        'size',
    ];

    protected $hidden = [
        'stored_filename',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}