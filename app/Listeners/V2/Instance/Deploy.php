<?php

namespace App\Listeners\V2\Instance;

use App\Events\V2\Instance\Deploy as DeployEvent;
use App\Events\V2\Instance\Deploy\Data as DeployEventData;
use App\Jobs\Instance\Deploy\ActivateWindows;
use App\Jobs\Instance\Deploy\AssignFloatingIp;
use App\Jobs\Instance\Deploy\ConfigureNics;
use App\Jobs\Instance\Deploy\ConfigureWinRm;
use App\Jobs\Instance\Deploy\OsCustomisation;
use App\Jobs\Instance\Deploy\PrepareOsDisk;
use App\Jobs\Instance\Deploy\PrepareOsUsers;
use App\Jobs\Instance\Deploy\RunApplianceBootstrap;
use App\Jobs\Instance\Deploy\RunBootstrapScript;
use App\Jobs\Instance\Deploy\UpdateNetworkAdapter;
use App\Jobs\Instance\Deploy\WaitOsCustomisation;
use App\Jobs\Instance\PowerOn;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class Deploy implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @param DeployEvent $event
     * @return void
     * @throws Exception
     */
    public function handle(DeployEvent $event)
    {
        /** @var DeployEventData $data */
        $data = $event->data;

        // TODO :- post MVP, replace this in the jobs so we just pass in the "$event->data"
        $data = (array)$data;

        // Create the chained jobs for deployment
        dispatch((new \App\Jobs\Instance\Deploy\Deploy($data))->chain([
            new ConfigureNics($data),
            new AssignFloatingIp($data),
            new UpdateNetworkAdapter($data),
            new OsCustomisation($data),
            new PowerOn($data),
            //new WaitOsCustomisation($data),
            new ConfigureWinRm($data),
            new ActivateWindows($data),
            new PrepareOsUsers($data),
            new PrepareOsDisk($data),
            new RunApplianceBootstrap($data),
            new RunBootstrapScript($data),
        ]));
    }
}
