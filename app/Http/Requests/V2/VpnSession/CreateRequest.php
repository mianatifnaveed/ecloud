<?php
namespace App\Http\Requests\V2\VpnSession;

use App\Models\V2\VpnEndpoint;
use App\Models\V2\VpnProfileGroup;
use App\Models\V2\VpnService;
use App\Rules\V2\EitherOrNotBoth;
use App\Rules\V2\ExistsForUser;
use App\Rules\V2\IsResourceAvailable;
use App\Rules\V2\ValidCidrNetworkCsvString;
use App\Rules\V2\ValidIpv4;
use Illuminate\Validation\Rule;
use UKFast\FormRequests\FormRequest;

class CreateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string',
            'vpn_profile_group_id' => [
                'required',
                'string',
                Rule::exists(VpnProfileGroup::class, 'id')->whereNull('deleted_at'),
            ],
            'vpn_service_id' => [
                'required',
                'string',
                Rule::exists(VpnService::class, 'id')->whereNull('deleted_at'),
                new ExistsForUser(VpnService::class),
                new IsResourceAvailable(VpnService::class),
            ],
            'vpn_endpoint_id' => [
                'required',
                'string',
                Rule::exists(VpnEndpoint::class, 'id')->whereNull('deleted_at'),
                'distinct',
                new ExistsForUser(VpnEndpoint::class),
                new IsResourceAvailable(VpnEndpoint::class),
            ],
            'remote_ip' => [
                'required',
                'string',
                new ValidIpv4()
            ],
            'remote_networks' => [
                'required_without:local_networks',
                'string',
                new ValidCidrNetworkCsvString()
            ],
            'local_networks' => [
                'required_without:remote_networks',
                'string',
                new ValidCidrNetworkCsvString()
            ],
        ];
    }
}