<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reminder_category_id',
    'title',
    'description',
    'deadline',
    'deadline_has_time',
    'remind_before',
])]
class Reminder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'deadline_has_time' => 'boolean',
            'remind_before' => 'integer',
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Reminder $reminder) {
            $reminder->remind_at = $reminder->deadline && $reminder->remind_before !== null
                ? $reminder->deadline->copy()->subMinutes($reminder->remind_before)
                : null;

            if ($reminder->isDirty('remind_at')) {
                $reminder->sent_at = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReminderCategory::class, 'reminder_category_id');
    }
}
