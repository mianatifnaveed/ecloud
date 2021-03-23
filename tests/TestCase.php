<?php

namespace Tests;

use App\Models\V2\Appliance;
use App\Models\V2\ApplianceVersion;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\Credential;
use App\Models\V2\FirewallPolicy;
use App\Models\V2\HostGroup;
use App\Models\V2\HostSpec;
use App\Models\V2\Instance;
use App\Models\V2\Network;
use App\Models\V2\Nic;
use App\Models\V2\Region;
use App\Models\V2\Router;
use App\Models\V2\Volume;
use App\Models\V2\Vpc;
use App\Models\V2\Image;
use App\Providers\EncryptionServiceProvider;
use App\Services\V2\ArtisanService;
use App\Services\V2\ConjurerService;
use App\Services\V2\KingpinService;
use App\Services\V2\NsxService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Application;
use Laravel\Lumen\Testing\DatabaseMigrations;

abstract class TestCase extends \Laravel\Lumen\Testing\TestCase
{
    // This is required for the Kingping/NSX mocks, see below
    use DatabaseMigrations,
        Mocks\Traits\Host;

    public $validReadHeaders = [
        'X-consumer-custom-id' => '1-1',
        'X-consumer-groups' => 'ecloud.read',
    ];
    public $validWriteHeaders = [
        'X-consumer-custom-id' => '0-0',
        'X-consumer-groups' => 'ecloud.read, ecloud.write',
    ];

    /** @var Region */
    private $region;

    /** @var AvailabilityZone */
    private $availabilityZone;

    /** @var Vpc */
    private $vpc;

    /** @var FirewallPolicy */
    private $firewallPolicy;

    /** @var Router */
    private $router;

    /** @var NsxService */
    private $nsxServiceMock;

    /** @var KingpinService */
    private $kingpinServiceMock;

    /** @var ConjurerService */
    private $conjurerServiceMock;

    /** @var ArtisanService */
    private $artisanServiceMock;

    /** @var Credential */
    private $credential;

    /** @var Instance */
    private $instance;

    /** @var ApplianceVersion */
    private $applianceVersion;

    /** @var Appliance */
    private $appliance;

    /** @var Network */
    private $network;

    /** @var HostSpec */
    private $hostSpec;

    /** @var HostGroup */
    private $hostGroup;

    /** @var Image */
    private $image;

    /** @var Nic */
    private $nic;

    /**
     * Creates the application.
     * @return Application
     */
    public function createApplication()
    {
        return require __DIR__ . '/../bootstrap/app.php';
    }

    public function firewallPolicy($id = 'fwp-test')
    {
        if (!$this->firewallPolicy) {
            $this->firewallPolicy = factory(FirewallPolicy::class)->create([
                'id' => $id,
                'router_id' => $this->router()->id,
            ]);
        }
        return $this->firewallPolicy;
    }

    public function router()
    {
        if (!$this->router) {
            $this->router = factory(Router::class)->create([
                'id' => 'rtr-test',
                'vpc_id' => $this->vpc()->id,
                'availability_zone_id' => $this->availabilityZone()->id
            ]);
        }
        return $this->router;
    }

    public function vpc()
    {
        if (!$this->vpc) {
            $this->vpc = factory(Vpc::class)->create([
                'id' => 'vpc-test',
                'region_id' => $this->region()->id
            ]);
        }
        return $this->vpc;
    }

    public function region()
    {
        if (!$this->region) {
            $this->region = factory(Region::class)->create([
                'id' => 'reg-test',
            ]);
        }
        return $this->region;
    }

    public function availabilityZone()
    {
        if (!$this->availabilityZone) {
            $this->availabilityZone = factory(AvailabilityZone::class)->create([
                'id' => 'az-test',
                'region_id' => $this->region()->id,
            ]);
        }
        return $this->availabilityZone;
    }

    public function instance()
    {
        if (!$this->instance) {
/*            app()->bind(Volume::class, function () {
                return new Volume(['id' => 'vol-test']);
            });

            // Deploy :: Deploy instance from template
            $this->kingpinServiceMock()->expects('post')
                ->withSomeOfArgs('/api/v2/vpc/vpc-test/instance/fromtemplate')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // ConfigureNICs, PrepareOSDisk :: Return instance details
            $this->kingpinServiceMock()->expects('get')
                ->zeroOrMoreTimes()
                ->withSomeOfArgs('/api/v2/vpc/vpc-test/instance/i-test')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode([
                        'numCPU' => 1,
                        'ramMiB' => 1024,
                        'nics' => [
                            [
                                'macAddress' => 'AA:BB:CC:DD:EE:FF',
                            ]
                        ],
                        'volumes' => [
                            [
                                'volumeId' => 'vol-test',
                                'uuid' => 'uuid-test-uuid-test-uuid-test',
                            ]
                        ],
                    ]));
                });

            // PrepareOSDisk :: Set volume ID for FCD
            $this->kingpinServiceMock()->expects('put')
                ->withSomeOfArgs('/api/v1/vpc/' . $this->vpc()->id . '/volume/uuid-test-uuid-test-uuid-test/resourceid',)
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // PrepareOSDisk :: Set volume IOPS
            $this->kingpinServiceMock()->expects('put')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/volume/uuid-test-uuid-test-uuid-test/iops',)
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // ConfigureNics :: Retrieve DHCP static bindings
            $this->nsxServiceMock()->expects('get')
                ->withSomeOfArgs('/policy/api/v1/infra/tier-1s/' . $this->router()->id . '/segments/' . $this->network()->id . '/dhcp-static-binding-configs?cursor=')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode([
                        'results' => []
                    ]));
                });

            // CreateDHCPLease :: Create DHCP lease in NSX
            $this->nsxServiceMock()->expects('put')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // UpdateNetworkAdapter :: Connect NIC to network
            $this->kingpinServiceMock()->expects('put')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/nic/AA:BB:CC:DD:EE:FF/connect')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // OsCustomisation :: Connect NIC to network
            $this->kingpinServiceMock()->expects('put')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/oscustomization')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // PowerOn :: Power on instance
            $this->kingpinServiceMock()->expects('post')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/power')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // WaitOsCustomisation :: Wait for os customization
            $this->kingpinServiceMock()->expects('get')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/oscustomization/status')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(['status'=>'Succeeded']));
                });

            // PrepareOsUsers :: Create admin group
            $this->kingpinServiceMock()->expects('post')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/guest/linux/admingroup')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // PrepareOsUsers :: Create graphiterack/ukfastsupport accounts
            $this->kingpinServiceMock()->expects('post')
                ->times(2)
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/guest/linux/user')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });

            // ExpandOsDisk :: Expand /
            $this->kingpinServiceMock()->expects('put')
                ->withSomeOfArgs('/api/v2/vpc/' . $this->vpc()->id . '/instance/i-test/guest/linux/disk/lvm/extend')
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(true));
                });*/

            Instance::withoutEvents(function() {
                $this->instance = factory(Instance::class)->create([
                    'id' => 'i-test',
                    'vpc_id' => $this->vpc()->id,
                    'name' => 'Test Instance ' . uniqid(),
                    'image_id' => $this->image()->id,
                    'vcpu_cores' => 1,
                    'ram_capacity' => 1024,
                    'platform' => 'Linux',
                    'availability_zone_id' => $this->availabilityZone()->id,
                    'deploy_data' => [
                        'network_id' => $this->network()->id,
                        'volume_capacity' => 20,
                        'volume_iops' => 300,
                        'requires_floating_ip' => false,
                    ]
                ]);
            });

            //app()->bind(Volume::class);
        }
        return $this->instance;
    }

    public function nic()
    {
        if (!$this->nic) {
            Nic::withoutEvents(function() {
                $this->nic = factory(Nic::class)->create([
                    'id' => 'nic-test',
                    'mac_address' => 'AA:BB:CC:DD:EE:FF',
                    'instance_id' => $this->instance()->id,
                    'network_id' => $this->network()->id,
                ]);
            });
        }
    }

    public function image()
    {
        if (!$this->image) {
            $this->image = factory(Image::class)->create([
                'appliance_version_id' => $this->applianceVersion()->id,
            ]);
        }
        return $this->image;
    }

    public function applianceVersion()
    {
        if (!$this->applianceVersion) {
            $this->applianceVersion = factory(ApplianceVersion::class)->create([
                'appliance_version_appliance_id' => $this->appliance()->id,
            ]);
        }
        return $this->applianceVersion;
    }

    public function appliance()
    {
        if (!$this->appliance) {
            $this->appliance = factory(Appliance::class)->create([
                'appliance_name' => 'Test Appliance',
            ])->refresh();
        }
        return $this->appliance;
    }

    public function network()
    {
        if (!$this->network) {
            $this->network = factory(Network::class)->create([
                'name' => 'Manchester Network',
                'router_id' => $this->router()->id
            ]);
        }
        return $this->network;
    }

    public function hostGroup()
    {
        if (!$this->hostGroup) {
            // CreateCluster Job
            $this->kingpinServiceMock()->expects('get')
                ->with('/api/v2/vpc/vpc-test/hostgroup/hg-test')
                ->andReturnUsing(function () {
                    return new Response(404);
                });
            $this->kingpinServiceMock()->expects('post')
                ->withSomeOfArgs(
                    '/api/v2/vpc/vpc-test/hostgroup',
                    [
                        'json' => [
                            'hostGroupId' => 'hg-test',
                            'shared' => false,
                        ]
                    ]
                )
                ->andReturnUsing(function () {
                    return new Response(200);
                });

            $this->hostGroup = factory(HostGroup::class)->create([
                'id' => 'hg-test',
                'name' => 'hg-test',
                'vpc_id' => $this->vpc()->id,
                'availability_zone_id' => $this->availabilityZone()->id,
                'host_spec_id' => $this->hostSpec()->id,
            ]);
        }
        return $this->hostGroup;
    }

    public function hostSpec()
    {
        if (!$this->hostSpec) {
            $this->hostSpec = factory(HostSpec::class)->create([
                'id' => 'hs-test',
                'name' => 'test-host-spec',
            ]);
        }
        return $this->hostSpec;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $mockEncryptionServiceProvider = \Mockery::mock(EncryptionServiceProvider::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        app()->bind('encrypter', function () use ($mockEncryptionServiceProvider) {
            $mockEncryptionServiceProvider->shouldReceive('encrypt')->andReturn('EnCrYpTeD-pAsSwOrD');
            $mockEncryptionServiceProvider->shouldReceive('decrypt')->andReturn('somepassword');
            return $mockEncryptionServiceProvider;
        });

        // Using these mocks means we have to use DatabaseMigration by default, but the ability to catch 3rd party
        // API calls being performed in tests is more than worth the extra overhead.
        $this->kingpinServiceMock();
        $this->nsxServiceMock();

        Event::fake([
            // V1 hack
            \App\Events\V1\DatastoreCreatedEvent::class,

            // Created
            \App\Events\V2\AvailabilityZone\Created::class,
            \App\Events\V2\Dhcp\Created::class,
            \App\Events\V2\Network\Created::class,
            \App\Events\V2\Router\Created::class,
            \App\Events\V2\Vpc\Created::class,
            \App\Events\V2\FloatingIp\Created::class,
            \App\Events\V2\Nat\Created::class,

            // Deleting
            \App\Events\V2\Nat\Deleting::class,

            // Deleted
            \App\Events\V2\AvailabilityZone\Deleted::class,
            \App\Events\V2\Nat\Deleted::class,
            \App\Events\V2\Vpc\Deleted::class,
            \App\Events\V2\Dhcp\Deleted::class,
            \App\Events\V2\FloatingIp\Deleted::class,
            \App\Events\V2\Network\Deleted::class,
            \App\Events\V2\Router\Deleted::class,

            // Saved
            \App\Events\V2\Router\Saved::class,
            \App\Events\V2\Network\Saved::class,
            \App\Events\V2\Nat\Saved::class,
            \App\Events\V2\AvailabilityZoneCapacity\Saved::class,

            // Updated
            \App\Events\V2\Sync\Updated::class,

            // Saving
            \App\Events\V2\Router\Saving::class,
            \App\Events\V2\Network\Saving::class,
            \App\Events\V2\Nat\Saving::class,

        ]);
    }

    public function kingpinServiceMock()
    {
        if (!$this->kingpinServiceMock) {
            factory(Credential::class)->create([
                'id' => 'cred-kingpin',
                'name' => 'kingpinapi',
                'resource_id' => $this->availabilityZone()->id,
            ]);
            $this->kingpinServiceMock = \Mockery::mock(new KingpinService(new Client()))->makePartial();
            app()->bind(KingpinService::class, function () {
                return $this->kingpinServiceMock;
            });
        }
        return $this->kingpinServiceMock;
    }

    public function nsxServiceMock()
    {
        if (!$this->nsxServiceMock) {
            factory(Credential::class)->create([
                'id' => 'cred-nsx',
                'name' => 'NSX',
                'resource_id' => $this->availabilityZone()->id,
            ]);
            $nsxService = app()->makeWith(NsxService::class, [$this->availabilityZone()]);
            $this->nsxServiceMock = \Mockery::mock($nsxService)->makePartial();
            app()->bind(NsxService::class, function () {
                return $this->nsxServiceMock;
            });
        }
        return $this->nsxServiceMock;
    }

    public function conjurerServiceMock()
    {
        if (!$this->conjurerServiceMock) {
            factory(Credential::class)->create([
                'id' => 'cred-ucs',
                'name' => 'UCS API',
                'username' => config('conjurer.ucs_user'),
                'resource_id' => $this->availabilityZone()->id,
            ]);

            factory(Credential::class)->create([
                'id' => 'cred-conjurer',
                'name' => 'Conjurer API',
                'username' => config('conjurer.user'),
                'resource_id' => $this->availabilityZone()->id,
            ]);

            $this->conjurerServiceMock = \Mockery::mock(new ConjurerService(new Client()))->makePartial();
            app()->bind(ConjurerService::class, function () {
                return $this->conjurerServiceMock;
            });
        }
        return $this->conjurerServiceMock;
    }

    public function artisanServiceMock()
    {
        if (!$this->artisanServiceMock) {
            factory(Credential::class)->create([
                'id' => 'cred-3par',
                'name' => '3PAR',
                'username' => config('artisan.user'),
                'resource_id' => $this->availabilityZone()->id,
            ]);

            factory(Credential::class)->create([
                'id' => 'cred-artisan',
                'name' => 'Artisan API',
                'username' => config('artisan.san_user'),
                'resource_id' => $this->availabilityZone()->id,
            ]);

            $this->artisanServiceMock = \Mockery::mock(new ArtisanService(new Client()))->makePartial();
            app()->bind(ArtisanService::class, function () {
                return $this->artisanServiceMock;
            });
        }
        return $this->artisanServiceMock;
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
