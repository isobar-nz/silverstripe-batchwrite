<?php

namespace LittleGiant\SilverStripe\BatchWrite\Adapters;

use PDO;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBInt;

/**
 * Class PDOAdapter
 * @package LittleGiant\SilverStripe\BatchWrite\Adapters
 */
class PDOAdapter implements DBAdapter
{
    /**
     * @var PDO
     */
    private $conn;

    /**
     * PDOAdapter constructor.
     * @param PDO $conn
     */
    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @param $sql
     * @param $params
     * @return bool
     */
    public function query($sql, $params)
    {
        $stmt = $this->conn->prepare($sql);
        $res = $stmt->execute($params);
        return $res;
    }

    /**
     * @param $className
     * @param $objects
     * @param bool|false $setID
     * @param bool|false $isUpdate
     * @param string $tablePostfix
     * @return bool
     */
    public function insertClass($className, $objects, $setID = false, $isUpdate = false, $tablePostfix = '')
    {
        $fields = DataObject::database_fields($className);
        $singleton = singleton($className);

        $fields = array_filter(array_keys($fields), function ($field) use ($singleton) {
            return $singleton->hasOwnTableDatabaseField($field);
        });

        // if setting ID then add to fields
        if ($setID || $isUpdate) {
            array_unshift($fields, 'ID');
        }

        $fieldObjects = array();
        foreach ($fields as $field) {
            $fieldObjects[$field] = $singleton->dbObject($field);
        }

        $params = array();
        foreach ($objects as $object) {
            foreach ($fields as $field) {
                $value = $object->getField($field);
                // need to fill in null values with appropriate values
                // TODO is there a better way to figure out if a value needs to be filled in?
                if ($value === null) {
                    if ($fieldObjects[$field] instanceof DBInt ||
                        $fieldObjects[$field] instanceof DBDecimal ||
                        $fieldObjects[$field] instanceof DBFloat) {
                        $value = 0;
                    } else {
                        $value = '';
                    }
                }
                $params[] = $value;
            }
        }

        // ClassName or ClassName_Live
        $tableName = $className . ($tablePostfix ? '_' . $tablePostfix : '');

        //  (`Field1`, `Field2`, ...)
        $fieldSQL = implode(', ', array_map(function ($field) {
            return "`{$field}`";
        }, $fields));

        // (?, ?, ?, ?), (?, ...), ....
        $inserts = implode(',', array_fill(0, count($objects), '(' . implode(',', array_fill(0, count($fields), '?')) . ')'));

        $sql = "INSERT INTO `{$tableName}` ({$fieldSQL}) VALUES {$inserts}";

        if ($isUpdate) {
            $mappings = array();
            foreach ($fields as $field) {
                if ($field !== 'ID') {
                    $mappings[] = "`{$field}` = VALUES(`{$field}`)";
                }
            }
            $mappings = implode(',', $mappings);
            $sql .= " ON DUPLICATE KEY UPDATE {$mappings}";
        }

        return $this->query($sql, $params);
    }

    /**
     * @param $sql
     * @param $params
     * @return mixed
     */
    public function insertManyMany($sql, $params)
    {
        return $this->query($sql, $params);
    }
}
