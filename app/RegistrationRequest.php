<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'requested_birth_date',
        'notes',
        'status',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'requested_birth_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
