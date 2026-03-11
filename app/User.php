<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Ramsey\Uuid\Uuid;

class User extends Authenticatable
{
    use Notifiable;

    const METADATA_KEYS = [
        'cemetery_location_name',
        'cemetery_location_address',
        'cemetery_location_latitude',
        'cemetery_location_longitude',
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'birth_order',
        'nickname', 'gender_id', 'name',
        'email', 'password',
        'address', 'phone',
        'dob', 'yob', 'dod', 'yod', 'city',
        'is_deceased',
        'father_id', 'mother_id', 'parent_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $appends = [
        'gender',
        'display_name',
    ];

    protected $casts = [
        'dob' => 'date',
        'dod' => 'date',
        'is_deceased' => 'boolean',
        'couples.pivot.id'  => 'string',
        'wifes.pivot.id'    => 'string',
        'husbands.pivot.id' => 'string',
    ];

    protected static function booted()
    {
        static::saving(function (self $user) {
            $user->is_deceased = $user->normalizeDeceasedState();
        });
    }

    public static function normalizeUppercase($value)
    {
        if (is_null($value) || trim($value) === '') {
            return $value;
        }

        return mb_strtoupper(trim($value), 'UTF-8');
    }

    // protected $keyType = 'string';

    public function getGenderAttribute()
    {
        return $this->gender_id == 1 ? trans('app.male_code') : trans('app.female_code');
    }

    public function getDisplayNameAttribute()
    {
        return trim($this->deceasedPrefix().' '.$this->name);
    }

    public function deceasedPrefix(): string
    {
        if (! $this->isDeceased()) {
            return '';
        }

        if ((int) $this->gender_id === 1) {
            return 'Alm.';
        }

        if ((int) $this->gender_id === 2) {
            return 'Almh.';
        }

        return '';
    }

    public function isDeceased(): bool
    {
        return (bool) $this->is_deceased || ! empty($this->dod) || ! empty($this->yod);
    }

    public function hasDeathInfo(): bool
    {
        return $this->isDeceased() || ! empty($this->dod) || ! empty($this->yod);
    }

    public function normalizeDeceasedState(): bool
    {
        if (! empty($this->dod) || ! empty($this->yod)) {
            return true;
        }

        return (bool) $this->is_deceased;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = static::normalizeUppercase($value);
    }

    public function setNicknameAttribute($value)
    {
        $this->attributes['nickname'] = static::normalizeUppercase($value);
    }

    public function setFather(User $father)
    {
        if ($father->gender_id == 1) {

            if ($father->exists == false) {
                $father->save();
            }

            $this->father_id = $father->id;
            $this->save();

            return $father;
        }

        return false;
    }

    public function setMother(User $mother)
    {
        if ($mother->gender_id == 2) {

            if ($mother->exists == false) {
                $mother->save();
            }

            $this->mother_id = $mother->id;
            $this->save();

            return $mother;
        }

        return false;
    }

    public function father()
    {
        return $this->belongsTo(User::class);
    }

    public function mother()
    {
        return $this->belongsTo(User::class);
    }

    public function childs()
    {
        if ($this->gender_id == 2) {
            return $this->hasMany(User::class, 'mother_id')->orderBy('birth_order');
        }

        return $this->hasMany(User::class, 'father_id')->orderBy('birth_order');
    }

    public function profileLink($type = 'profile')
    {
        $type = ($type == 'chart') ? 'chart' : 'show';
        return link_to_route('users.'.$type, $this->display_name, [$this->id]);
    }

    public function fatherLink()
    {
        return $this->father_id ? link_to_route('users.show', $this->father->display_name, [$this->father_id]) : null;
    }

    public function motherLink()
    {
        return $this->mother_id ? link_to_route('users.show', $this->mother->display_name, [$this->mother_id]) : null;
    }

    public function wifes()
    {
        return $this->belongsToMany(User::class, 'couples', 'husband_id', 'wife_id')->using('App\CouplePivot')->withPivot(['id'])->withTimestamps()->orderBy('marriage_date');
    }

    public function addWife(User $wife, $marriageDate = null)
    {
        if ($this->gender_id == 1 && !$this->hasBeenMarriedTo($wife)) {
            $this->wifes()->save($wife, [
                'id'            => Uuid::uuid4()->toString(),
                'marriage_date' => $marriageDate,
                'manager_id'    => auth()->id(),
            ]);
            return $wife;
        }

        return false;
    }

    public function husbands()
    {
        return $this->belongsToMany(User::class, 'couples', 'wife_id', 'husband_id')->using('App\CouplePivot')->withPivot(['id'])->withTimestamps()->orderBy('marriage_date');
    }

    public function addHusband(User $husband, $marriageDate = null)
    {
        if ($this->gender_id == 2 && !$this->hasBeenMarriedTo($husband)) {
            $this->husbands()->save($husband, [
                'id'            => Uuid::uuid4()->toString(),
                'marriage_date' => $marriageDate,
                'manager_id'    => auth()->id(),
            ]);
            return $husband;
        }

        return false;
    }

    public function hasBeenMarriedTo(User $user)
    {
        return $this->couples->contains($user);
    }

    public function couples()
    {
        if ($this->gender_id == 1) {
            return $this->belongsToMany(User::class, 'couples', 'husband_id', 'wife_id')->using('App\CouplePivot')->withPivot(['id'])->withTimestamps()->orderBy('marriage_date');
        }

        return $this->belongsToMany(User::class, 'couples', 'wife_id', 'husband_id')->using('App\CouplePivot')->withPivot(['id'])->withTimestamps()->orderBy('marriage_date');
    }

    public function marriages()
    {
        if ($this->gender_id == 1) {
            return $this->hasMany(Couple::class, 'husband_id')->orderBy('marriage_date');
        }

        return $this->hasMany(Couple::class, 'wife_id')->orderBy('marriage_date');
    }

    public function siblings()
    {
        if (is_null($this->father_id) && is_null($this->mother_id) && is_null($this->parent_id)) {
            return collect([]);
        }

        return User::where('id', '!=', $this->id)
            ->where(function ($query) {
                if (!is_null($this->father_id)) {
                    $query->where('father_id', $this->father_id);
                }

                if (!is_null($this->mother_id)) {
                    $query->orWhere('mother_id', $this->mother_id);
                }

                if (!is_null($this->parent_id)) {
                    $query->orWhere('parent_id', $this->parent_id);
                }

            })
            ->orderBy('birth_order')->get();
    }

    public function parent()
    {
        return $this->belongsTo(Couple::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class);
    }

    public function managedUsers()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function managedCouples()
    {
        return $this->hasMany(Couple::class, 'manager_id');
    }

    public function getAgeAttribute()
    {
        $ageDetail = null;
        $yearOnlySuffix = Carbon::now()->format('-m-d');

        if ($this->isDeceased() && !$this->dod && !$this->yod) {
            return null;
        }

        if ($this->dob && !$this->dod) {
            $ageDetail = Carbon::parse($this->dob)->diffInYears();
        }
        if (!$this->dob && $this->yob) {
            $ageDetail = Carbon::parse($this->yob.$yearOnlySuffix)->diffInYears();
        }
        if ($this->dob && $this->dod) {
            $ageDetail = Carbon::parse($this->dob)->diffInYears($this->dod);
        }
        if (!$this->dob && $this->yob && !$this->dod && $this->yod) {
            $ageDetail = Carbon::parse($this->yob.$yearOnlySuffix)->diffInYears($this->yod.$yearOnlySuffix);
        }
        if ($this->dob && $this->yob && $this->dod && $this->yod) {
            $ageDetail = Carbon::parse($this->dob)->diffInYears($this->dod);
        }

        return $ageDetail;
    }

    public function getAgeDetailAttribute()
    {
        $ageDetail = null;
        $yearOnlySuffix = Carbon::now()->format('-m-d');

        if ($this->isDeceased() && !$this->dod && !$this->yod) {
            return null;
        }

        if ($this->dob && !$this->dod) {
            $ageDetail = Carbon::parse($this->dob)->timespan();
        }
        if (!$this->dob && $this->yob) {
            $ageDetail = Carbon::parse($this->yob.$yearOnlySuffix)->timespan();
        }
        if ($this->dob && $this->dod) {
            $ageDetail = Carbon::parse($this->dob)->timespan($this->dod);
        }
        if (!$this->dob && $this->yob && !$this->dod && $this->yod) {
            $ageDetail = Carbon::parse($this->yob.$yearOnlySuffix)->timespan($this->yod.$yearOnlySuffix);
        }
        if ($this->dob && $this->yob && $this->dod && $this->yod) {
            $ageDetail = Carbon::parse($this->dob)->timespan($this->dod);
        }

        return $ageDetail;
    }

    public function getAgeStringAttribute()
    {
        return '<div title="'.$this->age_detail.'">'.$this->age.' '.trans_choice('user.age_years', $this->age).'</div>';
    }

    public function getBirthdayAttribute()
    {
        if (!$this->dob || $this->isDeceased()) {
            return;
        }

        $birthdayDate = date('Y').substr($this->dob, 4);
        $birthdayDateClass = Carbon::parse($birthdayDate);

        if (Carbon::parse(date('Y-m-d').' 00:00:00')->gt($birthdayDateClass)) {
            return $birthdayDateClass->addYear();
        }

        return $birthdayDateClass;
    }

    public function getBirthdayRemainingAttribute()
    {
        if ($this->dob && !$this->isDeceased()) {
            return Carbon::now()->diffInDays($this->birthday, false);
        }
    }

    public function metadata()
    {
        return $this->hasMany(UserMetadata::class, 'user_id', 'id');
    }

    public function getMetadata($key = null, $defaultValue = null)
    {
        $metadata = $this->metadata;

        if (is_null($key)) {
            $metadataCollection = [];
            foreach ($metadata as $metaKey => $metaValue) {
                $metadataCollection[$metaKey] = $metaValue;
            }

            return collect($metadataCollection);
        }

        $meta = $metadata->filter(function ($meta) use ($key) {
            return $meta->key == $key;
        })->first();

        if ($meta) {
            return $meta->value;
        }

        return $defaultValue;
    }
}
