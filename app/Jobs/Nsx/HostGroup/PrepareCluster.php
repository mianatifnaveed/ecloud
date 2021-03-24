<?php

namespace App\Jobs\Nsx\HostGroup;

use App\Jobs\Job;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\HostGroup;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class PrepareCluster extends Job
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

        $transportNodeCollections = $this->getTransportNodeCollections($hostGroup->availabilityZone);
        if (!$transportNodeCollections || !count($transportNodeCollections->results)) {
            $this->fail(new \Exception('Failed to get TransportNodeCollections'));
            return false;
        }

        $transportNodeCollectionDisplayName = 'tnc-' . $this->model->id;
        $exists = collect($transportNodeCollections->results)->filter(function ($result) use (
            $transportNodeCollectionDisplayName
        ) {
            return ($result->display_name === $transportNodeCollectionDisplayName);
        })->count();
        if ($exists) {
            Log::info(get_class($this) . ' : Skipped', [
                'id' => $this->model->id,
            ]);
            return true;
        }

        $transportNodeProfiles = $this->getTransportNodeProfiles($hostGroup->availabilityZone, $hostGroup);
        if (!$transportNodeProfiles || !count($transportNodeProfiles->results)) {
            $this->fail(new \Exception('Failed to get TransportNodeProfiles'));
            return false;
        }
        $transportNodeProfile = collect($transportNodeProfiles->results)->first();

        $computeCollections = $this->getHostGroupComputeCollections($hostGroup->availabilityZone, $hostGroup);
        if (!$computeCollections || !count($computeCollections->results)) {
            $this->fail(new \Exception('Failed to get ComputeCollections'));
            return false;
        }
        $computeCollection = collect($computeCollections->results)->first();

        $hostGroup->availabilityZone->nsxService()->post(
            '/api/v1/transport-node-collections',
            [
                'json' => [
                    'resource_type' => 'TransportNodeCollection',
                    'display_name' => $transportNodeCollectionDisplayName,
                    'description' => 'API created Transport Node Collection',
                    'compute_collection_id' => $computeCollection->external_id,
                    'transport_node_profile_id' => $transportNodeProfile->id,
                ]
            ]
        );

        Log::info(get_class($this) . ' : Finished', ['id' => $this->model->id]);
    }

    private function getTransportNodeCollections(AvailabilityZone $availabilityZone): ?\stdClass
    {
        return json_decode(
            $availabilityZone->nsxService()
                ->get('/api/v1/transport-node-collections')
                ->getBody()
                ->getContents()
        );
    }

    private function getTransportNodeProfiles(AvailabilityZone $availabilityZone, HostGroup $hostGroup): ?\stdClass
    {
        return json_decode(
            $availabilityZone->nsxService()
                ->get('/api/v1/search/query?query=resource_type:TransportNodeProfile%20AND%20display_name:tnp-' . $hostGroup->id)
                ->getBody()
                ->getContents()
        );
    }

    private function getHostGroupComputeCollections(
        AvailabilityZone $availabilityZone,
        HostGroup $hostGroup
    ): ?\stdClass {
        return json_decode(
            $availabilityZone->nsxService()
                ->get('/api/v1/fabric/compute-collections?origin_type=VC_Cluster&display_name=' . $hostGroup->id)
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