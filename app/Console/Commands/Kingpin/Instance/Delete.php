<?php

namespace App\Console\Commands\Kingpin\Instance;

use App\Models\V2\Instance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Class Delete
 * Delete an instance from vmware
 * @param string instanceId
 * @package App\Console\Commands\Kingpin\Instance
 */
class Delete extends Command
{
    protected $signature = 'kingpin:instance:delete {instanceId}';
    protected $description = 'Delete an instance';

    public function handle()
    {
        /** @var Instance $instance */
        $instance = Instance::find($this->argument('instanceId'));
        if (!$instance) {
            $this->alert('Failed to find instance');
            exit;
        }

        try {
            $instance->availabilityZone->kingpinService()->delete(
                '/api/v2/vpc/' . $instance->vpc_id . '/instance/' . $instance->getKey()
            );
        } catch (\Exception $e) {
            $errorMessage = 'Failed to delete instance' . $e->getMessage();
            $this->output->writeln($errorMessage);
            Log::error($errorMessage);
        }
    }
}