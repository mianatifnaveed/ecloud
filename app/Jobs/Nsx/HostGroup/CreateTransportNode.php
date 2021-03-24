<?php

namespace App\Jobs\Nsx\HostGroup;

use App\Jobs\Job;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\HostGroup;
use App\Models\V2\Vpc;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class CreateTransportNode extends Job
{
    private $model;

    public function __construct(HostGroup $model)
    {
        $this->model = $model;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->model->id]);

        $hostGroup = $this->model;

        $transportNodeProfiles = $this->getTransportNodeProfiles($hostGroup->availabilityZone);
        if (!$transportNodeProfiles) {
            $this->fail(new \Exception('Failed to get TransportNodeProfiles'));
            return false;
        }

        $transportNodeProfileDisplayName =  'tnp-' . $this->model->id;
        $exists = collect($transportNodeProfiles->results)->filter(function ($result) use (
            $transportNodeProfileDisplayName
        ) {
            return ($result->display_name === $transportNodeProfileDisplayName);
        })->count();
        if ($exists) {
            Log::info(get_class($this) . ' : Skipped', [
                'id' => $this->model->id,
            ]);
            return true;
        }

        $networkSwitch = $this->getNetworkSwitchDetails($hostGroup->availabilityZone, $hostGroup->vpc);
        if (!$networkSwitch) {
            $this->fail(new \Exception('Failed to get NetworkSwitch'));
            return false;
        }

        $transportZones = $this->getTransportZones($hostGroup->availabilityZone);
        if (!$transportZones || !isset($transportZones->results) || !count($transportZones->results)) {
            $this->fail(new \Exception('Failed to get TransportZones'));
            return false;
        }
        $transportZone = collect($transportZones->results)->first();

        $uplinkHostSwitchProfiles = $this->getUplinkHostSwitchProfiles($hostGroup->availabilityZone);
        if (!$uplinkHostSwitchProfiles || !isset($uplinkHostSwitchProfiles->results) || !count($uplinkHostSwitchProfiles->results)) {
            $this->fail(new \Exception('Failed to get UplinkHostSwitchProfiles'));
            return false;
        }
        $uplinkHostSwitchProfile = collect($uplinkHostSwitchProfiles->results)->first();

        $hostGroup->availabilityZone->nsxService()->post(
            '/api/v1/transport-node-profiles',
            [
                'json' => [
                    'resource_type' => 'TransportNodeProfile',
                    'display_name' => $transportNodeProfileDisplayName,
                    'description' => 'API created Transport Node Profile',
                    'host_switch_spec' => [
                        'resource_type' => 'StandardHostSwitchSpec',
                        'host_switches' => [
                            [
                                'host_switch_name' => $hostGroup->vpc->id,
                                'host_switch_id' => $networkSwitch->uuid,
                                'host_switch_mode' => 'STANDARD',
                                'host_switch_type' => 'VDS',
                                'host_switch_profile_ids' => [
                                    [
                                        'value' => $uplinkHostSwitchProfile->id,
                                        'key' => 'UplinkHostSwitchProfile'
                                    ]
                                ],
                                'transport_zone_endpoints' => [
                                    [
                                        'transport_zone_id' => $transportZone->id,
                                        'transport_zone_profile_ids' => $transportZone->transport_zone_profile_ids,
                                    ]
                                ],
                                'uplinks' => [
                                    [
                                        'vds_uplink_name' => 'dvUplink1',
                                        'uplink_name' => 'Uplink 1'
                                    ],
                                    [
                                        'vds_uplink_name' => 'dvUplink2',
                                        'uplink_name' => 'Uplink 2'
                                    ]
                                ],
                                'ip_assignment_spec' => [
                                    'resource_type' => 'AssignedByDhcp'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );

        Log::info(get_class($this) . ' : Finished', ['id' => $this->model->id]);
    }

    private function getTransportNodeProfiles(AvailabilityZone $availabilityZone): ?\stdClass
    {
        return json_decode(
            $availabilityZone->nsxService()
                ->get('/api/v1/transport-node-profiles')
                ->getBody()
                ->getContents()
        );
    }

    private function getNetworkSwitchDetails(AvailabilityZone $availabilityZone, Vpc $vpc): ?\stdClass
    {
        return json_decode(
            $availabilityZone->kingpinService()
                ->get('/api/v2/vpc/' . $vpc->id . '/network/switch')
                ->getBody()
                ->getContents()
        );
    }

    private function getTransportZones(AvailabilityZone $availabilityZone): ?\stdClass
    {
        return json_decode(
            $availabilityZone->nsxService()
                ->get('/api/v1/search/query?query=resource_type:TransportZone%20AND%20tags.scope:ukfast%20AND%20tags.tag:default-overlay-tz')
                ->getBody()
                ->getContents()
        );
    }

    private function getUplinkHostSwitchProfiles(AvailabilityZone $availabilityZone): ?\stdClass
    {
        return json_decode(
            $availabilityZone->nsxService()
                ->get('/api/v1/search/query?query=resource_type:UplinkHostSwitchProfile%20AND%20tags.scope:ukfast%20AND%20tags.tag:default-uplink-profile')
                ->getBody()
                ->getContents()
        );
    }

    public function failed($exception)
    {
        $message = $exception->getMessage();
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $json = json_decode($exception->getResponse()->getBody()->getContents());
            Log::error('Request Exception', [
                'response_json' => $json,
                'exception' => $exception,
            ]);
        }
        $this->model->setSyncFailureReason($message);
    }
}