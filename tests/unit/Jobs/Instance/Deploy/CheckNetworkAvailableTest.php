<?php
namespace Tests\unit\Jobs\Instance\Deploy;

use App\Jobs\Instance\Deploy\CheckNetworkAvailable;
use App\Jobs\Instance\GuestShutdown;
use App\Jobs\Nsx\NetworkPolicy\SecurityGroup\UndeployCheck;
use App\Models\V2\Instance;
use App\Models\V2\Task;
use App\Support\Sync;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class CheckNetworkAvailableTest extends TestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testNetworkDoesNotExists()
    {
        $instance = Instance::withoutEvents(function () {
            return factory(Instance::class)->create([
                'id' => 'i-fail',
                'vpc_id' => $this->vpc()->id,
                'name' => 'Test Instance ' . uniqid(),
                'image_id' => $this->image()->id,
                'vcpu_cores' => 1,
                'ram_capacity' => 1024,
                'platform' => 'Linux',
                'availability_zone_id' => $this->availabilityZone()->id,
                'deploy_data' => [
                    'network_id' => 'net-notexists',
                    'volume_capacity' => 20,
                    'volume_iops' => 300,
                    'requires_floating_ip' => false,
                ]
            ]);
        });

        $this->expectException(ModelNotFoundException::class);

        dispatch(new CheckNetworkAvailable($instance));
    }

    public function testNetworkSyncFail()
    {
        $task = new Task([
            'completed' => true,
            'failure_reason' => 'Test Failure',
            'name' => Sync::TASK_NAME_UPDATE,
        ]);
        $this->network()->tasks()->save($task);

        Event::fake([JobFailed::class]);

        dispatch(new CheckNetworkAvailable($this->instance()));

        Event::assertDispatched(JobFailed::class);
    }

    public function testNetworkSyncInProgress()
    {
        $task = new Task([
            'name' => Sync::TASK_NAME_UPDATE,
            'completed' => false,
        ]);
        $this->network()->tasks()->save($task);

        Event::fake([JobFailed::class, JobProcessed::class]);

        dispatch(new CheckNetworkAvailable($this->instance()));

        Event::assertNotDispatched(JobFailed::class);
        Event::assertDispatched(JobProcessed::class, function ($event) {
            return $event->job->isReleased();
        });
    }

    public function testSuccessful()
    {
        $task = new Task([
            'name' => Sync::TASK_NAME_UPDATE,
            'completed' => true,
        ]);
        $this->network()->tasks()->save($task);

        Event::fake([JobFailed::class]);

        dispatch(new CheckNetworkAvailable($this->instance()));

        Event::assertNotDispatched(JobFailed::class);
    }
}