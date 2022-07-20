<?php

namespace App\Jobs\Tasks\Instance;

use App\Jobs\Job;
use App\Models\V2\Image;
use App\Models\V2\Task;
use App\Traits\V2\LoggableTaskJob;
use App\Traits\V2\TaskableBatch;
use Illuminate\Bus\Batchable;

class PowerOn extends Job
{
    use TaskableBatch, Batchable, LoggableTaskJob;

    const TASK_NAME = 'power_on';

    private Task $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function handle()
    {
        $this->updateTaskBatch([
            [
                new \App\Jobs\Instance\PowerOn($this->task->resource)
            ]
        ])->dispatch();
    }
}
