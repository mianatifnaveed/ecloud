<?php

namespace App\Jobs\Sync\HostGroup;

use App\Jobs\Job;
use App\Models\V2\HostGroup;
use Illuminate\Support\Facades\Log;

class Delete extends Job
{
    private $model;

    public function __construct(HostGroup $model)
    {
        $this->model = $model;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->model->id]);

        $jobs = [
            // TODO :- Undeploy
            new \App\Jobs\Sync\Completed($this->model),
            new \App\Jobs\Sync\Delete($this->model),
        ];
        dispatch(array_shift($jobs)->chain($jobs));

        Log::info(get_class($this) . ' : Finished', ['id' => $this->model->id]);
    }

    public function failed($exception)
    {
        $this->model->setTaskFailureReason($exception->getMessage());
    }
}
