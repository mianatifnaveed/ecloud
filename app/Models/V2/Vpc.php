<?php

namespace App\Models\V2;

use App\Events\V2\Vpc\Deleting;
use App\Events\V2\Vpc\Saved;
use App\Events\V2\Vpc\Saving;
use App\Jobs\Vpc\UpdateSupportEnabledBilling;
use App\Traits\V2\CustomKey;
use App\Traits\V2\DefaultName;
use App\Traits\V2\DeletionRules;
use App\Traits\V2\Syncable;
use App\Traits\V2\Taskable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use UKFast\Api\Auth\Consumer;
use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filter;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;

class Vpc extends Model implements Filterable, Sortable, ResellerScopeable, RegionAble
{
    use HasFactory, CustomKey, SoftDeletes, DefaultName, DeletionRules, Syncable, Taskable;

    public $keyPrefix = 'vpc';
    public $incrementing = false;
    public $timestamps = true;
    protected $keyType = 'string';
    protected $connection = 'ecloud';
    protected $fillable = [
        'id',
        'name',
        'reseller_id',
        'region_id',
        'console_enabled',
        'advanced_networking',
    ];

    protected $dispatchesEvents = [
        'saving' => Saving::class,
        'saved' => Saved::class,
        'deleting' => Deleting::class,
    ];

    public $children = [
        'routers',
        'instances',
        'loadBalancers',
        'volumes',
        'floatingIps',
        'hostGroups',
    ];

    protected $casts = [
        'console_enabled' => 'bool',
        'advanced_networking' => 'bool',
    ];

    public function getResellerId(): int
    {
        return $this->reseller_id;
    }

    public function dhcps()
    {
        return $this->hasMany(Dhcp::class);
    }

    public function routers()
    {
        return $this->hasMany(Router::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function volumes()
    {
        return $this->hasMany(Volume::class);
    }

    public function floatingIps()
    {
        return $this->hasMany(FloatingIp::class);
    }

    public function loadBalancers()
    {
        return $this->hasMany(LoadBalancer::class);
    }

    public function hostGroups()
    {
        return $this->hasMany(HostGroup::class);
    }

    public function billingMetrics()
    {
        return $this->hasMany(BillingMetric::class);
    }


    /**
     * @param $query
     * @param $user
     * @return mixed
     */
    public function scopeForUser($query, Consumer $user)
    {
        if (!$user->isScoped()) {
            return $query;
        }
        return $query->where('reseller_id', '=', $user->resellerId());
    }

    /**
     * Get the vpc's support flag.
     *
     * @return string
     */
    public function getSupportEnabledAttribute()
    {
        return (bool) BillingMetric::getActiveByKey($this, UpdateSupportEnabledBilling::getKeyName());
    }

    /**
     * @param FilterFactory $factory
     * @return array|Filter[]
     */
    public function filterableColumns(FilterFactory $factory)
    {
        return [
            $factory->create('id', Filter::$stringDefaults),
            $factory->create('name', Filter::$stringDefaults),
            $factory->create('reseller_id', Filter::$stringDefaults),
            $factory->create('region_id', Filter::$stringDefaults),
            $factory->boolean()->create('console_enabled', '1', '0'),
            $factory->boolean()->create('support_enabled', '1', '0'),
            $factory->boolean()->create('advanced_networking', '1', '0'),
            $factory->create('created_at', Filter::$dateDefaults),
            $factory->create('updated_at', Filter::$dateDefaults),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort[]
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function sortableColumns(SortFactory $factory)
    {
        return [
            $factory->create('id'),
            $factory->create('name'),
            $factory->create('reseller_id'),
            $factory->create('region_id'),
            $factory->create('console_enabled'),
            $factory->create('support_enabled'),
            $factory->create('advanced_networking'),
            $factory->create('created_at'),
            $factory->create('updated_at'),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort|\UKFast\DB\Ditto\Sort[]|null
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function defaultSort(SortFactory $factory)
    {
        return [
            $factory->create('name', 'asc'),
        ];
    }

    /**
     * @return array|string[]
     */
    public function databaseNames()
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'reseller_id' => 'reseller_id',
            'region_id' => 'region_id',
            'console_enabled' => 'console_enabled',
            'advanced_networking' => 'advanced_networking',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }
}
