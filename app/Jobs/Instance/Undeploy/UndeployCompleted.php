<?php

namespace App\Jobs\Instance\Undeploy;

use App\Jobs\Job;
use App\Models\V2\Instance;
use Illuminate\Support\Facades\Log;

class UndeployCompleted extends Job
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['data' => $this->data]);

        Instance::withTrashed()->findOrFail($this->data['instance_id'])->setSyncCompleted();

        Log::info(get_class($this) . ' : Finished', ['data' => $this->data]);
    }
}