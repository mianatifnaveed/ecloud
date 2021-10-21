<?php

namespace Tests\V2\Vip;

use App\Events\V2\Task\Created;
use App\Models\V2\Vip;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testValidDataIsSuccessful()
    {
        Event::fake(Created::class);

        $this->patch('/v2/vips/' . $this->vip()->id,
            [
                'loadbalancer_id' => $this->loadbalancer()->id,
                'network_id' => $this->network()->id
            ],
            [
                'x-consumer-custom-id' => '0-0',
                'x-consumer-groups' => 'ecloud.write'
            ]
        )->assertResponseStatus(202);
        $this->assertEquals($this->loadbalancer()->id, Vip::findOrFail($this->vip()->id)->loadbalancer_id);
    }
}
