<?php

namespace App\Http\Controllers\V2;

use App\Http\Requests\V2\DiscountPlan\Create;
use App\Http\Requests\V2\DiscountPlan\Update;
use App\Models\V2\DiscountPlan;
use App\Resources\V2\DiscountPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UKFast\DB\Ditto\QueryTransformer;

/**
 * Class DiscountPlanController
 * @package App\Http\Controllers\V2
 */
class DiscountPlanController extends BaseController
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $collection = DiscountPlan::forUser($request->user);
        (new QueryTransformer($request))
            ->config(DiscountPlan::class)
            ->transform($collection);

        return DiscountPlanResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param Request $request
     * @param string $discountPlanId
     * @return DiscountPlanResource
     */
    public function show(Request $request, string $discountPlanId)
    {
        return new DiscountPlanResource(
            DiscountPlan::forUser($request->user)->findOrFail($discountPlanId)
        );
    }

    /**
     * @param Create $request
     * @return JsonResponse
     */
    public function store(Create $request)
    {
        $discountPlan = new DiscountPlan($request->only($this->getAllowedFields()));
        if (!$request->has('term_end_date')) {
            $discountPlan->term_end_date = $this->calculateNewEndDate(
                $request->get('term_start_date'),
                $request->get('term_length')
            );
        }
        if (!$request->has('reseller_id')) {
            $discountPlan->reseller_id = $this->resellerId;
        }
        $discountPlan->save();
        return $this->responseIdMeta($request, $discountPlan->getKey(), 201);
    }

    /**
     * @param Update $request
     * @param string $discountPlanId
     * @return JsonResponse
     */
    public function update(Update $request, string $discountPlanId)
    {
        $discountPlan = DiscountPlan::forUser(app('request')->user)->findOrFail($discountPlanId);
        $discountPlan->update($request->only($this->getAllowedFields()));

        if ($this->isAdmin) {
            $discountPlan->reseller_id = $request->input('reseller_id', $discountPlan->reseller_id);
        }

        // if start date specified then use existing term_length or newly submitted one
        if ($request->has('term_start_date')) {
            $termLength = $request->get('term_length', $discountPlan->term_length);
            $discountPlan->term_end_date = $this->calculateNewEndDate(
                $request->get('term_start_date'),
                $termLength
            );
        }

        // if term_length specified but no start date, then auto calculate new term_end_date based on existing start_date
        if (!$request->has('term_start_date') && $request->has('term_length')) {
            $discountPlan->term_end_date = $this->calculateNewEndDate(
                $discountPlan->term_start_date,
                $request->has('term_length')
            );
        }

        $discountPlan->save();
        return $this->responseIdMeta($request, $discountPlan->getKey(), 200);
    }

    /**
     * @param string $discountPlanId
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy(string $discountPlanId)
    {
        $discountPlan = DiscountPlan::forUser(app('request')->user)->findOrFail($discountPlanId);
        $discountPlan->delete();
        return response()->json([], 204);
    }

    /**
     * @return string[]
     */
    private function getAllowedFields(): array
    {
        $allowedFields = [
            'name',
            'commitment_amount',
            'commitment_before_discount',
            'discount_rate',
            'term_length',
            'term_start_date',
            'term_end_date',
        ];
        if (app('request')->user->isAdministrator) {
            $allowedFields[] = 'contact_id';
            $allowedFields[] = 'employee_id';
            $allowedFields[] = 'reseller_id';
        }
        return $allowedFields;
    }

    /**
     * @param $startDate
     * @param $termLength
     * @return string
     */
    private function calculateNewEndDate($startDate, $termLength): string
    {
        return date('Y-m-d', strtotime('+ '.$termLength.' months', strtotime($startDate)));
    }
}