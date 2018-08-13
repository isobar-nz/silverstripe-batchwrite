<?php

namespace LittleGiant\BatchWrite\Adapters;

use Exception;
use LittleGiant\BatchWrite\Batch;
use mysqli;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBMoney;

/**
 * Class MySQLiAdapter
 * @package LittleGiant\BatchWrite\Adapters
 */
class MySQLiAdapter implements DBAdapter
{
    use Injectable;

    /**
     * @var mysqli
     */
    private $conn;

    /**
     * MySQLiAdapter constructor.
     * @param mysqli $conn
     */
    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @inheritdoc
     */
    public function query($sql, $params)
    {
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("{$this->conn->error} in query {$sql}", $this->conn->errno);
        }

        $stmt->bind_param(...$params);
        return $stmt->execute();
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

        // types need to be set
        $typeLookup = array(
            'ID' => 'i',
        );
        foreach ($fields as $field => $type) {
            if ($type === DBBoolean::class || $type === DBInt::class) {
                $typeLookup[$field] = 'i';
            } else if ($type === DBFloat::class || $type === DBDecimal::class || $type === DBMoney::class) {
                $typeLookup[$field] = 'd';
            } else {
                $typeLookup[$field] = 's';
            }
        }

        $fields = array_keys($fields);
        $typeString = '';
        $params = array();
        foreach ($objects as $obj) {
            foreach ($fields as $field) {
                $type = $typeLookup[$field];
                $typeString .= $type;
                $value = $obj->getField($field);
                if ($type === 'i') {
                    $value = intval($value);
                } else if ($type === 'd') {
                    $value = floatval($value);
                } else {
                    $value = '' . $value;
                }
                $params[] = $value;
            }
        }
        array_unshift($params, $typeString);

        $tablePostfix = Batch::getStageTableSuffix($tablePostfix);
        $table = $dataObjectSchema->tableName($className) . $tablePostfix;
        $columns = '`' . implode('`,`', $fields) . '`';

        // inserts
        $inserts = implode(',', array_fill(0, count($objects), '(' . implode(',', array_fill(0, count($fields), '?')) . ')'));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES {$inserts}";

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
        array_unshift($params, implode('', array_fill(0, count($params), 'i')));
        return $this->query($sql, $params);
    }
}
