<?php

namespace App\Http\Requests\V2\DiscountPlan;

use UKFast\FormRequests\FormRequest;

/**
 * Class Create
 * @package App\Http\Requests\V2
 */
class Create extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
//            'contact_id' => 'sometimes|required_without:employee_id|exists:reseller.reseller_contact,reseller_contact_id',
//            'employee_id' => 'sometimes|required_without:contact_id|exists:holiday.employee,employee_id',
            'contact_id' => 'sometimes|required_without:employee_id|integer',
            'employee_id' => 'sometimes|required_without:contact_id|integer',
            'name' => 'required|string|max:255',
            'commitment_amount' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'commitment_before_discount' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'discount_rate' => 'required|numeric|min:0|max:100',
            'term_length' => 'required|integer|min:1',
            'term_start_date' => 'required|date|after_or_equal:today',
            'term_end_date' => 'required|date|after:today',
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
