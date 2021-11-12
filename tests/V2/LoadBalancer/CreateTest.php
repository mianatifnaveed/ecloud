<?php

namespace Tests\V2\LoadBalancer;

use App\Models\V2\AvailabilityZone;
use App\Models\V2\LoadBalancer;
use App\Models\V2\LoadBalancerSpecification;
use App\Models\V2\Region;
use App\Models\V2\Task;
use App\Models\V2\Vpc;
use App\Support\Sync;
use Faker\Factory as Faker;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class CreateTest extends TestCase
{
    protected $faker;
    protected $region;
    protected $vpc;
    protected $lbs;
    protected $availabilityZone;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();

        $this->region = factory(Region::class)->create();
        $this->lbs = factory(LoadBalancerSpecification::class)->create();

        $this->vpc = Vpc::withoutEvents(function () {
            return factory(Vpc::class)->create([
                'id' => 'vpc-test',
                'name' => 'Manchester DC',
                'region_id' => $this->region->id
            ]);
        });

        $this->availabilityZone = factory(AvailabilityZone::class)->create([
            'region_id' => $this->region->id
        ]);
    }

    public function testInvalidVpcIdIsFailed()
    {
        $data = [
            'name' => 'My Load Balancer',
            'load_balancer_spec_id' => $this->lbs->id,
            'vpc_id' => $this->faker->uuid(),
            'availability_zone_id' => $this->availabilityZone->id
        ];

        $this->post(
            '/v2/load-balancers',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'The specified vpc id was not found',
                'status' => 422,
                'source' => 'vpc_id'
            ])
            ->assertResponseStatus(422);
    }

    public function testInvalidAvailabilityZoneIsFailed()
    {
        $data = [
            'name' => 'My Load Balancer',
            'load_balancer_spec_id' => $this->lbs->id,
            'vpc_id' => $this->faker->uuid(),
            'availability_zone_id' => $this->faker->uuid()
        ];

        $this->post(
            '/v2/load-balancers',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'The specified vpc id was not found',
                'status' => 422,
                'source' => 'vpc_id'
            ])
            ->assertResponseStatus(422);
    }

    public function testNotOwnedVpcIdIsFailed()
    {
        $data = [
            'name' => 'My Load Balancer',
            'load_balancer_spec_id' => $this->lbs->id,
            'vpc_id' => $this->vpc->id,
            'availability_zone_id' => $this->faker->uuid()
        ];

        $this->post(
            '/v2/load-balancers',
            $data,
            [
                'X-consumer-custom-id' => '2-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'The specified vpc id was not found',
                'status' => 422,
                'source' => 'vpc_id'
            ])
            ->assertResponseStatus(422);
    }

    public function testFailedVpcCausesFail()
    {
        // Force failure
        Model::withoutEvents(function () {
            $model = new Task([
                'id' => 'sync-test',
                'failure_reason' => 'Unit Test Failure',
                'completed' => true,
                'name' => Sync::TASK_NAME_UPDATE,
            ]);
            $model->resource()->associate($this->vpc);
            $model->save();
        });

        $data = [
            'name' => 'My Load Balancer',
            'load_balancer_spec_id' => $this->lbs->id,
            'vpc_id' => $this->vpc->id,
            'availability_zone_id' => $this->availabilityZone->id
        ];
        $this->post(
            '/v2/load-balancers',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeJson(
            [
                'title' => 'Validation Error',
                'detail' => 'The specified vpc id resource is currently in a failed state and cannot be used',
            ]
        )->assertResponseStatus(422);
    }

    public function testValidDataSucceeds()
    {
        $data = [
            'name' => 'My Load Balancer',
            'vpc_id' => $this->vpc->id,
            'load_balancer_spec_id' => $this->lbs->id,
            'availability_zone_id' => $this->availabilityZone->id
        ];
        $this->post(
            '/v2/load-balancers',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->assertResponseStatus(202);

        $resourceId = (json_decode($this->response->getContent()))->data->id;
        $resource = LoadBalancer::find($resourceId);
        $this->assertNotNull($resource);
    }
}