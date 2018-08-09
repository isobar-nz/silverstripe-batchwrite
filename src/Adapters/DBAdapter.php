<?php

namespace LittleGiant\BatchWrite\Adapters;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Interface DBAdapter
 * @package LittleGiant\BatchWrite\Adapters
 */
interface DBAdapter
{
    /**
     * @param string $sql
     * @param array $params
     * @return bool
     */
    public function query($sql, $params);

    /**
     * @param string $className
     * @param DataList|DataObject[] $objects
     * @param bool $setID
     * @param bool $isUpdate
     * @param string $tablePostfix
     * @return bool
     */
    public function insertClass($className, $objects, $setID = false, $isUpdate = false, $tablePostfix = '');

    /**
     * @param string $sql
     * @param array $params
     * @return bool
     */
    public function insertManyMany($sql, $params);
}
