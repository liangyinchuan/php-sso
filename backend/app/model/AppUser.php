<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class AppUser extends Model
{
    protected $table = 'app_users';
    protected $guarded = ['id'];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $casts = [
        'roles' => 'array',
        'profile' => 'array',
        'status' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'app_id');
    }
}
