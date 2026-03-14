<?php

namespace App;

use App\Services\FamilyScopeResolver;
use Illuminate\Database\Eloquent\Model;

class BulkEditImport extends Model
{
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    public const STATUS_COMPLETED = 'completed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_host',
        'source_type',
        'source_name',
        'uploaded_by',
        'status',
        'summary_json',
    ];

    protected $casts = [
        'summary_json' => 'array',
    ];

    public function rows()
    {
        return $this->hasMany(BulkEditImportRow::class)->orderBy('sheet_name')->orderBy('row_number');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForTenant($query, FamilyScopeResolver $familyScopeResolver)
    {
        if (! $familyScopeResolver->hasActiveScope()) {
            return $query;
        }

        return $query->where('tenant_host', $familyScopeResolver->currentHost());
    }
}
