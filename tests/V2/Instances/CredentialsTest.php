<?php

namespace Tests\V2\Instances;

use App\Models\V2\Credential;
use App\Models\V2\Instance;
use App\Models\V2\Vpc;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseMigrations;

class CredentialsTest extends TestCase
{
    use DatabaseMigrations;

    protected $faker;

    protected $vpc;

    protected $instance;

    protected $credential;

    public function setUp(): void
    {
        parent::setUp();
        Vpc::flushEventListeners();
        $this->vpc = factory(Vpc::class)->create([
            'name' => 'Manchester VPC',
        ]);
        $this->instance = factory(Instance::class)->create([
            'vpc_id' => $this->vpc->getKey(),
        ]);

        $this->credential = factory(Credential::class)->create([
            'resource_id' => $this->instance->getKey()
        ]);
    }

    public function testGetCredentials()
    {
        $this->get(
            '/v2/instances/' . $this->instance->getKey() . '/credentials',
            [
                'X-consumer-custom-id' => '1-0',
                'X-consumer-groups' => 'ecloud.read',
            ]
        )
            ->seeJson(
                collect($this->credential)
                    ->except(['created_at', 'updated_at'])
                    ->toArray()
            )
            ->assertResponseStatus(200);
    }
}