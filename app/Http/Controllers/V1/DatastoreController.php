<?php

namespace App\Http\Controllers\V1;

use App\Datastore\Status;
use App\Events\V1\DatastoreExpandEvent;
use App\Exceptions\V1\ArtisanException;
use App\Exceptions\V1\ConflictException;
use App\Exceptions\V1\IntapiServiceException;
use App\Exceptions\V1\KingpinException;
use App\Exceptions\V1\SanNotFoundException;
use App\Exceptions\V1\ServiceUnavailableException;
use App\Models\V1\Storage;
use App\Rules\V1\IsValidUuid;
use App\Services\IntapiService;
use App\Traits\V1\SanitiseRequestData;
use Illuminate\Support\Facades\Event;
use UKFast\Api\Exceptions\BadRequestException;
use UKFast\Api\Exceptions\ForbiddenException;
use UKFast\Api\Exceptions\UnprocessableEntityException;
use UKFast\DB\Ditto\QueryTransformer;
use Illuminate\Support\Facades\Log;

use UKFast\Api\Resource\Traits\ResponseHelper;
use UKFast\Api\Resource\Traits\RequestHelper;

use Illuminate\Http\Request;

use App\Models\V1\Datastore;
use App\Resources\V1\DatastoreResource;
use App\Datastore\Exceptions\DatastoreNotFoundException;

class DatastoreController extends BaseController
{
    use ResponseHelper, RequestHelper, SanitiseRequestData;

    /**
     * List all Datastores
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $collectionQuery = static::getDatastoreQuery($request);

        (new QueryTransformer($request))
            ->config(Datastore::class)
            ->transform($collectionQuery);

        return $this->respondCollection(
            $request,
            $collectionQuery->paginate($this->perPage),
            200,
            DatastoreResource::class,
            [],
            Datastore::$collectionProperties
        );
    }

    /**
     * Show specific datastore
     *
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\http\Response
     * @throws DatastoreNotFoundException
     */
    public function show(Request $request, $datastoreId)
    {
        $datastore = static::getDatastoreById($request, $datastoreId);

        return $this->respondItem(
            $request,
            $datastore,
            200,
            DatastoreResource::class,
            [],
            Datastore::$itemProperties
        );
    }

    /**
     * Create datastore
     *
     * Creates the initial reseller_lun record and fires off automation
     * @param Request $request
     * @param IntapiService $intapiService
     * @return \Illuminate\Http\Response
     * @throws ArtisanException
     * @throws ConflictException
     * @throws SanNotFoundException
     * @throws UnprocessableEntityException
     * @throws \App\Exceptions\V1\SiteNotFoundException
     * @throws \App\Exceptions\V1\SolutionNotFoundException
     * @throws BadRequestException
     * @throws ServiceUnavailableException
     */
    public function create(Request $request, IntapiService $intapiService)
    {
        $this->validate($request, Datastore::getRules());

        // Determine the pod
        if ($request->has('site_id')) {
            $solutionSite = SolutionSiteController::getSiteById($request, $request->input('site_id'));
            $solution = $solutionSite->solution;
            $pod = $solutionSite->pod;
        } else {
            $solution = SolutionController::getSolutionById($request, $request->input('solution_id'));
            $pod = $solution->pod;
        }

        if ($request->has('name')) {
            // Validate volume friendly name is unique to the solution or solution site
            $datastores = Collect(Datastore::getForSolution($solution->getKey(), $request->input('site_id')));

            if ($datastores->contains('reseller_lun_friendly_name', '=', $request->input('name'))) {
                throw new ConflictException(
                    "Datastore with name '{$request->input('name')}' already exists for this " . ($request->has('site_id') ? 'solution site' : 'solution')
                );
            }
        }

        // Receive the user data
        $datastoreResource = $this->receiveItem(
            new Request($request->only(['solution_id', 'capacity', 'name'])),
            Datastore::class
        );

        if ($pod->sans->count() == 0) {
            throw new SanNotFoundException('No SANS are available on the solution\'s pod');
        }

        // If more than 1 san is available on the pod user must specify a san_id
        if ($pod->sans->count() > 1 && !$request->has('san_id')) {
            throw new UnprocessableEntityException(
                'More than one SAN is available on the solution\'s pod - Please specify a san_id'
            );
        }

        // If the user specified a san_id check that the san in on the solution / solution sites pod
        if ($request->has('san_id')) {
            $storage = Storage::withPod($pod->getKey())
                ->where('server_id', '=', $request->input('san_id'));

            if ($storage->count() < 1) {
                $errorMessage = 'A SAN with the requested ID was not found on the solution\'s pod';
                Log::error(
                    $errorMessage,
                    [
                        'san_id' => $request->input('san_id'),
                        'pod_id' => $pod->getKey()
                    ]
                );
                throw new SanNotFoundException($errorMessage);
            };

            $storage = $storage->first();
        }

        // Only one SAN is available for the pod so use that
        if (empty($storage)) {
            $storage = Storage::withPod($pod->getKey())
                ->where('server_id', '=', $pod->sans->first()->servers_id)
                ->first();
        }

        $automationData = [
            'san_id' => $storage->server_id
        ];

        if ($request->has('iops_tier')) {
            if (!$storage->qosEnabled()) {
                throw new BadRequestException('IOPS is not configurable for this datastore');
            }
            $this->validate($request, ['iops_tier' => ['required', new IsValidUuid()]]);
            // Validate teh input is a valid IOPS tier, but pass a numeric value to automation
            $iops = IOPSController::getById($request->input('iops_tier'));
            $automationData['max_iops'] = $iops->max_iops;
        }

        // Only allow DATA LUN's for non-admins
        if ($request->has('lun_type')) {
            if (!$this->isAdmin && $request->input('lun_type') != 'DATA') {
                throw new BadRequestException('lun_type \'' . $request->input('lun_type') . '\' is not supported');
            }
        }

        $datastore = $datastoreResource->resource;
        $datastore->reseller_lun_ucs_storage_id = $storage->getKey();
        $datastore->reseller_lun_type = $solution->ucs_reseller_type;
        $datastore->reseller_lun_reseller_id = $solution->resellerId();
        $datastore->reseller_lun_lun_type = $request->input('lun_type', 'DATA');
        $datastore->reseller_lun_status = Status::QUEUED;
        $datastore->save();
        $datastore->refresh();

        try {
            $automationRequestId = $intapiService->automationRequest(
                'add_lun',
                'reseller_lun',
                $datastore->getKey(),
                $automationData,
                'ecloud_ucs_' . $pod->getKey(),
                $request->user->applicationId
            );
        } catch (IntapiServiceException $exception) {
            throw new ServiceUnavailableException('Failed to expand datastore', null, 502);
        }

        $headers = [];
        if ($request->user->isAdministrator) {
            $headers = [
                'X-AutomationRequestId' => $automationRequestId
            ];
        }

        return $this->respondEmpty(202, $headers);
    }

    /**
     * Create a volume on the SAN for the datastore
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws BadRequestException
     * @throws DatastoreNotFoundException
     */
    public function createVolume(Request $request, $datastoreId)
    {
        $datastore = static::getDatastoreById($request, $datastoreId);

        if (!empty($datastore->reseller_lun_name)) {
            throw new BadRequestException('A volume has already been assigned to this datastore.');
        }

        $datastore->createVolume();

        return $this->respondEmpty(201);
    }

    /**
     * Create the datastore on VMWare
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws DatastoreNotFoundException
     * @throws KingpinException
     */
    public function createDatastore(Request $request, $datastoreId)
    {
        $datastore = static::getDatastoreById($request, $datastoreId);

        $datastore->create();

        return $this->respondEmpty(201);
    }

    /**
     * Update datastore
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws DatastoreNotFoundException
     */
    public function update(Request $request, $datastoreId)
    {
        static::getDatastoreById($request, $datastoreId);

        $rules = Datastore::getRules();

        $rules = array_merge(
            $rules,
            [
                'capacity' => ['nullable', 'numeric'],
                'solution_id' => ['sometimes', 'numeric'],
                'type' => ['sometimes'],
                'lun_type' => ['sometimes', 'in:DATA,CLUSTER,QRM'],
                'name' => ['nullable'],
                'lun_wwn' => ['nullable', 'max:255']
            ]
        );

        $request['id'] = $datastoreId;
        $this->validate($request, $rules);

        $datastore = $this->receiveItem(new Request($request->only(['id', 'status', 'capacity', 'name'])), Datastore::class);

        $datastore->resource->save();

        return $this->respondEmpty();
    }

    /**
     * Expand a datastore - Initiate automation to expand a datastore
     *
     * Schedules automation to expand a datastore
     * @param Request $request
     * @param IntapiService $intapiService
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws DatastoreNotFoundException
     * @throws ForbiddenException
     * @throws ServiceUnavailableException
     * Todo: This is locked down to admin until we move billing from myukfast to an automation step for expand datastore
     */
    public function expand(Request $request, IntapiService $intapiService, $datastoreId)
    {
        $this->validate($request, ['size_gb' => 'required|integer|min:2']);

        $datastore = DatastoreController::getDatastoreById($request, $datastoreId);

        // check the new size is larger than the current size
        $newSizeGB = $request->input('size_gb');
        if ($newSizeGB <= $datastore->reseller_lun_size_gb) {
            throw new ForbiddenException('New datastore size must be greater than the current size');
        }
        $datastore->reseller_lun_status = Status::EXPANDING;

        if ($datastore->reseller_lun_lun_type != 'DATA') {
            throw new ForbiddenException(
                'Datastores of type ' . $datastore->reseller_lun_lun_type . ' can not be expanded automatically'
            );
        }

        try {
            $automationRequestId = $intapiService->automationRequest(
                'expand_lun',
                'reseller_lun',
                $datastore->getKey(),
                [
                    'new_capacity_gb' => $newSizeGB
                ],
                'ecloud_ucs_' . $datastore->storage->pod->getKey(),
                $request->user->applicationId
            );
        } catch (IntapiServiceException $exception) {
            throw new ServiceUnavailableException('Failed to expand datastore.');
        }

        $datastore->save();

        $headers = [];
        if ($request->user->isAdministrator) {
            $headers = [
                'X-AutomationRequestId' => $automationRequestId
            ];
        }

        Event::fire(new DatastoreExpandEvent($datastore, $newSizeGB));

        return $this->respondEmpty(202, $headers);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public static function getDatastoreQuery(Request $request)
    {
        $query = Datastore::withReseller($request->user->resellerId)
            ->where('reseller_lun_status', '!=', 'Deleted')
            ->join('ucs_reseller', 'ucs_reseller_id', '=', 'reseller_lun_ucs_reseller_id');

        if (!$request->user->isAdministrator) {
            $query->where('ucs_reseller_active', 'Yes');
        }

        return $query;
    }

    /**
     * get datastore by ID
     * @param Request $request
     * @param $datastoreId
     * @return mixed
     * @throws DatastoreNotFoundException
     */
    public static function getDatastoreById(Request $request, $datastoreId)
    {
        $datastore = static::getDatastoreQuery($request)->find($datastoreId);

        if (is_null($datastore)) {
            throw new DatastoreNotFoundException('Datastore ID #' . $datastoreId . ' not found');
        }

        return $datastore;
    }

    /**
     * List Solution Datastores
     * @param Request $request
     * @param $solutionId
     * @return \Illuminate\Http\Response
     * @throws \App\Exceptions\V1\SolutionNotFoundException
     */
    public function indexSolution(Request $request, $solutionId)
    {
        SolutionController::getSolutionById($request, $solutionId);
        $collectionQuery = static::getDatastoreQuery($request)
            ->withSolution($solutionId);

        (new QueryTransformer($request))
            ->config(Datastore::class)
            ->transform($collectionQuery);

        return $this->respondCollection(
            $request,
            $collectionQuery->paginate($this->perPage),
            200,
            DatastoreResource::class,
            [],
            Datastore::$collectionProperties
        );
    }

    /**
     * Expand the datastore volume on the SAN
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws DatastoreNotFoundException
     * @throws ForbiddenException
     */
    public function expandVolume(Request $request, $datastoreId)
    {
        $datastore = DatastoreController::getDatastoreById($request, $datastoreId);

        $this->validate($request, ['size_gb' => 'required|integer|min:2']);

        // check the new size is larger than the current size
        $newSizeGB = $request->input('size_gb');
        if ($newSizeGB <= $datastore->reseller_lun_size_gb) {
            throw new ForbiddenException('New datastore size must be greater than the current size');
        }

        $datastore->expandVolume($newSizeGB);

        return $this->respondEmpty();
    }


    /**
     * Rescan a cluster on VMWare after expanding a datastore
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws DatastoreNotFoundException
     * @throws KingpinException
     */
    public function clusterRescan(Request $request, $datastoreId)
    {
        $datastore = DatastoreController::getDatastoreById($request, $datastoreId);

        try {
            $datastore->clusterRescan();
        } catch (\Exception $exception) {
            throw new KingpinException('Failed to rescan datastore: ' . $exception->getMessage());
        }

        return $this->respondEmpty();
    }

    /**
     * Expand the datastore on VMWare
     * @param Request $request
     * @param $datastoreId
     * @return \Illuminate\Http\Response
     * @throws DatastoreNotFoundException
     * @throws KingpinException
     */
    public function expandDatastore(Request $request, $datastoreId)
    {
        $datastore = DatastoreController::getDatastoreById($request, $datastoreId);

        try {
            $datastore->expand();
        } catch (\Exception $exception) {
            throw new KingpinException('Failed to expand datastore: ' . $exception->getMessage());
        }

        return $this->respondEmpty();
    }
}
