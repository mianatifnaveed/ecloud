<?php

namespace App\Jobs\Instance\Deploy;

use App\Jobs\Job;
use Illuminate\Support\Facades\Log;

class PrepareOsUsers extends Job
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @see https://gitlab.devops.ukfast.co.uk/ukfast/api.ukfast/ecloud/-/issues/330
     */
    public function handle()
    {
        Log::info('PrepareOsUsers');
    }
}