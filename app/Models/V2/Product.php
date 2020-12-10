<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filter;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;

/**
 * Class Product
 * @package App\Models\V1
 */
class Product extends Model implements Filterable, Sortable
{
    protected $connection = 'reseller';
    protected $table = 'product';
    protected $primaryKey = 'product_id';
    public $timestamps = false;

    const PRODUCT_CATEGORIES = [
        'Compute',
        'Networking',
        'Storage',
        'License'
    ];

    /**
     * Apply a scope/filter to ** ALL ** Queries using this model to only return eCloud v2 products
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(function (Builder $builder) {
            return $builder->whereIn('product_subcategory', self::PRODUCT_CATEGORIES)
                ->where('product_active', 'Yes');
        });
    }

    public function productPrice()
    {
        return $this->hasMany(ProductPrice::class, 'product_price_product_id');
    }

    public function getPriceAttribute()
    {
        $productPrice = $this->productPrice()->where('product_price_type', 'Standard')->first();
        return $productPrice ? $productPrice->product_price_sale_price : null;
    }

    public function getNameAttribute()
    {
        preg_match("/(az-\w+[^:])(:\s)(\S[^-]+)/", $this->attributes['product_name'], $matches);
        return str_replace(' ', '_', $matches[3] ?? null);
    }

    public function getAvailabilityZoneIdAttribute()
    {
        preg_match("/(az-\w+[^:])(:\s)(\S[^-]+)/", $this->attributes['product_name'], $matches);
        return $matches[1] ?? null;
    }

    public function scopeForAvailabilityZone($query, AvailabilityZone $availabilityZone)
    {
        return $query->where('product_name', 'like', $availabilityZone->getKey() . '%');
    }

    public function scopeForRegion($query, Region $region)
    {
        foreach ($region->availabilityZones() as $availabilityZone) {
            $query->orWhere('product_name', 'like', $availabilityZone->getKey() . '%');
        }

        return $query;
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
            $factory->create('category', Filter::$stringDefaults),
            $factory->create('availability_zone_id', Filter::$stringDefaults)
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
            $factory->create('category'),
            $factory->create('availability_zone_id')
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
            'id' => 'product_id',
            'name' => 'product_name', // computed from product_name
            'category' => 'product_subcategory',
            'availability_zone_id' => 'product_name' // computed from product_name
        ];
    }

    /**
     * Transform request query parameters (filters) to work for the computed properties of this resource.
     * @param Request $request
     * @return Request
     */
    public static function transformRequest(Request $request) : Request
    {
        if (!empty($request->query)) {
            foreach ($request->query() as $key => $val) {
                $parts = explode(':', $key);
                if ($parts[1] == 'eq') {
                    $request->query->remove($key);
                    $request->query->add([$parts[0] . ':lk' => '*' . $val . '*']);
                }
            }
        }
        return $request;
    }
}