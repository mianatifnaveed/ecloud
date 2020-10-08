<?php

namespace Tests\V2\Instances;

use App\Events\V2\InstanceDeleteEvent;
use App\Listeners\V2\InstanceUndeploy;
use App\Listeners\V2\InstanceVolumeDelete;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\Instance;
use App\Models\V2\Region;
use App\Models\V2\Volume;
use App\Models\V2\Vpc;
use App\Services\V2\KingpinService;
use Faker\Factory as Faker;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class DeleteEventTest extends TestCase
{
    use DatabaseMigrations;

    protected \Faker\Generator $faker;
    protected AvailabilityZone $availability_zone;
    protected Instance $instance;
    protected Region $region;
    protected $volumes;
    protected Vpc $vpc;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->region = factory(Region::class)->create();
        $this->availability_zone = factory(AvailabilityZone::class)->create([
            'region_id' => $this->region->getKey()
        ]);
        Vpc::flushEventListeners();
        $this->vpc = factory(Vpc::class)->create([
            'region_id' => $this->region->getKey()
        ]);
        $this->instance = factory(Instance::class)->create([
            'vpc_id' => $this->vpc->getKey(),
            'name' => 'GetTest Default',
        ]);
        $this->volumes = factory(Volume::class, 3)->make([
            'availability_zone_id' => $this->availability_zone->getKey(),
            'vpc_id' => $this->vpc->getKey(),
        ])
            ->each(function ($volume) {
                $volume->vmware_uuid = $this->faker->uuid;
                $volume->save();
                $volume->instances()->attach($this->instance);
            });

        $mockKingpinService = \Mockery::mock(new KingpinService(new Client()));
        $mockKingpinService->shouldReceive('delete')
            ->withArgs(['/api/v2/vpc/'.$this->vpc->getKey().'/instance/'.$this->instance->getKey()])
            ->andReturn(
                new Response(200)
            );

        app()->bind(KingpinService::class, function () use ($mockKingpinService) {
            return $mockKingpinService;
        });
    }

    public function testInstanceDeleteEventFired()
    {
        Event::fake();

        $this->delete(
            '/v2/instances/' . $this->instance->getKey(),
            [],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups'    => 'ecloud.write',
            ]
        )
            ->assertResponseStatus(204);

        Event::assertDispatched(InstanceDeleteEvent::class, function ($event) {
            return $event->instance->id === $this->instance->getKey();
        });
    }

    public function testDeleteInstanceListener()
    {
        $event = new InstanceDeleteEvent($this->instance);
        /** @var InstanceUndeploy $listener */
        $listener = \Mockery::mock(InstanceUndeploy::class)
            ->makePartial();
        $listener->handle($event);
    }

    public function testDeleteVolumeListener()
    {
        $volumeCount = $this->instance->volumes()->count();
        $this->instance->delete();
        $event = new InstanceDeleteEvent($this->instance);
        $listener = \Mockery::mock(InstanceVolumeDelete::class)
            ->makePartial();
        $listener->shouldReceive('attempts')->andReturn(1);
        $listener->handle($event);
        $this->assertEquals(3, $volumeCount);
        $this->assertEquals(0, $this->instance->volumes()->count());
    }
}
