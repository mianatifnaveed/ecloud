<?php

namespace App\Listeners\V2\Volume;

use App\Events\V2\Volume\Deleted;
use App\Events\V2\Volume\Updated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class Delete implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @param Updated $event
     * @return void
     * @throws \Exception
     */
    public function handle(Deleted $event)
    {
        Log::info(get_class($this).' : Started', ['event' => $event]);
        // Get the volume along with the number of instances that are using the volume
        $volume = ($event->model)->withCount(['instances'])->first();
        // if there are no instances with this volume attached then it's safe to delete
        if ($volume->instances_count == 0) {
            $endpoint = '/api/v1/vpc/' . $volume->vpc->id . '/volume/' . $volume->vmware_uuid;
            $volume->availabilityZone->kingpinService()->delete($endpoint);
            Log::info('Volume ' . $volume->getKey() . ' (' . $volume->vmware_uuid . ') deleted.');
        }
        Log::info(get_class($this).' : Finished', ['event' => $event]);
    }
}
