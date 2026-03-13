<?php

namespace App;

use App\Services\FamilyScopeResolver;
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
        'password',
        'requested_birth_date',
        'notes',
        'domain_host',
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

    public function scopeForTenant($query, FamilyScopeResolver $familyScopeResolver)
    {
        if (!$familyScopeResolver->hasActiveScope()) {
            return $query;
        }

        $host = $familyScopeResolver->currentHost();
        $visibleIds = $familyScopeResolver->visibleUserIds();

        return $query->where(function ($tenantQuery) use ($host, $visibleIds) {
            $tenantQuery->where('domain_host', $host);

            if (!empty($visibleIds)) {
                $tenantQuery->orWhere(function ($legacyQuery) use ($visibleIds) {
                    $legacyQuery->whereNull('domain_host')
                        ->whereHas('user', function ($userQuery) use ($visibleIds) {
                            $userQuery->whereIn('id', $visibleIds);
                        });
                });
            }
        });
    }
}
