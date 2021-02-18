<?php
namespace Tests\unit\Rules;

use App\Models\V2\NetworkPolicy;
use App\Models\V2\Network;
use App\Rules\V2\NetworkHasNoPolicy;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class NetworkHasNoAclTest extends TestCase
{
    use DatabaseMigrations;

    protected NetworkHasNoPolicy $rule;
    protected Network $network;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new NetworkHasNoPolicy();
        $this->availabilityZone();
        $this->network = factory(Network::class)->create([
            'router_id' => $this->router()->id,
        ]);
    }

    public function testRulePasses()
    {
        $this->assertTrue($this->rule->passes('', $this->network->id));
    }

    public function testRuleFails()
    {
        factory(NetworkPolicy::class)->create([
            'network_id' => $this->network->id,
        ]);
        $this->assertFalse($this->rule->passes('', $this->network->id));
    }
}