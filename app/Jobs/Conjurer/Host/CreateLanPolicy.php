<?php

namespace App\Jobs\Conjurer\Host;

use App\Jobs\Job;
use App\Models\V2\Host;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class CreateLanPolicy extends Job
{
    private $model;

    public function __construct(Host $model)
    {
        $this->model = $model;
    }

    /**
     * @return bool
     */
    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->model->id]);

        $host = $this->model;
        $vpc = $host->hostGroup->vpc;
        $availabilityZone = $host->hostGroup->availabilityZone;

        if (empty($availabilityZone->ucs_compute_name)) {
            $message = 'Failed to load UCS compute name for availability zone ' . $availabilityZone->id;
            Log::error($message);
            $this->fail(new \Exception($message));
            return false;
        }

        // Check whether a LAN connectivity policy exists on the UCS for the VPC
        try {
            $availabilityZone->conjurerService()->get('/api/v2/compute/' . $availabilityZone->ucs_compute_name . '/vpc/' . $vpc->id);
        } catch (RequestException $exception) {
            if ($exception->getCode() != 404) {
                throw $exception;
            }

            $availabilityZone->conjurerService()->post(
                '/api/v2/compute/' . $availabilityZone->ucs_compute_name . '/vpc',
                [
                    'json' => [
                        'vpcId' => $vpc->id,
                    ],
                ]
            );
            Log::info(get_class($this) . ' : LAN policy created on UCS for VPC', ['id' => $this->model->id]);
        }

        Log::info(get_class($this) . ' : Finished', ['id' => $this->model->id]);
    }

    public function failed($exception)
    {
        $message = ($exception instanceof RequestException && $exception->hasResponse()) ?
            $exception->getResponse()->getBody()->getContents() :
            $exception->getMessage();
        $this->model->setSyncFailureReason($message);
    }
}