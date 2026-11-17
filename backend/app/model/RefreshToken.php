<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';
    protected $guarded = ['id'];
    public $timestamps = false;
}
