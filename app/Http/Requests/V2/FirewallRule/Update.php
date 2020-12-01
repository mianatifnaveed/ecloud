<?php

namespace App\Http\Requests\V2\FirewallRule;

use App\Models\V2\FirewallPolicy;
use App\Rules\V2\ExistsForUser;
use App\Rules\V2\ValidIpFormatCsvString;
use App\Rules\V2\ValidPortReference;
use UKFast\FormRequests\FormRequest;

class Update extends FormRequest
{
    /**
     * @return array
     */
    public function rules()
    {
        $firewallPortRules = (new \App\Http\Requests\V2\FirewallRulePort\Create)->rules();

        return [
            'name' => 'sometimes|required|string|max:50',
            'sequence' => 'sometimes|required|integer',
            'firewall_policy_id' => [
                'sometimes',
                'required',
                'string',
                'exists:ecloud.firewall_policies,id,deleted_at,NULL',
                new ExistsForUser(FirewallPolicy::class)
            ],
            'source' => [
                'sometimes',
                'nullable',
                'string',
                new ValidIpFormatCsvString()
            ],
            'destination' => [
                'sometimes',
                'nullable',
                'string',
                new ValidIpFormatCsvString()
            ],
            'action' => 'sometimes|required|string|in:ALLOW,DROP,REJECT',
            'direction' => 'sometimes|required|string|in:IN,OUT,IN_OUT',
            'enabled' => 'sometimes|required|boolean',
            'ports' => [
                'sometimes',
                'required',
                'array'
            ],
            'ports.*.protocol' => $firewallPortRules['protocol'],
            'ports.*.source' => $firewallPortRules['source'],
            'ports.*.destination' => $firewallPortRules['destination']
        ];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [
            'required' => 'The :attribute field is required',
            'string' => 'The :attribute field must contain a string',
            'name.max' => 'The :attribute field must be less than 50 characters',
            'firewall_policy_id.exists' => 'The specified :attribute was not found',
            'in' => 'The :attribute field contains an invalid option',
            'service_type.in' => 'The :attribute field must contain one of TCP or UDP',
            'enabled.boolean' => 'The :attribute field is not a valid boolean value',
            'sequence.integer' => 'The specified :attribute must be an integer',
        ];
    }
}