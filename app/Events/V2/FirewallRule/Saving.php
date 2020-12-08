<?php

namespace App\Events\V2\FirewallRule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

class Saving
{
    use SerializesModels;

    public $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
