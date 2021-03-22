<?php

namespace App\Jobs\Sync\Instance;

use App\Jobs\Instance\Deploy\ActivateWindows;
use App\Jobs\Instance\Deploy\AssignFloatingIp;
use App\Jobs\Instance\Deploy\AttachOsDisk;
use App\Jobs\Instance\Deploy\ConfigureNics;
use App\Jobs\Instance\Deploy\ConfigureWinRm;
use App\Jobs\Instance\Deploy\DeployCompleted;
use App\Jobs\Instance\Deploy\ExpandOsDisk;
use App\Jobs\Instance\Deploy\OsCustomisation;
use App\Jobs\Instance\Deploy\PrepareOsDisk;
use App\Jobs\Instance\Deploy\PrepareOsUsers;
use App\Jobs\Instance\Deploy\RunApplianceBootstrap;
use App\Jobs\Instance\Deploy\RunBootstrapScript;
use App\Jobs\Instance\Deploy\UpdateNetworkAdapter;
use App\Jobs\Instance\Deploy\WaitOsCustomisation;
use App\Jobs\Instance\PowerOn;
use App\Jobs\Job;
use App\Jobs\Kingpin\Volume\CapacityChange;
use App\Jobs\Instance\Deploy\Deploy;
use App\Jobs\Kingpin\Volume\IopsChange;
use App\Jobs\Sync\Completed;
use App\Listeners\V2\Instance\ComputeChange;
use App\Models\V2\Sync;
use App\Models\V2\Volume;
use App\Traits\V2\SyncableBatch;
use Illuminate\Support\Facades\Log;

class Update extends Job
{
    use SyncableBatch;

    private $sync;
    private $originalValues;

    public function __construct(Sync $sync)
    {
        $this->sync = $sync;
        $this->originalValues = $sync->resource->getOriginal();
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->sync->id, 'resource_id' => $this->sync->resource->id]);

        if (!$this->sync->resource->deployed) {
            $this->updateSyncBatch([
                [
                    new Deploy($this->sync->resource),
                    new PrepareOsDisk($this->sync->resource),
                    new AttachOsDisk($this->sync->resource),
                    new ConfigureNics($this->sync->resource),
                    new AssignFloatingIp($this->sync->resource),
                    new UpdateNetworkAdapter($this->sync->resource),
                    new OsCustomisation($this->sync->resource),
                    new PowerOn($this->sync->resource),
                    new WaitOsCustomisation($this->sync->resource),
                    new PrepareOsUsers($this->sync->resource),
                    new ExpandOsDisk($this->sync->resource),
                    new ConfigureWinRm($this->sync->resource),
                    new ActivateWindows($this->sync->resource),
                    new RunApplianceBootstrap($this->sync->resource),
                    new RunBootstrapScript($this->sync->resource),
                    new DeployCompleted($this->sync->resource),
                ],
            ])->dispatch();
        } else {
            Log::warning("DEBUG :: instance update jobs initialization here");
            //$jobs[] = new ComputeChange($this->sync->resource, $this->originalValues);
        }

        Log::info(get_class($this) . ' : Finished', ['id' => $this->sync->id, 'resource_id' => $this->sync->resource->id]);
    }
}
