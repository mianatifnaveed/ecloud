<?php
namespace Tests\V2\Image;

use App\Models\V2\Image;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use UKFast\Api\Auth\Consumer;

class UpdateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->be(new Consumer(1, [config('app.name') . '.read', config('app.name') . '.write']));
    }

    public function testUpdatePublicResourceAdminSucceeds()
    {
        $this->be((new Consumer(0, [config('app.name') . '.read', config('app.name') . '.write']))->setIsAdmin(true));

        Event::fake(\App\Events\V2\Task\Created::class);

        $this->patch(
            '/v2/images/' . $this->image()->id,
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
                'script_template' => 'NEW SCRIPT TEMPLATE',
                'vm_template' => 'NEW VM TEMPLATE',
                'platform' => 'Windows',
                'active' => false,
                'public' => false,
                'visibility' => Image::VISIBILITY_PRIVATE,
            ]
        )->seeInDatabase(
            'images',
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
                'script_template' => 'NEW SCRIPT TEMPLATE',
                'vm_template' => 'NEW VM TEMPLATE',
                'platform' => 'Windows',
                'active' => false,
                'public' => false,
                'visibility' => Image::VISIBILITY_PRIVATE,
            ],
            'ecloud'
        )->assertResponseStatus(202);

        Event::assertDispatched(\App\Events\V2\Task\Created::class);
    }

    public function testUpdatePublicResourceNotAdminFails()
    {
        $this->patch('/v2/images/' . $this->image()->id, [])->assertResponseStatus(403);
    }

    public function testUpdatePrivateResourceAdminNotOwnerSucceeds()
    {
        factory(Image::class)->create([
            'id' => 'img-private-test',
            'vpc_id' => $this->vpc()->id,
            'visibility' => Image::VISIBILITY_PRIVATE
        ]);

        $this->be((new Consumer(0, [config('app.name') . '.read', config('app.name') . '.write']))->setIsAdmin(true));

        Event::fake(\App\Events\V2\Task\Created::class);

        $this->patch(
            '/v2/images/' . $this->image()->id,
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
                'script_template' => 'NEW SCRIPT TEMPLATE',
                'vm_template' => 'NEW VM TEMPLATE',
                'platform' => 'Windows',
                'active' => false,
                'public' => false,
                'visibility' => Image::VISIBILITY_PUBLIC,
            ]
        )->seeInDatabase(
            'images',
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
                'script_template' => 'NEW SCRIPT TEMPLATE',
                'vm_template' => 'NEW VM TEMPLATE',
                'platform' => 'Windows',
                'active' => false,
                'public' => false,
                'visibility' => Image::VISIBILITY_PUBLIC,
            ],
            'ecloud'
        )->assertResponseStatus(202);

        Event::assertDispatched(\App\Events\V2\Task\Created::class);
    }

    public function testUpdatePrivateResourceNotAdminIsOwnerSucceeds()
    {
        Event::fake(\App\Events\V2\Task\Created::class);

        factory(Image::class)->create([
            'id' => 'img-private-test',
            'vpc_id' => $this->vpc()->id,
            'visibility' => Image::VISIBILITY_PRIVATE
        ]);

        $this->patch(
            '/v2/images/img-private-test',
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
            ]
        )->seeInDatabase(
            'images',
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
            ],
            'ecloud'
        )->assertResponseStatus(202);

        Event::assertDispatched(\App\Events\V2\Task\Created::class);
    }

    public function testUpdatePrivateAdminPropertiesIgnored()
    {
        Event::fake(\App\Events\V2\Task\Created::class);

        factory(Image::class)->create([
            'id' => 'img-private-test',
            'vpc_id' => $this->vpc()->id,
            'visibility' => Image::VISIBILITY_PRIVATE
        ]);

        $this->patch(
            '/v2/images/img-private-test',
            [
                'name' => 'NEW NAME',
                'logo_uri' => 'NEW LOGO URI',
                'documentation_uri' => 'NEW DOCS URI',
                'description' => 'NEW DESCRIPTION',
                // Admin only data
                'platform' => 'Windows',
                'script_template' => 'NEW SCRIPT TEMPLATE',
                'vm_template' => 'NEW VM TEMPLATE',
                'active' => false,
                'public' => false,
                'visibility' => Image::VISIBILITY_PUBLIC
            ]
        )->notSeeInDatabase(
            'images',
            [
                'platform' => 'Windows',
                'script_template' => 'NEW SCRIPT TEMPLATE',
                'vm_template' => 'NEW VM TEMPLATE',
                'active' => false,
                'public' => false,
                'visibility' => Image::VISIBILITY_PUBLIC
            ],
            'ecloud'
        )->assertResponseStatus(202);

        Event::assertDispatched(\App\Events\V2\Task\Created::class);

    }

    public function testUpdatePrivateResourceNotAdminNotOwnerFails()
    {
        $this->be(new Consumer(2, [config('app.name') . '.read', config('app.name') . '.write']));

        factory(Image::class)->create([
            'id' => 'img-private-test',
            'vpc_id' => $this->vpc()->id,
            'visibility' => Image::VISIBILITY_PRIVATE
        ]);

        $this->patch('/v2/images/img-private-test', [])->assertResponseStatus(404);
    }
}
