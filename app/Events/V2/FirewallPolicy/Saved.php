<?php

namespace App\Events\V2\FirewallPolicy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

class Saved
{
    use SerializesModels;

    public $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}