<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
    ];
}
