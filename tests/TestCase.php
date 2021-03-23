<?php

namespace Tests;

use App\Models\V2\Appliance;
use App\Models\V2\ApplianceVersion;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\Credential;
use App\Models\V2\FirewallPolicy;
use App\Models\V2\HostGroup;
use App\Models\V2\HostSpec;
use App\Models\V2\Image;
use App\Models\V2\Instance;
use App\Models\V2\Network;
use App\Models\V2\Region;
use App\Models\V2\Router;
use App\Models\V2\Vpc;
use App\Services\V2\ArtisanService;
use App\Services\V2\ConjurerService;
use App\Services\V2\KingpinService;
use App\Services\V2\NsxService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
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
            $this->instance = factory(Instance::class)->create([
                'id' => 'i-test',
                'vpc_id' => $this->vpc()->id,
                'name' => 'Test Instance ' . uniqid(),
                'image_id' => $this->image()->id,
                'vcpu_cores' => 1,
                'ram_capacity' => 1024,
                'platform' => 'Linux',
                'availability_zone_id' => $this->availabilityZone()->id
            ]);
        }
        return $this->instance;
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
            $this->hostGroupJobMocks();
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

    public function hostGroupJobMocks()
    {
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

        // CreateTransportNode Job
        $this->kingpinServiceMock()->expects('get')
            ->with('/api/v1/vpc/vpc-test/network/switch')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'name' => 'test-network-switch-name',
                    'uuid' => 'test-network-switch-uuid',
                ]));
            });
        $this->nsxServiceMock()->expects('get')
            ->with('/api/v1/transport-node-profiles')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'id' => 'TEST-TRANSPORT-NODE-PROFILE-ID',
                            'display_name' => 'TEST-TRANSPORT-NODE-PROFILE-DISPLAY-NAME',
                        ],
                    ],
                ]));
            });
        $this->nsxServiceMock()->expects('get')
            ->with('/api/v1/search/query?query=resource_type:TransportZone%20AND%20tags.scope:ukfast%20AND%20tags.tag:default-overlay-tz')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'id' => 'TEST-TRANSPORT-ZONE-ID',
                            'transport_zone_profile_ids' => [
                                'profile_id' => 'TEST-TRANSPORT-NODE-PROFILE-ID',
                                'resource_type' => 'BfdHealthMonitoringProfile',
                            ],
                        ],
                    ],
                ]));
            });
        $this->nsxServiceMock()->expects('get')
            ->with('/api/v1/search/query?query=resource_type:UplinkHostSwitchProfile%20AND%20tags.scope:ukfast%20AND%20tags.tag:default-uplink-profile')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'id' => 'TEST-UPLINK-HOST-SWITCH-ID',
                        ],
                    ],
                ]));
            });
        $this->nsxServiceMock()->expects('post')
            ->withSomeOfArgs('/api/v1/transport-node-profiles')
            ->andReturnUsing(function () {
                return new Response(200);
            });

        // PrepareCluster Job
        $this->nsxServiceMock()->expects('get')
            ->with('/api/v1/transport-node-collections')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'id' => 'TEST-TRANSPORT-NODE-COLLECTION-ID',
                            'display_name' => 'TEST-TRANSPORT-NODE-COLLECTION-DISPLAY-NAME',
                        ],
                    ],
                ]));
            });
        $this->nsxServiceMock()->expects('get')
            ->with('/api/v1/search/query?query=resource_type:TransportNodeProfile%20AND%20display_name:tnp-hg-test')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'id' => 'TEST-TRANSPORT-NODE-COLLECTION-ID',
                        ],
                    ],
                ]));
            });
        $this->nsxServiceMock()->expects('get')
            ->with('/api/v1/fabric/compute-collections?origin_type=VC_Cluster&display_name=hg-test')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'results' => [
                        [
                            'external_id' => 'TEST-COMPUTE-COLLECTION-ID',
                        ],
                    ],
                ]));
            });
        $this->nsxServiceMock()->expects('post')
            ->withSomeOfArgs(
                '/api/v1/transport-node-collections',
                [
                    'json' => [
                        'resource_type' => 'TransportNodeCollection',
                        'display_name' => 'hg-test-tnc',
                        'description' => 'API created Transport Node Collection',
                        'compute_collection_id' => 'TEST-COMPUTE-COLLECTION-ID',
                        'transport_node_profile_id' => 'TEST-TRANSPORT-NODE-COLLECTION-ID',
                    ]
                ]
            )
            ->andReturnUsing(function () {
                return new Response(200);
            });
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
