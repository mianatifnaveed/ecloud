<?php

namespace App\Listeners\V2;

use App\Events\V2\NetworkCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class NetworkDeploy implements ShouldQueue
{
    use InteractsWithQueue;

    const ROUTER_RETRY_ATTEMPTS = 10;

    const ROUTER_RETRY_DELAY = 5;

    /**
     * @param NetworkCreated $event
     * @return void
     * @throws \Exception
     */
    public function handle(NetworkCreated $event)
    {
        $network = $event->network;

        if (empty($network->router)) {
            $this->fail(new \Exception('Failed to load network\'s router'));
        }

        if (!$network->router->available) {
            if ($this->attempts() <= static::ROUTER_RETRY_ATTEMPTS) {
                $this->release(static::ROUTER_RETRY_DELAY);
                Log::info('Attempted to create Network but Router was not available');
                return;
            } else {
                $this->fail(new \Exception('Timed out waiting for Router to become available for network deployment'));
            }
        }

        if (empty($network->router->vpc->dhcp)) {
            $this->fail(new \Exception('Failed to load DHCP for VPC'));
        }

        try {
            $network->availabilityZone->nsxClient()->put(
                'policy/api/v1/infra/tier-1s/' . $network->router->getKey() . '/segments/' . $network->getKey(),
                [
                    'json' => [
                        'resource_type' => 'Segment',
                        'subnets' => [
                            [
                                'gateway_address' => config('defaults.network.subnets.gateway_address'),
                                'dhcp_config' => [
                                    'resource_type' => 'SegmentDhcpV4Config',
                                    'server_address' => config('defaults.network.subnets.dhcp_config.server_address'),
                                    'lease_time' => config('defaults.network.subnets.dhcp_config.lease_time'),
                                    'dns_servers' => config('defaults.network.subnets.dhcp_config.dns_servers')
                                ]
                            ]
                        ],
                        'domain_name' => config('defaults.network.domain_name'),
                        'dhcp_config_path' => '/infra/dhcp-server-configs/' . $network->router->vpc->dhcp->getKey(),
                        'advanced_config' => [
                            'connectivity' => 'ON'
                        ],
                        'tags' => [
                            [
                                'scope' => config('defaults.tag.scope'),
                                'tag' => $network->router->vpc->getKey()
                            ]
                        ]
                    ]
                ]
            );
        } catch (GuzzleException $exception) {
            $this->fail(new \Exception($exception->getResponse()->getBody()->getContents()));
        }
    }
}
