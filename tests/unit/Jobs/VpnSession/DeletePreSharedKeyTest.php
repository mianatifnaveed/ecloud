<?php

namespace Tests\unit\Jobs\VpnSession;

use App\Jobs\VpnSession\DeletePreSharedKey;
use App\Models\V2\Credential;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Mocks\Resources\VpnSessionMock;
use Tests\TestCase;

class DeletePreSharedKeyTest extends TestCase
{
    use VpnSessionMock;

    public function testSuccessful()
    {
        Credential::withoutEvents(function () {
            $credential = factory(Credential::class)->create([
                'id' => 'cred-test',
                'name' => 'Pre-shared Key for VPN Session ' . $this->vpnSession()->id,
                'host' => null,
                'username' => 'PSK',
                'password' => Str::random(32),
                'port' => null,
                'is_hidden' => false,
            ]);
            $this->vpnSession()->credentials()->save($credential);
        });

        $this->assertTrue($this->vpnSession()->credentials()->where('username', 'PSK')->exists());

        dispatch(new DeletePreSharedKey($this->vpnSession()));

        $this->assertFalse($this->vpnSession()->credentials()->where('username', 'PSK')->exists());

        Event::assertNotDispatched(JobFailed::class);
    }
}