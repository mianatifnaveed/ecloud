<?php

namespace Tests\VirtualMachines;

use App\Models\V1\Pod;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseMigrations;
use App\Models\V1\VirtualMachine;

class ConsoleSessionTest extends TestCase
{
    use DatabaseMigrations;

    public function testValidRequest()
    {
        return $this->markTestSkipped('WIP');

        app()->bind('App\Services\Kingpin\V1\KingpinService', function ($k) {

        });

        $pod = factory(Pod::class)->create()->first();
        $vm = factory(VirtualMachine::class)->create()->first();
        $vm->pod = $pod;

        $consoleResource = \App\Models\V1\Pod\Resource\Console::create([
            'token' => 'XXXXXXXXXXXXXXXXXXXXXXX',
            'url' => 'https://www.testdomain.com',
            'console_url' => 'https://www.testdomain.com/console',
        ]);
        $pod->addResource($consoleResource);

        $this->get('/v1/vms/999/console-session', [
            'X-consumer-custom-id' => '1-1',
            'X-consumer-groups' => 'ecloud.read',
        ]);

        $this->assertResponseStatus(404);
    }
}
