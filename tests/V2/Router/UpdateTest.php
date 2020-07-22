<?php

namespace Tests\V2\Router;

use App\Models\V2\Router;
use Faker\Factory as Faker;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseMigrations;

class UpdateTest extends TestCase
{
    use DatabaseMigrations;

    protected $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
    }

    public function testNonAdminIsDenied()
    {
        $zone = $this->createRouter();
        $data = [
            'name'       => 'Manchester Router 2',
        ];
        $this->patch(
            '/v2/routers/' . $zone->getKey(),
            $data,
            [
                'X-consumer-custom-id' => '1-1',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title'  => 'Unauthorised',
                'detail' => 'Unauthorised',
                'status' => 401,
            ])
            ->assertResponseStatus(401);
    }

    public function testNullNameIsDenied()
    {
        $zone = $this->createRouter();
        $data = [
            'name'       => '',
        ];
        $this->patch(
            '/v2/routers/' . $zone->getKey(),
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title'  => 'Validation Error',
                'detail' => 'The name field, when specified, cannot be null',
                'status' => 422,
                'source' => 'name'
            ])
            ->assertResponseStatus(422);
    }

    public function testValidDataIsSuccessful()
    {
        $zone = $this->createRouter();
        $data = [
            'name'       => 'Manchester Router 2',
        ];
        $this->patch(
            '/v2/routers/' . $zone->getKey(),
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->assertResponseStatus(200);

        $routerItem = Router::findOrFail($zone->getKey());
        $this->assertEquals($data['name'], $routerItem->name);
    }

    /**
     * Create Router
     * @return \App\Models\V2\Router
     */
    public function createRouter(): Router
    {
        $router = factory(Router::class, 1)->create()->first();
        $router->save();
        $router->refresh();
        return $router;
    }

}