<?php

namespace App\Models\V2;

use App\Services\V2\KingpinService;
use App\Traits\V2\CustomKey;
use App\Traits\V2\DefaultAvailabilityZone;
use App\Traits\V2\DefaultName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filter;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;

/**
 * Class Instance
 * @package App\Models\V2
 * @method static find(string $routerId)
 * @method static findOrFail(string $routerUuid)
 */
class Instance extends Model implements Filterable, Sortable
{
    use CustomKey, SoftDeletes, DefaultName, DefaultAvailabilityZone;

    public $keyPrefix = 'i';
    public $incrementing = false;
    public $timestamps = true;
    protected $keyType = 'string';
    protected $connection = 'ecloud';
    protected $fillable = [
        'id',
        'name',
        'vpc_id',
        'appliance_version_id',
        'vcpu_cores',
        'ram_capacity',
        'availability_zone_id',
        'locked',
    ];

    protected $hidden = [
        'appliance_version_id'
    ];

    protected $appends = [
        'appliance_id',
        'power_state',
    ];

    protected $casts = [
        'locked' => 'boolean',
    ];

    public function vpc()
    {
        return $this->belongsTo(Vpc::class);
    }

    public function credentials()
    {
        return $this->hasMany(Credential::class, 'resource_id', 'id');
    }

    public function availabilityZone()
    {
        return $this->belongsTo(AvailabilityZone::class);
    }

    public function getPowerStateAttribute()
    {
        try {
            $response = app()->make(KingpinService::class, [$this->availabilityZone])
                ->get('/api/v2/vpc/' . $this->vpc_id . '/instance/' . $this->getKey());
        } catch (\Exception $e) {
            dd($e->getMessage());
            return;
        }
        return json_decode($response)->powerState;
    }

    public function scopeForUser($query, $user)
    {
        if (!empty($user->resellerId)) {
            $query->whereHas('vpc', function ($query) use ($user) {
                $resellerId = filter_var($user->resellerId, FILTER_SANITIZE_NUMBER_INT);
                if (!empty($resellerId)) {
                    $query->where('reseller_id', '=', $resellerId);
                }
            });
        }
        return $query;
    }

    public function getApplianceIdAttribute()
    {
        $versions = $this->applianceVersions()
            ->first();
        if (!$versions) {
            return;
        }
        return $versions->appliance
            ->appliance_uuid;
    }

    public function applianceVersions()
    {
        return $this->belongsTo(
            ApplianceVersion::class,
            'appliance_version_id',
            'appliance_version_uuid'
        );
    }

    public function setApplianceVersionId(string $applianceUuid)
    {
        $version = (new ApplianceVersion)->getLatest($applianceUuid);
        $this->attributes['appliance_version_id'] = $version;
    }

    /**
     * @param  \UKFast\DB\Ditto\Factories\FilterFactory  $factory
     * @return array|\UKFast\DB\Ditto\Filter[]
     */
    public function filterableColumns(FilterFactory $factory)
    {
        return [
            $factory->create('id', Filter::$stringDefaults),
            $factory->create('name', Filter::$stringDefaults),
            $factory->create('vpc_id', Filter::$stringDefaults),
            $factory->create('appliance_version_id', Filter::$stringDefaults),
            $factory->create('vcpu_cores', Filter::$stringDefaults),
            $factory->create('ram_capacity', Filter::$stringDefaults),
            $factory->create('availability_zone_id', Filter::$stringDefaults),
            $factory->create('locked', Filter::$stringDefaults),
            $factory->create('created_at', Filter::$dateDefaults),
            $factory->create('updated_at', Filter::$dateDefaults),
        ];
    }

    /**
     * @param  \UKFast\DB\Ditto\Factories\SortFactory  $factory
     * @return array|\UKFast\DB\Ditto\Sort[]
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function sortableColumns(SortFactory $factory)
    {
        return [
            $factory->create('id'),
            $factory->create('name'),
            $factory->create('vpc_id'),
            $factory->create('appliance_version_id'),
            $factory->create('vcpu_cores'),
            $factory->create('ram_capacity'),
            $factory->create('availability_zone_id'),
            $factory->create('locked'),
            $factory->create('created_at'),
            $factory->create('updated_at'),
        ];
    }

    /**
     * @param  \UKFast\DB\Ditto\Factories\SortFactory  $factory
     * @return array|\UKFast\DB\Ditto\Sort|\UKFast\DB\Ditto\Sort[]|null
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function defaultSort(SortFactory $factory)
    {
        return [
            $factory->create('created_at', 'desc'),
        ];
    }

    /**
     * @return array|string[]
     */
    public function databaseNames()
    {
        return [
            'id'                   => 'id',
            'name'                 => 'name',
            'vpc_id'               => 'vpc_id',
            'appliance_version_id' => 'appliance_version_id',
            'vcpu_cores'           => 'vcpu_cores',
            'ram_capacity'         => 'ram_capacity',
            'availability_zone_id' => 'availability_zone_id',
            'locked'     => 'locked',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }
}
