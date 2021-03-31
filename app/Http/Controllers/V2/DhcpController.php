<?php

namespace App\Http\Controllers\V2;

use App\Exceptions\SyncException;
use App\Http\Requests\V2\CreateDhcpRequest;
use App\Http\Requests\V2\UpdateDhcpRequest;
use App\Jobs\Nsx\Dhcp\Undeploy;
use App\Models\V2\Dhcp;
use App\Resources\V2\DhcpResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use UKFast\DB\Ditto\QueryTransformer;

/**
 * Class DhcpController
 * @package App\Http\Controllers\V2
 */
class DhcpController extends BaseController
{
    /**
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, QueryTransformer $queryTransformer)
    {
        $collection = Dhcp::query();

        $queryTransformer->config(Dhcp::class)
            ->transform($collection);

        return DhcpResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param string $dhcpId
     * @return DhcpResource
     */
    public function show(string $dhcpId)
    {
        return new DhcpResource(
            Dhcp::findOrFail($dhcpId)
        );
    }

    /**
     * @param CreateDhcpRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(CreateDhcpRequest $request)
    {
        $dhcp = new Dhcp($request->only(['name', 'vpc_id', 'availability_zone_id']));
        try {
            if (!$dhcp->save()) {
                return $dhcp->getSyncError();
            }
        } catch (SyncException $exception) {
            return $dhcp->getSyncError();
        }
        return $this->responseIdMeta($request, $dhcp->id, 201);
    }

    /**
     * @param UpdateDhcpRequest $request
     * @param string $dhcpId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateDhcpRequest $request, string $dhcpId)
    {
        $dhcp = Dhcp::findOrFail($dhcpId);
        $dhcp->fill($request->only(['name']));
        try {
            if (!$dhcp->save()) {
                return $dhcp->getSyncError();
            }
        } catch (SyncException $exception) {
            return $dhcp->getSyncError();
        }
        return $this->responseIdMeta($request, $dhcp->id, 200);
    }

    public function destroy(string $dhcpId)
    {
        $model = Dhcp::findOrFail($dhcpId);
        try {
            $model->delete();
        } catch (SyncException $exception) {
            return $model->getSyncError();
        }
        return response()->json([], 204);
    }
}
