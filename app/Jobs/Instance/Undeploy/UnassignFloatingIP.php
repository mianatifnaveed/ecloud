<?php

namespace App\Jobs\Instance\Undeploy;

use App\Jobs\Job;
use App\Models\V2\Instance;
use App\Traits\V2\Jobs\AwaitTask;
use App\Traits\V2\LoggableModelJob;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class UnassignFloatingIP extends Job
{
    use Batchable, LoggableModelJob, AwaitTask;
    
    private $model;

    public function __construct(Instance $instance)
    {
        $this->model = $instance;
    }

    public function handle()
    {
        $instance = $this->model;

        $instance->nics()->each(function ($nic) {
            if ($nic->floatingIp()->exists()) {
                $task = $nic->floatingIp->createTaskWithLock(
                    'floating_ip_unassign',
                    \App\Jobs\Tasks\FloatingIp\Unassign::class
                );

                Log::info('Triggered floating_ip_unassign task for Floating IP (' . $nic->floatingIp->id . ')');

                $this->awaitTask($task);
            }
        });
    }
}
