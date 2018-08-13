<?php

namespace LittleGiant\BatchWrite\Adapters;

use LittleGiant\BatchWrite\Batch;
use PDO;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBInt;

/**
 * Class PDOAdapter
 * @package LittleGiant\BatchWrite\Adapters
 */
class PDOAdapter implements DBAdapter
{
    use Injectable;

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
     * @inheritdoc
     */
    public function query($sql, $params)
    {
        $stmt = $this->conn->prepare($sql);
        $res = $stmt->execute($params);
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function insertClass($className, $objects, $setID = false, $isUpdate = false, $tablePostfix = '')
    {
        $dataObjectSchema = DataObject::getSchema();
        $fields = $dataObjectSchema->databaseFields($className, false);

        if (!$setID && !$isUpdate) {
            unset($fields['ID']);
        }

        $params = array();
        foreach ($objects as $object) {
            foreach ($fields as $field => $type) {
                $value = $object->getField($field);
                // need to fill in null values with appropriate values
                // TODO is there a better way to figure out if a value needs to be filled in?
                if ($value === null) {
                    if ($type === DBInt::class || $type === DBDecimal::class || $type === DBFloat::class) {
                        $value = 0;
                    } else {
                        $value = '';
                    }
                }
                $params[] = $value;
            }
        }

        $fields = array_keys($fields);
        $tablePostfix = Batch::getStageTableSuffix($tablePostfix);
        $tableName = $dataObjectSchema->tableName($className) . $tablePostfix;

        //  (`Field1`, `Field2`, ...)
        $fieldSQL = '`' . implode('`,`', $fields) . '`';

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
     * @inheritdoc
     */
    public function insertManyMany($sql, $params)
    {
        return $this->query($sql, $params);
    }
}
