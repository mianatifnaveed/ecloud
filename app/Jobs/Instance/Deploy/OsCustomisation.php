<?php

namespace App\Jobs\Instance\Deploy;

use App\Jobs\Job;
use App\Models\V2\Credential;
use App\Models\V2\Instance;
use App\Models\V2\Vpc;
use App\Services\V2\PasswordService;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class OsCustomisation extends Job
{
    use Batchable;

    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @param PasswordService $passwordService
     * @see https://gitlab.devops.ukfast.co.uk/ukfast/api.ukfast/ecloud/-/issues/331
     */
    public function handle(PasswordService $passwordService)
    {
        Log::info(get_class($this) . ' : Started', ['data' => $this->data]);

        $instance = Instance::findOrFail($this->data['instance_id']);
        $vpc = Vpc::findOrFail($this->data['vpc_id']);

        $username = ($instance->platform == 'Linux') ? 'root' : 'graphite.rack';
        $credential = Credential::create([
            'name' => $username,
            'resource_id' => $instance->id,
            'username' => $username,
        ]);
        $credential->password = $passwordService->generate();
        $credential->save();

        $instance->availabilityZone->kingpinService()->put(
            '/api/v2/vpc/' . $vpc->id . '/instance/' . $instance->id . '/oscustomization',
            [
                'json' => [
                    'platform' => $instance->platform,
                    'password' => $credential->password,
                    'hostname' => $instance->id,
                ],
            ]
        );

        Log::info(get_class($this) . ' : Finished', ['data' => $this->data]);
    }
}
