<?php

namespace BatchWrite;

/**
 * Interface DBAdapter
 * @package BatchWrite
 */
interface DBAdapter
{
    /**
     * @param $sql
     * @param $params
     * @return mixed
     */
    public function query($sql, $params);

    /**
     * @param $className
     * @param $objects
     * @param bool|false $setID
     * @param bool|false $isUpdate
     * @param string $tablePostfix
     * @return mixed
     */
    public function insertClass($className, $objects, $setID = false, $isUpdate = false, $tablePostfix = '');

    /**
     * @param $sql
     * @param $params
     * @return mixed
     */
    public function insertManyMany($sql, $params);
}
