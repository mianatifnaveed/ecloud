<?php

namespace App\Events\V2\Task;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

class Updated
{
    use SerializesModels;

    public $model;
    public $original;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->original = $model->getOriginal();
    }
}
