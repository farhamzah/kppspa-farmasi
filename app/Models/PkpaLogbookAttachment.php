<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaLogbookAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_logbook_entry_id',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'checksum',
        'status',
        'uploaded_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(PkpaLogbookEntry::class, 'pkpa_logbook_entry_id');
    }
}
