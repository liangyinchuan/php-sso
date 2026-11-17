<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class UserConsent extends Model
{
    protected $table = 'user_consents';
    protected $guarded = ['id'];
    protected $dateFormat = 'Y-m-d H:i:s';
}
