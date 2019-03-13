<?php

namespace Tests\Appliances\ApplianceParameters;

use App\Models\V1\ApplianceParameters;
use Laravel\Lumen\Testing\DatabaseMigrations;

use Ramsey\Uuid\Uuid;

use Tests\ApplianceTestCase;

class GetTest extends ApplianceTestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test GET Appliance parameters collection
     */
    public function testValidCollection()
    {
        $this->get('/v1/appliance-parameters', $this->validReadHeaders);

        $this->assertResponseStatus(200) && $this->seeJson([
            'total' => ApplianceParameters::query()->count()
        ]);
    }

    /**
     * Test GET Appliance parameter Item
     */
    public function testValidItem()
    {
        $parameter = $this->appliances[0]->getLatestVersion()->parameters[0];

        $this->get('/v1/appliance-parameters/' . $parameter->uuid, $this->validReadHeaders);

        $this->assertResponseStatus(200);
    }
}
