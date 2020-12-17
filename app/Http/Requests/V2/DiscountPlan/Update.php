<?php

namespace App\Http\Requests\V2\DiscountPlan;

use App\Rules\V2\CommitmentIsGreater;
use UKFast\FormRequests\FormRequest;

/**
 * Class Update
 * @package App\Http\Requests\V2
 */
class Update extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $discountPlanId = app('request')->route('discountPlanId');
        return [
            'name' => 'sometimes|required|string|max:255',
            'commitment_amount' => [
                'sometimes',
                'required',
                'numeric',
                'regex:/^\d+(\.\d{1,2})?$/',
                new CommitmentIsGreater($discountPlanId)
            ],
            'commitment_before_discount' => [
                'sometimes',
                'required',
                'numeric',
                'regex:/^\d+(\.\d{1,2})?$/',
                new CommitmentIsGreater($discountPlanId)
            ],
            'discount_rate' => 'sometimes|required|numeric|min:0|max:100',
            'term_length' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                new CommitmentIsGreater($discountPlanId)
            ],
            'term_start_date' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:today',
                new CommitmentIsGreater($discountPlanId)
            ],
            'term_end_date' => [
                'sometimes',
                'required',
                'date',
                'after:today',
                new CommitmentIsGreater($discountPlanId)
            ],
        ];
    }

    public function messages()
    {
        return [
            'commitment_amount.regex' => 'The :attribute field is not a valid monetary value',
            'term_start_date.after_or_equal' => 'The :attribute field cannot be a date in the past',
            'term_end_date.after' => 'The :attribute field must be a date after today',
        ];
    }
}
