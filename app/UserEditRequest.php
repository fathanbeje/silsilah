<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserEditRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'target_user_id',
        'requester_name',
        'requester_whatsapp',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'proposed_profile',
        'proposed_metadata',
        'proposed_new_spouses',
        'proposed_new_children',
        'proposed_photo_path',
        'review_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'proposed_profile' => 'array',
        'proposed_metadata' => 'array',
        'proposed_new_spouses' => 'array',
        'proposed_new_children' => 'array',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function summaryParts(): array
    {
        $parts = [];

        if (!empty($this->proposed_profile)) {
            $parts[] = count($this->proposed_profile).' field profil';
        }

        if (!empty($this->proposed_metadata)) {
            $parts[] = count($this->proposed_metadata).' metadata';
        }

        if (!empty($this->proposed_photo_path)) {
            $parts[] = 'foto';
        }

        if (!empty($this->proposed_new_spouses)) {
            $parts[] = count($this->proposed_new_spouses).' pasangan baru';
        }

        if (!empty($this->proposed_new_children)) {
            $parts[] = count($this->proposed_new_children).' anak baru';
        }

        return $parts;
    }
}
