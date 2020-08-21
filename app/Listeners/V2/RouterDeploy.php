<?php

namespace App\Listeners\V2;

use App\Events\V2\NetworkCreated;
use App\Models\V2\Network;
use App\Services\NsxService;
use App\Events\V2\RouterCreated;
use App\Models\V2\Router;
use App\Models\V2\FirewallRule;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use GuzzleHttp\Exception\GuzzleException;

class RouterDeploy implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * This needs replacing with a lookup to find the edge cluster for
     * the VPC this router belongs too
     */
    const EDGE_CLUSTER_ID = '8bc61267-583e-4988-b5d9-16b46f7fe900';

    /**
     * @param RouterCreated $event
     * @return void
     * @throws \Exception
     */
    public function handle(RouterCreated $event)
    {
        $event->router->each(function ($router) {
            /** @var Router $router */

            dd($router->availabilityZones()->first());

            try {
                $nsxClient = $router->availabilityZones()->first()->nsxClient();
                $nsxClient->put('policy/api/v1/infra/tier-1s/' . $router->id, [
                    'json' => [
                        'tier0_path' => '/infra/tier-0s/T0',
                    ],
                ]);
                $nsxClient->put('policy/api/v1/infra/tier-1s/' . $router->id . '/locale-services/' . $router->id, [
                    'json' => [
                        'edge_cluster_path' => '/infra/sites/default/enforcement-points/default/edge-clusters/' . self::EDGE_CLUSTER_ID,
                    ],
                ]);
            } catch (GuzzleException $exception) {
                $json = json_decode($exception->getResponse()->getBody()->getContents());
                throw new \Exception($json);
            }
            $router->deployed = true;
            $router->save();

            $firewallRule = app()->make(FirewallRule::class);
            $firewallRule->router()->attach($router);
            $firewallRule->save();

            $router->networks()->each(function ($network) {
                /** @var Network $network */
                event(new NetworkCreated($network));
            });
        });
    }
}
