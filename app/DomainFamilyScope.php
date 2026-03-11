<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DomainFamilyScope extends Model
{
    protected $fillable = [
        'host',
        'core_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function coreUser()
    {
        return $this->belongsTo(User::class, 'core_user_id');
    }
}
