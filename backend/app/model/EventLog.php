<?php

declare(strict_types=1);

namespace app\model;

use support\Model;

class EventLog extends Model
{
    protected $table = 'event_logs';
    protected $guarded = ['id'];
    public $timestamps = false;
}
