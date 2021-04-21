<?php

namespace Tests\unit\Jobs\Sync\Nat;

use App\Jobs\Sync\Nat\Update;
use App\Models\V2\Nat;
use App\Models\V2\Sync;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use DatabaseMigrations;

    private $sync;
    private $nat;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testJobsBatched()
    {
        Model::withoutEvents(function() {
            $this->nat = new Nat([
                'id' => 'nat-test'
            ]);
            $this->sync = new Sync([
                'id' => 'sync-1',
            ]);
            $this->sync->resource()->associate($this->nat);
        });

        Bus::fake();
        $job = new Update($this->sync);
        $job->handle();

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() == 1 && count($batch->jobs->all()[0]) == 2;
        });
    }
}
