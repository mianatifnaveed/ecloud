<?php

namespace Tests\unit\Jobs\Router;

use App\Jobs\Network\CreateManagementNetwork;
use App\Jobs\Router\CreateManagementFirewallPolicies;
use App\Listeners\V2\TaskCreated;
use App\Models\V2\FirewallPolicy;
use App\Models\V2\Network;
use App\Models\V2\Router;
use App\Models\V2\Task;
use App\Support\Sync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateManagementFirewallPoliciesTest extends TestCase
{
    protected Task $task;
    protected Router $managementRouter;
    protected Network $managementNetwork;

    protected function setUp(): void
    {
        parent::setUp();
        $this->managementRouter = factory(Router::class)->create([
            'id' => 'rtr-mgmt',
            'vpc_id' => $this->vpc()->id,
            'availability_zone_id' => $this->availabilityZone()->id,
            'router_throughput_id' => $this->routerThroughput()->id,
        ]);
        $this->managementNetwork = factory(Network::class)->create([
            'id' => 'net-mgmt',
            'name' => 'Manchester Network',
            'subnet' => '10.0.0.0/24',
            'router_id' => $this->router()->id
        ]);
    }

    public function testNoPoliciesAddedIfNoManagementNetwork()
    {
        Model::withoutEvents(function () {
            $this->task = new Task([
                'id' => 'sync-1',
                'name' => Sync::TASK_NAME_UPDATE,
            ]);
            $this->router()->setAttribute('is_hidden', true)->saveQuietly();
            $this->task->resource()->associate($this->router());
        });
        Event::fake(TaskCreated::class);
        Bus::fake();
        $job = new CreateManagementFirewallPolicies($this->task);
        $this->assertNull($job->handle());
    }

    public function testAddFirewallPolicies()
    {
        Model::withoutEvents(function () {
            $this->task = new Task([
                'id' => 'sync-1',
                'name' => Sync::TASK_NAME_UPDATE,
            ]);
            $this->router()->setAttribute('is_hidden', true)->saveQuietly();
            $this->task->resource()->associate($this->router());
            $this->task->setAttribute('data', [
                'management_router_id' => $this->managementRouter->id,
                'management_network_id' => $this->managementNetwork->id,
            ])->saveQuietly();
        });
        Event::fake(TaskCreated::class);
        Bus::fake();

        $job = new CreateManagementFirewallPolicies($this->task);
        $job->handle();

        $firewallPolicy = FirewallPolicy::where('router_id', '=', $this->managementRouter->id)->first();
        $this->assertNotEmpty($firewallPolicy);
        $this->assertEquals(4222, $firewallPolicy->firewallRules->first()->firewallRulePorts->first()->source);
    }
}
