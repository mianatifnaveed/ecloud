<?php

namespace App\Models\V2;

use App\Events\V2\Host\Deleted;
use App\Events\V2\Host\Deleting;
use App\Events\V2\Host\Saved;
use App\Events\V2\Host\Saving;
use App\Traits\V2\CustomKey;
use App\Traits\V2\DefaultName;
use App\Traits\V2\Syncable;
use App\Traits\V2\Taskable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use UKFast\Api\Auth\Consumer;
use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filter;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;

/**
 * Class Host
 * @package App\Models\V2
 */
class Host extends Model implements Filterable, Sortable
{
    use CustomKey, SoftDeletes, DefaultName, Syncable, Taskable;

    public string $keyPrefix = 'h';

    protected $dispatchesEvents = [
        'deleted' => Deleted::class,
        'deleting' => Deleting::class,
        'saved' => Saved::class,
        'saving' => Saving::class,
    ];

    public function __construct(array $attributes = [])
    {
        $this->incrementing = false;
        $this->keyType = 'string';
        $this->connection = 'ecloud';

        $this->fillable([
            'id',
            'name',
            'host_group_id',
        ]);

        $this->dispatchesEvents = [
            'saving' => Saving::class,
            'saved' => Saved::class,
            'deleting' => Deleting::class,
            'deleted' => Deleted::class,
        ];

        parent::__construct($attributes);
    }

    public function hostGroup()
    {
        return $this->belongsTo(HostGroup::class);
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
        return $query->whereHas('hostGroup.vpc', function ($query) use ($user) {
            $query->where('reseller_id', $user->resellerId());
        });
    }

    /**
     * @param FilterFactory $factory
     * @return array|Filter[]
     */
    public function filterableColumns(FilterFactory $factory): array
    {
        return [
            $factory->create('id', Filter::$stringDefaults),
            $factory->create('name', Filter::$stringDefaults),
            $factory->create('host_group_id', Filter::$stringDefaults),
            $factory->create('created_at', Filter::$dateDefaults),
            $factory->create('updated_at', Filter::$dateDefaults),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort[]
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function sortableColumns(SortFactory $factory): array
    {
        return [
            $factory->create('id'),
            $factory->create('name'),
            $factory->create('host_group_id'),
            $factory->create('created_at'),
            $factory->create('updated_at'),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort|\UKFast\DB\Ditto\Sort[]|null
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function defaultSort(SortFactory $factory): array
    {
        return [
            $factory->create('created_at', 'desc'),
        ];
    }

    public function databaseNames(): array
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'host_group_id' => 'host_group_id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }
}
