<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class User extends Model
{
    protected $table = 'users';
    protected $guarded = ['id'];
    protected $hidden = ['password'];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $casts = [
        'profile' => 'array',
        'is_admin' => 'boolean',
        'status' => 'integer',
    ];

    public function memberships()
    {
        return $this->hasMany(AppUser::class, 'user_id');
    }
}
