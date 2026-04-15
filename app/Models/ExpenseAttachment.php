<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExpenseAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'file_path',
        'file_name',
        'mime_type',
        'size_bytes',
    ];

    /** Accessors to auto-append to JSON/array responses (so the frontend gets `url`). */
    protected $appends = ['url', 'is_image', 'is_pdf'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk(config('filesystems.media_disk', 'public'))->url($this->file_path);
    }

    public function getIsImageAttribute(): bool
    {
        if (str_starts_with((string) $this->mime_type, 'image/')) {
            return true;
        }
        $ext = strtolower(pathinfo($this->file_name ?? '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function getIsPdfAttribute(): bool
    {
        if ($this->mime_type === 'application/pdf') {
            return true;
        }
        $ext = strtolower(pathinfo($this->file_name ?? '', PATHINFO_EXTENSION));

        return $ext === 'pdf';
    }
}
