<?php

namespace App\Models;

use Database\Factories\ChatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title'])]
class Chat extends Model
{
    /** @use HasFactory<ChatFactory> */
    use HasFactory;

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return 'id';
    }

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the chat.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages for the chat.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }
}
