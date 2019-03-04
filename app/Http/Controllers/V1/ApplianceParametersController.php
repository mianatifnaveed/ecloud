<?php

namespace App\Http\Controllers\V1;

use App\Exceptions\V1\ParameterNotFoundException;
use App\Models\V1\ApplianceParameters;
use App\Rules\V1\IsValidUuid;
use UKFast\Api\Exceptions\BadRequestException;
use UKFast\Api\Exceptions\DatabaseException;
use UKFast\DB\Ditto\QueryTransformer;

use UKFast\Api\Resource\Traits\ResponseHelper;
use UKFast\Api\Resource\Traits\RequestHelper;

use Illuminate\Http\Request;

class ApplianceParametersController extends BaseController
{
    use ResponseHelper, RequestHelper;

    /**
     * List all Appliances Versions collection
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $collectionQuery = static::getApplianceParametersQuery($request);

        (new QueryTransformer($request))
            ->config(ApplianceParameters::class)
            ->transform($collectionQuery);

        $applianceParameters = $collectionQuery->paginate($this->perPage);

        return $this->respondCollection(
            $request,
            $applianceParameters,
            200,
            null,
            [],
            ($this->isAdmin) ? null : ApplianceParameters::VISIBLE_SCOPE_RESELLER
        );
    }

    /**
     * Get a single ApplianceVersion resource
     *
     * @param Request $request
     * @param $parameterId
     * @return \Illuminate\Http\Response
     * @throws ParameterNotFoundException
     */
    public function show(Request $request, $parameterId)
    {
        $request['id'] = $parameterId;
        $this->validate($request, ['id' => [new IsValidUuid()]]);

        return $this->respondItem(
            $request,
            static::getApplianceParameterById($request, $parameterId),  
            200,
            null,
            [],
            ($this->isAdmin) ? null : ApplianceParameters::VISIBLE_SCOPE_RESELLER
        );
    }


    /**
     * Create an appliance script parameter
     *
     * @param Request $request
     * - version_id - requiredm UUID
     * - name - string, nullable
     * - type - enum, required
     * - required, optional, default true
     * - validation_rule, optional
     *
     * @return \Illuminate\Http\Response
     * @throws DatabaseException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResourceException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResponseException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidRouteException
     * @throws \App\Exceptions\V1\ApplianceVersionNotFoundException
     */
    public function create(Request $request)
    {
        $rules = ApplianceParameters::getRules();
        $this->validate($request, $rules);

        //Validate the appliance exists
        ApplianceVersionController::getApplianceVersionById($request, $request->input('version_id'));

        //TODO: If the parameter is required, validate that the parameter key is present in the script template
        $applianceParameter = $this->receiveItem($request, ApplianceParameters::class);
        if (!$applianceParameter->resource->save()) {
            throw new DatabaseException('Unable to save Appliance parameter.');
        }

        return $this->respondSave(
            $request,
            $applianceParameter,
            201
        );
    }

    /**
     * Update an appliance script parameter
     * @param Request $request
     * @param $applianceParameterId
     * @return \Illuminate\Http\Response
     * @throws BadRequestException
     * @throws DatabaseException
     * @throws ParameterNotFoundException
     * @throws \App\Exceptions\V1\ApplianceVersionNotFoundException
     */
    public function update(Request $request, $applianceParameterId)
    {
        if (!$this->isAdmin) {
            throw new ForbiddenException('Only UKFast can update appliance parameters at this time.');
        }

        $rules = ApplianceParameters::getUpdateRules();

        $request['id'] = $applianceParameterId;
        $this->validate($request, $rules);

        //Do we want to change the Appliance Version the parameter is associated with?
        if ($request->has('version_id')) {
            $applianceParameter = ApplianceParametersController::getApplianceParameterById(
                $request,
                $applianceParameterId
            );
            //Validate the appliance version exists
            $newApplianceVersion = ApplianceVersionController::getApplianceVersionById(
                $request,
                $request->input('version_id')
            );

            // Only allow a parameter to be moved between different versions of the same Application
            if ($newApplianceVersion->appliance->id != $applianceParameter->applianceVersion->appliance->id) {
                throw new BadRequestException('Parameters can not be moved between different Appliances.');
            }
        }

        // Update the resource
        $applianceParameter = $this->receiveItem($request, ApplianceParameters::class);

        try {
            $applianceParameter->resource->save();
        } catch (\Illuminate\Database\QueryException $exception) {
            throw new DatabaseException('Unable to update Appliance parameter.');
        }

        return $this->respondEmpty();
    }


    /**
     * Load an appliance parameter (by UUID)
     * @param Request $request
     * @param $applianceParameterId
     * @return mixed
     * @throws ParameterNotFoundException
     */
    public static function getApplianceParameterById(Request $request, $applianceParameterId)
    {
        $applianceVersion = static::getApplianceParametersQuery($request)->find($applianceParameterId);

        if (is_null($applianceVersion)) {
            throw new ParameterNotFoundException("Parameter with ID '$applianceParameterId' was not found", 'id');
        }

        return $applianceVersion;
    }

    /**
     * Get appliance parameter query builder
     * @param $request
     * @return mixed
     */
    public static function getApplianceParametersQuery($request)
    {
        $applianceParametersQuery = ApplianceParameters::query()
            ->join(
                'appliance_version',
                'appliance_script_parameters_appliance_version_id',
                '=',
                'appliance_version_id'
            )
            ->join(
                'appliance',
                'appliance_id',
                '=',
                'appliance_version_appliance_id'
            )
            ->whereNull('appliance_version_deleted_at');

        if ($request->user->resellerId != 0) {
            $applianceParametersQuery->where('appliance_version_active', 'Yes');
            $applianceParametersQuery->where('appliance_active', 'Yes');
        }

        return $applianceParametersQuery;
    }
}
