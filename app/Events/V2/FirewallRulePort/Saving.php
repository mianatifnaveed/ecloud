<?php

namespace App\Events\V2\FirewallRulePort;

use App\Events\Event;
use Illuminate\Database\Eloquent\Model;

class Saving extends Event
{
    public $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
