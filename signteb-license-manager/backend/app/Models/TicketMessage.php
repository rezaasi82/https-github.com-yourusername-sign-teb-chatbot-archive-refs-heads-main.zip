<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'author_type', 'author_id', 'body', 'attachments'];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
