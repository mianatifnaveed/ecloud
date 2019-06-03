<?php

namespace Tests\Appliances\ApplianceParameters;

use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\ApplianceTestCase;


class DeleteTest extends ApplianceTestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testDeleteApplianceParameter()
    {
        $parameter = $this->appliances[0]->getLatestVersion()->parameters[0];

        $this->assertNull($parameter->deleted_at);

        $this->json('DELETE', '/v1/appliance-parameters/' . $parameter->uuid , [], $this->validWriteHeaders);

        $this->assertResponseStatus(204);

        $parameter->refresh();

        $this->assertNotNull($parameter->deleted_at);
    }


    public function testDeleteApplianceParameterUnauthorised()
    {
        $parameter = $this->appliances[0]->getLatestVersion()->parameters[0];

        $this->json('DELETE', '/v1/appliance-parameters/' . $parameter->uuid , [], $this->validReadHeaders);

        $this->assertResponseStatus(403);
    }
}