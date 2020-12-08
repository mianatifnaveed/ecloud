<?php

namespace App\Http\Requests\V2\BillingMetric;

use App\Models\V2\Instance;
use App\Models\V2\Router;
use App\Models\V2\Volume;
use App\Models\V2\Vpc;
use App\Models\V2\Vpn;
use App\Rules\V2\ExistsForUser;
use UKFast\FormRequests\FormRequest;

class CreateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'resource_id' => [
                'required',
                'string',
                new ExistsForUser([
                    Instance::class,
                    Router::class,
                    Volume::class,
                    Vpn::class,
                ]),
            ],
            'vpc_id' => [
                'required',
                'string',
                new ExistsForUser([
                    Vpc::class,
                ]),
            ],
            'reseller_id' => ['required', 'numeric'],
            'key' => ['required', 'string'],
            'value' => ['required', 'string'],
            'start' => ['required', 'date'],
            'end' => ['date'],
        ];
    }
}