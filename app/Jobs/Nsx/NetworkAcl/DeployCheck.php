<?php

namespace App\Jobs\Nsx\NetworkAcl;

use App\Jobs\Job;
use App\Models\V2\NetworkAcl;
use Illuminate\Support\Facades\Log;

class DeployCheck extends Job
{
    const RETRY_DELAY = 5;

    public $tries = 500;

    private $model;

    public function __construct(NetworkAcl $model)
    {
        $this->model = $model;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->model->id]);

        // @todo DeployCheck implementation goes here

        $this->model->setSyncCompleted();

        Log::info(get_class($this) . ' : Finished', ['id' => $this->model->id]);
    }
}
