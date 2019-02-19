<?php

namespace App\Http\Controllers\V1;

use App\Exceptions\V1\ApplianceNotFoundException;
use App\Models\V1\Appliance;
use App\Models\V1\ApplianceParameters;
use App\Models\V1\ApplianceVersion;
use App\Rules\V1\IsValidUuid;
use UKFast\Api\Exceptions\DatabaseException;
use UKFast\Api\Exceptions\ForbiddenException;
use UKFast\Api\Exceptions\UnprocessableEntityException;
use UKFast\DB\Ditto\QueryTransformer;

use UKFast\Api\Resource\Traits\ResponseHelper;
use UKFast\Api\Resource\Traits\RequestHelper;

use Illuminate\Http\Request;

class ApplianceVersionController extends BaseController
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
        $collectionQuery = static::getApplianceVersionQuery($request);

        (new QueryTransformer($request))
            ->config(ApplianceVersion::class)
            ->transform($collectionQuery);

        $applianceVersions = $collectionQuery->paginate($this->perPage);

        return $this->respondCollection(
            $request,
            $applianceVersions
        );
    }

    /**
     * Get a singe ApplianceVersion resource
     *
     * @param Request $request
     * @param $applianceVersionId
     * @return \Illuminate\Http\Response
     * @throws ApplianceNotFoundException
     */
    public function show(Request $request, $applianceVersionId)
    {
        $request['id'] = $applianceVersionId;
        $this->validate($request, ['id' => [new IsValidUuid()]]);

        return $this->respondItem(
            $request,
            static::getApplianceVersionById($request, $applianceVersionId)
        );
    }

    /**
     * Create an appliance version.
     *
     * Stores an appliance version record and also stores any associated
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws DatabaseException
     * @throws ForbiddenException
     * @throws UnprocessableEntityException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResourceException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidResponseException
     * @throws \UKFast\Api\Resource\Exceptions\InvalidRouteException
     * @throws ApplianceNotFoundException
     */
    public function create(Request $request)
    {
        if (!$this->isAdmin) {
            throw new ForbiddenException('Only UKFast can publish appliances at this time.');
        }

        // Validate the appliance version
        $rules = ApplianceVersion::$rules;
        $rules['appliance_id'] = [new IsValidUuid()];
        // TODO: Add some template validation here, (validate number of parameters matches the script etc)
        $this->validate($request, $rules);

        //Validate the appliance exists
        ApplianceController::getApplianceById($request, $request->input('appliance_id'));

        //Receive the user data
        $applianceVersion = $this->receiveItem($request, ApplianceVersion::class);

        // Validate appliance version parameters
        if ($request->has('parameters')) {
            $rules = ApplianceParameters::getRules();
            $this->validate(
                $request,
                [
                    'parameters' => ['array'],
                    'parameters.*.name' => $rules['name'],
                    'parameters.*.type' => $rules['type'],
                    'parameters.*.description' => $rules['description'],
                    'parameters.*.required' => $rules['required'],
                    'parameters.*.validation_rule' => $rules['validation_rule'],
                ]
            );
        }

        $database = app('db')->connection('ecloud');
        $database->beginTransaction();

        // Save the appliance version record
        $errorMessage = 'Failed to save appliance version.';
        try {
            $applianceVersion->save();
        } catch (\Illuminate\Database\QueryException $exception) {
            // 23000 Error code (Integrity Constraint Violation: version already exists for this application)
            if ($exception->getCode() == 23000) {
                $errorMessage .= ' Version designation \'' .$request->input('version') . '\' already exists.';
                throw new UnprocessableEntityException($errorMessage);
            }

            throw new DatabaseException($errorMessage);
        }

        // Reload the model to populate the auto-generated data from the database
        $applianceVersion = $applianceVersion->resource->refresh();

        /**
         * Loop through and save any version parameters, we've already validated the data but if any errors occur
         * whilst saving, roll back the entire transaction to remove ALL version parameters AND THE VERSION RECORD.
         */
        foreach ($request->input('parameters') as $parameter) {
            $applianceParameter = new ApplianceParameters();
            $applianceParameter->appliance_version_id = $applianceVersion->id;
            $applianceParameter->name = $parameter['name'];
            $applianceParameter->type = $parameter['type'];

            if (isset($parameter['description'])) {
                $applianceParameter->description = $parameter['description'];
            }

            if (isset($parameter['required'])) {
                $applianceParameter->required = $parameter['required'];
            }

            if (isset($parameter['validation_rule'])) {
                $applianceParameter->validation_rule = $parameter['validation_rule'];
            }

            if (!$applianceParameter->save()) {
                $database->rollback();
                throw new DatabaseException(
                    'Failed to save Appliance version. Invalid parameter \''.$parameter['name'].'\''
                );
            }
        }

        $database->commit();

        return $this->respondSave(
            $request,
            $applianceVersion,
            201
        );
    }


    /**
     * Update an appliance version
     *
     * @param Request $request
     * - appliance_id - uuid, optional
     * - version - string, optional
     * - script_template - string, optional
     * - active - boolean, optional
     * @param $applianceVersionId
     * @return \Illuminate\Http\Response
     * @throws ApplianceNotFoundException
     * @throws ForbiddenException
     * @throws DatabaseException
     * @throws UnprocessableEntityException
     */
    public function update(Request $request, $applianceVersionId)
    {
        if (!$this->isAdmin) {
            throw new ForbiddenException('Only UKFast can update appliance versions at this time.');
        }

        $rules = ApplianceVersion::$rules;
        // Modify our appliance version validation rules for an update
        $rules = array_merge(
            $rules,
            [
                'version' => ['nullable', 'max:25'],
                'script_template' => ['nullable'],
                'id' => [new IsValidUuid()],
                'appliance_id' => ['nullable', new IsValidUuid()]
            ]
        );

        $request['id'] = $applianceVersionId;
        $this->validate($request, $rules);

        //Do we want to change the Appliance the version is associated with?
        if ($request->has('appliance_id')) {
            //Validate the appliance exists
            ApplianceController::getApplianceById($request, $request->input('appliance_id'));
        }

        // Update the resource
        $applianceVersion = $this->receiveItem($request, ApplianceVersion::class);

        $errorMessage = 'Unable to update Appliance version.';
        try {
            $applianceVersion->resource->save();
        } catch (\Illuminate\Database\QueryException $exception) {
            // 23000 Error code (Integrity Constraint Violation: version already exists for this application)
            if ($exception->getCode() == 23000) {
                $errorMessage .= ' Version designation \'' .$request->input('version') . '\' already exists.';
                throw new UnprocessableEntityException($errorMessage);
            }

            throw new DatabaseException($errorMessage);
        }

        return $this->respondEmpty();
    }

    /**
     * Load an appliance version by UUID
     * @param Request $request
     * @param $applianceVersionId
     * @return mixed
     * @throws ApplianceNotFoundException
     */
    public static function getApplianceVersionById(Request $request, $applianceVersionId)
    {
        $applianceVersion = static::getApplianceVersionQuery($request)->find($applianceVersionId);

        if (!is_null($applianceVersion)) {
            return $applianceVersion;
        }

        throw new ApplianceNotFoundException("Appliance version with ID '$applianceVersionId' was not found", 'id');
    }

    /**
     * Get appliances query builder
     * @param $request
     * @return mixed
     */
    public static function getApplianceVersionQuery($request)
    {
        $applianceQuery = ApplianceVersion::query();

        if ($request->user->resellerId != 0) {
            $applianceQuery->where('appliance_version_active', 'Yes');
        }

        $applianceQuery->whereNull('appliance_version_deleted_at');

        return $applianceQuery;
    }
}