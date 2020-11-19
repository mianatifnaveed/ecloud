<?php

namespace Tests\unit\Listeners\Nat;

use App\Models\V2\AvailabilityZone;
use App\Models\V2\FloatingIp;
use App\Models\V2\Instance;
use App\Models\V2\Nat;
use App\Models\V2\Network;
use App\Models\V2\Nic;
use App\Models\V2\Region;
use App\Models\V2\Router;
use App\Models\V2\Vpc;
use App\Services\V2\NsxService;
use Faker\Factory as Faker;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class DeployTest extends TestCase
{
    use DatabaseMigrations;

    protected \Faker\Generator $faker;
    protected $region;
    protected $availability_zone;
    protected $vpc;
    protected $router;
    protected $network;
    protected $instance;
    protected $floating_ip;
    protected $nic;
    protected $nat;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->region = factory(Region::class)->create();
        $this->availability_zone = factory(AvailabilityZone::class)->create([
            'region_id' => $this->region->id,
        ]);
        $this->vpc = factory(Vpc::class)->create([
            'region_id' => $this->region->id,
        ]);
        $this->router = factory(Router::class)->create([
            'availability_zone_id' => $this->availability_zone->id,
        ]);
        $this->network = factory(Network::class)->create([
            'router_id' => $this->router->id,
        ]);
        $this->instance = factory(Instance::class)->create([
            'availability_zone_id' => $this->availability_zone->id,
            'vpc_id' => $this->vpc->id,
        ]);
        $this->floating_ip = factory(FloatingIp::class)->create([
            'ip_address' => $this->faker->ipv4,
        ]);
        $this->nic = factory(Nic::class)->create([
            'instance_id' => $this->instance->id,
            'network_id' => $this->network->id,
            'ip_address' => $this->faker->ipv4,
        ]);

        Model::withoutEvents(function () {
            $this->nat = factory(Nat::class)->create([
                'id' => 'nat-123456',
                'destination_id' => $this->floating_ip->id,
                'destinationable_type' => FloatingIp::class,
                'translated_id' => $this->nic->id,
                'translatedable_type' => Nic::class,
            ]);
        });
    }

    public function testUpdatingNatWithoutEditingRulesDoesNotDeploy()
    {
        $listener = \Mockery::mock();
        $listener->shouldReceive('patch')
            ->never();
        app()->bind(NsxService::class, function () use ($listener) {
            return $listener;
        });
        $this->nat->save();
    }

    public function testUpdatingNatRemovesOldRuleAndAddsNewRule()
    {
        $newFloatingIp = factory(FloatingIp::class)->create([
            'ip_address' => $this->faker->ipv4,
        ]);
        $this->nat->destination_id = $newFloatingIp->id;

        $mockNsxService = \Mockery::mock();
        $mockNsxService->shouldReceive('patch')
            ->once()
            ->andReturn(new Response(200)); // TODO :- Build on this
        app()->bind(NsxService::class, function () use ($mockNsxService) {
            return $mockNsxService;
        });
        $listener = \Mockery::mock(\App\Listeners\V2\Nat\Deploy::class)->makePartial();
        $listener->handle(new \App\Events\V2\Nat\Saved($this->nat));

        $this->nat->save();
        Event::assertDispatched(\App\Events\V2\Nat\Saved::class, function ($event) {
            return $event->model->id === $this->nat->id;
        });
    }
}
