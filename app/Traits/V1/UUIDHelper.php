<?php

namespace App\Traits\V1;

use Ramsey\Uuid\Uuid;

/**
 * Trait UUIDHelper
 *
 * UUIDHelper related Model functionality
 *
 */
trait UUIDHelper
{
    /**
     * Create and save UUID on saving a new record
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function save(array $options = []) {

        $uuidColumn = $this->getUuidColumnName();

        if (empty($this->{$uuidColumn})) {
            $this->{$uuidColumn} = Uuid::uuid4()->toString();
        }

        return parent::save($options);
    }


    /**
     * Scope a query by UUID
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $uuid
     * @return \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeWithUuid($query, $uuid)
    {
        if (!empty($uuid)) {
            $query->where($this->getUuidColumnName(), $uuid);
        }

        return $query;
    }

    /**
     * Determine the table's UUID column
     *
     * Note: Assumes table_name.table_name_uuid unless told otherwise
     * @return string
     */
    public function getUuidColumnName()
    {
        return (isset($this->uuidColumn) ? $this->uuidColumn :  "{$this->table}_uuid");
    }

}