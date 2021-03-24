<?php

namespace App\Jobs\Kingpin\Host;

use App\Jobs\Job;
use App\Models\V2\Host;
use Illuminate\Support\Facades\Log;

class CheckOnline extends Job
{
    private $model;

    public function __construct(Host $model)
    {
        $this->model = $model;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->model->id]);

        // TODO :- https://gitlab.devops.ukfast.co.uk/ukfast/api.ukfast/ecloud/-/issues/625

        Log::info(get_class($this) . ' : Finished', ['id' => $this->model->id]);
    }

    public function failed($exception)
    {
        $this->model->setSyncFailureReason($exception->getMessage());
    }
}