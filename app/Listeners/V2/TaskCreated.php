<?php

namespace App\Listeners\V2;

use App\Tasks\Task;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskCreated
{
    public function handle($event)
    {
        Log::debug(get_class($this) . ' : Started', ['id' => $event->model->id]);

        if ($event->model->job) {
            $task = $event->model;
            $taskJob = new $task->job($task);

            if ($taskJob instanceof Task) {
                $jobs = [];
                foreach ($taskJob->jobs() as $job) {
                    $jobs[] = new $job($task);
                }
                $failureReasonCallback = $taskJob->failureReasonCallback();

                Log::debug(get_class($this) . " : Dispatching batch", ["id" => $event->model->id]);
                Bus::batch([$jobs])->then(function (Batch $batch) use ($task) {
                    Log::info("Setting task completed", ['id' => $task->id, 'resource_id' => $task->resource->id]);
                    $task->completed = true;
                    $task->save();
                })->catch(function (Batch $batch, Throwable $e) use ($task, $failureReasonCallback) {
                    Log::warning("Setting task failed", ['id' => $task->id, 'resource_id' => $task->resource->id]);
                    $task->failure_reason = $failureReasonCallback($e);
                    $task->save();
                })->dispatch();
            } else {
                Log::debug(get_class($this) . " : Dispatching job", ["job" => $event->model->job]);
                dispatch($taskJob);
            }
        } else {
            Log::debug(get_class($this) . " : Skipping job dispatch, no job defined for task", ["job" => $event->model->job]);
        }

        Log::debug(get_class($this) . ' : Finished', ['id' => $event->model->id]);
    }
}
