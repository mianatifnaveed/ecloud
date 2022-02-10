<?php

namespace App\Http\Requests\V2\IpAddress;

use App\Models\V2\IpAddress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use UKFast\FormRequests\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ]
        ];

        if (Auth::user()->isAdmin()) {
            $rules['type'] = [
                'sometimes',
                'required',
                'string',
                Rule::in([IpAddress::TYPE_NORMAL,IpAddress::TYPE_CLUSTER])
            ];
        }
        return $rules;
    }
}
