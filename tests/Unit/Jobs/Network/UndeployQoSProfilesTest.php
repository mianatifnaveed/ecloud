<?php

namespace Tests\Unit\Jobs\Network;

use App\Jobs\Network\UndeployQoSProfiles;
use App\Models\V2\Task;
use App\Support\Sync;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UndeployQoSProfilesTest extends TestCase
{
    protected Task $task;

    public function setUp(): void
    {
        parent::setUp();

        Model::withoutEvents(function () {
            $this->task = new Task([
                'id' => 'sync-1',
                'name' => Sync::TASK_NAME_UPDATE,
            ]);
            $this->task->resource()->associate($this->network());
            $this->task->save();
        });
    }

    public function testQoSProfileRemovedWhenExists()
    {
        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->network()->router->id . '/segments/' . $this->network()->id])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([]));
            });

        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->network()->router->id . '/segments/' . $this->network()->id . '/segment-qos-profile-binding-maps'])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'id' => 'some-existing-id',
                        ],
                    ]
                ]));
            });

        $this->nsxServiceMock()->expects('delete')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->network()->router->id . '/segments/' . $this->network()->id . '/segment-qos-profile-binding-maps/some-existing-id'])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([]));
            });

        Event::fake([JobFailed::class]);

        dispatch(new UndeployQoSProfiles($this->task));

        Event::assertNotDispatched(JobFailed::class);
    }

    public function testQoSProfileNotRemovedWhenRouterDoesntExist()
    {
        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->network()->router->id . '/segments/' . $this->network()->id])
            ->andThrow(
                new ClientException('Not Found', new Request('GET', 'test'), new Response(404))
            );
        $this->nsxServiceMock()->shouldNotReceive('delete');

        Event::fake([JobFailed::class]);

        dispatch(new UndeployQoSProfiles($this->task));

        Event::assertNotDispatched(JobFailed::class);
    }

    public function testQoSProfileNotRemovedWhenDoesntExist()
    {
        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->network()->router->id . '/segments/' . $this->network()->id])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([]));
            });

        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->network()->router->id . '/segments/' . $this->network()->id . '/segment-qos-profile-binding-maps'])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => []
                ]));
            });

        $this->nsxServiceMock()->shouldNotReceive('delete');

        Event::fake([JobFailed::class]);

        dispatch(new UndeployQoSProfiles($this->task));

        Event::assertNotDispatched(JobFailed::class);
    }
}
