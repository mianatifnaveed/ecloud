<?php

namespace App\Jobs\Sync\VpnSession;

use App\Jobs\Job;
use App\Jobs\Nsx\VpnSession\Undeploy;
use App\Jobs\Nsx\VpnSession\UndeployCheck;
use App\Jobs\VpnSession\DeletePreSharedKey;
use App\Models\V2\Task;
use App\Traits\V2\LoggableTaskJob;
use App\Traits\V2\TaskableBatch;

class Delete extends Job
{
    use TaskableBatch, LoggableTaskJob;

    private $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function handle()
    {

        $this->deleteTaskBatch([
            [
                new Undeploy($this->task->resource),
                new UndeployCheck($this->task->resource),
                new DeletePreSharedKey($this->task->resource)
            ]
        ])->dispatch();
    }
}
