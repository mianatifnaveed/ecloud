<?php

namespace App\Http\Requests\V2;

use App\Models\V2\Router;
use App\Models\V2\Vpc;
use App\Rules\V2\ExistsForUser;
use UKFast\FormRequests\FormRequest;

class UpdateRouterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return ($this->user()->isAdmin());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'sometimes|required|string',
            'throughput' => [
                'sometimes',
                'required',
                'integer',
                'in:' . implode(',', Router::THROUGHPUT_OPTIONS),
            ],
            'vpc_id' => [
                'sometimes',
                'required',
                'string',
                'exists:ecloud.vpcs,id,deleted_at,NULL',
                new ExistsForUser(Vpc::class)
            ],
            'availability_zone_id' => 'sometimes|required|string|exists:ecloud.availability_zones,id,deleted_at,NULL',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array|string[]
     */
    public function messages()
    {
        return [
            'name.required' => 'The :attribute field, when specified, cannot be null',
            'vpc_id.required' => 'The :attribute field, when specified, cannot be null',
            'vpc_id.exists' => 'The specified :attribute was not found',
            'availability_zone_id.required' => 'The :attribute field, when specified, cannot be null',
            'availability_zone_id.exists' => 'The specified :attribute was not found',
            'throughput.in' => 'The specified :attribute is not valid. Allowed values are ' . implode(',', Router::THROUGHPUT_OPTIONS)
        ];
    }
}
