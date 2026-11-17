<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class Application extends Model
{
    protected $table = 'applications';
    protected $guarded = ['id'];
    protected $hidden = ['client_secret_hash'];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $casts = [
        'redirect_uris' => 'array',
        'post_logout_uris' => 'array',
        'scopes' => 'array',
        'auto_provision' => 'boolean',
        'require_consent' => 'boolean',
        'status' => 'integer',
        'access_ttl' => 'integer',
        'refresh_ttl' => 'integer',
    ];

    public function members()
    {
        return $this->hasMany(AppUser::class, 'app_id');
    }
}
