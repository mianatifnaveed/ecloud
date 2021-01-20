<?php

namespace App\Jobs\Instance;

use App\Jobs\Job;
use App\Models\V2\Instance;
use App\Models\V2\Vpc;
use Illuminate\Support\Facades\Log;

class PowerReset extends Job
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['data' => $this->data]);

        $instance = Instance::findOrFail($this->data['instance_id']);
        $vpc = Vpc::findOrFail($this->data['vpc_id']);
        $instance->availabilityZone->kingpinService()->put(
            '/api/v2/vpc/' . $vpc->id . '/instance/' . $instance->id . '/power/reset'
        );
        $instance->setSyncCompleted();

        Log::info(get_class($this) . ' : Finished', ['data' => $this->data]);
    }
}
