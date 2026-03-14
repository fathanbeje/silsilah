<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BulkEditImportRow extends Model
{
    public const TYPE_EXISTING = 'existing_update';
    public const TYPE_SPOUSE = 'new_spouse';
    public const TYPE_CHILD = 'new_child';
    public const TYPE_STANDALONE = 'new_standalone';

    public const STATUS_READY = 'ready';
    public const STATUS_NEEDS_MAPPING = 'needs_mapping';
    public const STATUS_NEEDS_ANCHOR = 'needs_anchor';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'bulk_edit_import_id',
        'sheet_name',
        'row_number',
        'row_key',
        'row_type',
        'target_user_id',
        'payload_json',
        'normalized_json',
        'resolution_json',
        'status',
        'error_messages_json',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'normalized_json' => 'array',
        'resolution_json' => 'array',
        'error_messages_json' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function import()
    {
        return $this->belongsTo(BulkEditImport::class, 'bulk_edit_import_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
