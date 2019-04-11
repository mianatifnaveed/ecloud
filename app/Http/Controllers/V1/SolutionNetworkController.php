<?php

namespace App\Http\Controllers\V1;

use UKFast\DB\Ditto\QueryTransformer;

use UKFast\Api\Resource\Traits\ResponseHelper;
use UKFast\Api\Resource\Traits\RequestHelper;

use Illuminate\Http\Request;

use App\Models\V1\SolutionNetwork;

class SolutionNetworkController extends BaseController
{
    use ResponseHelper, RequestHelper;

    public function getSolutionNetworks(Request $request, $solutionId)
    {
        SolutionController::getSolutionById($request, $solutionId);

        $collection = SolutionNetwork::withReseller($request->user->resellerId)
            ->withSolution($solutionId);

        if (!$request->user->isAdministrator) {
            $collection->where('ucs_reseller_active', 'Yes');
        }

        (new QueryTransformer($request))
            ->config(SolutionNetwork::class)
            ->transform($collection);

        return $this->respondCollection(
            $request,
            $collection->paginate($this->perPage)
        );
    }
}
