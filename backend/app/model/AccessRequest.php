<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class AccessRequest extends Model
{
    protected $table = 'access_requests';
    protected $guarded = ['id'];
    protected $dateFormat = 'Y-m-d H:i:s';
}
