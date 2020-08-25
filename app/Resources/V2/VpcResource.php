<?php
namespace App\Resources\V2;

use Illuminate\Support\Carbon;
use UKFast\Responses\UKFastResource;

/**
 * Class VirtualPrivateCloudResource
 * @package App\Http\Resources\V2
 * @property string id
 * @property string name
 * @property string reseller_id
 * @property string region_id
 * @property string created_at
 * @property string updated_at
 */
class VpcResource extends UKFastResource
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $data = [
            'id'         => $this->id,
            'name'       => $this->name,
            'region_id' => $this->region_id,
            'created_at' => Carbon::parse(
                $this->created_at,
                new \DateTimeZone(config('app.timezone'))
            )->toIso8601String(),
            'updated_at' => Carbon::parse(
                $this->updated_at,
                new \DateTimeZone(config('app.timezone'))
            )->toIso8601String(),
        ];

        if ($request->user->isAdministrator) {
            $data['reseller_id'] = $this->reseller_id;
        }

        return $data;
    }
}