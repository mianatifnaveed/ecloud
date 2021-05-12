<?php

namespace App\Http\Controllers\V2;

use App\Exceptions\V2\TaskException;
use App\Http\Requests\V2\Volume\AttachRequest;
use App\Http\Requests\V2\Volume\DetachRequest;
use App\Http\Requests\V2\Volume\CreateRequest;
use App\Http\Requests\V2\Volume\UpdateRequest;
use App\Models\V2\Instance;
use App\Models\V2\Task;
use App\Models\V2\Volume;
use App\Models\V2\Vpc;
use App\Resources\V2\InstanceResource;
use App\Resources\V2\TaskResource;
use App\Resources\V2\VolumeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use UKFast\DB\Ditto\QueryTransformer;

class VolumeController extends BaseController
{
    public function index(Request $request)
    {
        if ($request->hasAny([
            'attached',
            'attached:eq',
            'attached:neq',
        ])) {
            if ($request->has('attached') || $request->has('attached:eq')) {
                if ($request->has('attached')) {
                    $attached = filter_var($request->get('attached'), FILTER_VALIDATE_BOOLEAN);
                    $request->query->remove('attached');
                } else {
                    $attached = filter_var($request->get('attached:eq'), FILTER_VALIDATE_BOOLEAN);
                    $request->query->remove('attached:eq');
                }
            } elseif ($request->has('attached:neq')) {
                $attached = !filter_var($request->get('attached:neq'), FILTER_VALIDATE_BOOLEAN);
                $request->query->remove('attached:neq');
            }

            if ($attached) {
                $collection = Volume::forUser($request->user())->has('instances', '>', 0);
            } else {
                $collection = Volume::forUser($request->user())->has('instances', '=', 0);
            }
        } else {
            $collection = Volume::forUser($request->user());
        }

        (new QueryTransformer($request))
            ->config(Volume::class)
            ->transform($collection);

        return VolumeResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    public function show(Request $request, string $volumeId)
    {
        return new VolumeResource(
            Volume::forUser($request->user())->findOrFail($volumeId)
        );
    }

    public function store(CreateRequest $request)
    {
        if ($request->has('availability_zone_id')) {
            $availabilityZone = Vpc::forUser(Auth::user())
                ->findOrFail($request->vpc_id)
                ->region
                ->availabilityZones
                ->first(function ($availabilityZone) use ($request) {
                    return $availabilityZone->id == $request->availability_zone_id;
                });

            if (!$availabilityZone) {
                return response()->json([
                    'errors' => [
                        'title' => 'Not Found',
                        'detail' => 'The specified availability zone is not available to that VPC',
                        'status' => 404,
                        'source' => 'availability_zone_id'
                    ]
                ], 404);
            }
        }

        $model = app()->make(Volume::class);
        $model->fill($request->only([
            'name',
            'vpc_id',
            'availability_zone_id',
            'capacity',
            'iops',
        ]));

        $task = $model->syncSave();

        return $this->responseIdMeta($request, $model->id, 202, $task->id);
    }

    public function update(UpdateRequest $request, string $volumeId)
    {
        $volume = Volume::forUser(Auth::user())->findOrFail($volumeId);
        $only = ['name', 'capacity', 'iops'];
        if ($this->isAdmin) {
            $only[] = 'vmware_uuid';
        }
        $volume->fill($request->only($only));
        $task = $volume->syncSave();

        return $this->responseIdMeta($request, $volume->id, 202, $task->id);
    }

    public function destroy(Request $request, string $volumeId)
    {
        $volume = Volume::forUser($request->user())->findOrFail($volumeId);

        $task = $volume->syncDelete();

        return $this->responseTaskId($task->id);
    }

    public function attach(AttachRequest $request, string $volumeId)
    {
        $volume = Volume::forUser(Auth::user())->findOrFail($volumeId);
        $instance = Instance::forUser(Auth::user())->findOrFail($request->get('instance_id'));

        $volume->withTaskLock(function ($volume) use ($instance) {
            $instance->withTaskLock(function ($instance) use ($volume) {
                if (!$instance->canCreateTask() || !$volume->canCreateTask()) {
                    throw new TaskException();
                }

                $task = $volume->createTask('volume_attach', \App\Jobs\Tasks\Volume\VolumeAttach::class, ['instance_id' => $instance->id]);
                $instance->createTask('volume_attach_wait', \App\Jobs\Tasks\AwaitTask::class, ['task_id' => $task->id]);
            });
        });

        return response('', 202);
    }

    public function detach(DetachRequest $request, string $volumeId)
    {
        $volume = Volume::forUser(Auth::user())->findOrFail($volumeId);
        $instance = Instance::forUser(Auth::user())->findOrFail($request->get('instance_id'));

        $volume->withTaskLock(function ($volume) use ($instance) {
            $instance->withTaskLock(function ($instance) use ($volume) {
                if (!$instance->canCreateTask() || !$volume->canCreateTask()) {
                    throw new TaskException();
                }

                $task = $volume->createTask('volume_detach', \App\Jobs\Tasks\Volume\VolumeDetach::class, ['instance_id' => $instance->id]);
                $instance->createTask('volume_detach_wait', \App\Jobs\Tasks\AwaitTask::class, ['task_id' => $task->id]);
            });
        });

        return response('', 202);
    }

    public function instances(Request $request, QueryTransformer $queryTransformer, string $volumeId)
    {
        $collection = Volume::forUser($request->user())->findOrFail($volumeId)->instances();
        $queryTransformer->config(Instance::class)
            ->transform($collection);

        return InstanceResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    public function tasks(Request $request, QueryTransformer $queryTransformer, string $volumeId)
    {
        $collection = Volume::forUser($request->user())->findOrFail($volumeId)->tasks();
        $queryTransformer->config(Task::class)
            ->transform($collection);

        return TaskResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }
}
