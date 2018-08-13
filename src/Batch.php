<?php

namespace LittleGiant\BatchWrite;

use Exception;
use LittleGiant\BatchWrite\Adapters\DBAdapter;
use LittleGiant\BatchWrite\Adapters\MySQLiAdapter;
use LittleGiant\BatchWrite\Adapters\PDOAdapter;
use ReflectionMethod;
use ReflectionProperty;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\Connect\MySQLiConnector;
use SilverStripe\ORM\Connect\PDOConnector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\Versioned\Versioned;

/**
 * Class Batch
 * @package LittleGiant\BatchWrite
 */
class Batch
{
    use Injectable;

    /**
     * @var int @@auto_increment_increment for the MySQL server
     */
    protected static $autoIncrementIncrement = null;

    /**
     * @var array
     */
    private $relations = [];

    /**
     * @var DBAdapter
     */
    private $adapter;

    /**
     *
     */
    public function __construct()
    {
        $this->adapter = $this->getAdapter();

        if (static::$autoIncrementIncrement === null) {
            $result = DB::query('SELECT @@auto_increment_increment as increment');
            static::$autoIncrementIncrement = intval($result->value());
        }
    }

    /**
     * @return DBAdapter
     */
    private function getAdapter()
    {
        $connector = DB::get_connector();

        if ($connector instanceof MySQLiConnector) {
            $connProperty = new ReflectionProperty(MySQLiConnector::class, 'dbConn');
            $connProperty->setAccessible(true);
            $conn = $connProperty->getValue($connector);
            return MySQLiAdapter::create($conn);
        } elseif ($connector instanceof PDOConnector) {
            $connProperty = new ReflectionProperty(PDOConnector::class, 'pdoConnection');
            $connProperty->setAccessible(true);
            $conn = $connProperty->getValue($connector);
            return PDOAdapter::create($conn);
        } else {
            $db = DB::get_conn();
            if ($db instanceof MySQLDatabase) {
                $connProperty = new ReflectionProperty(MySQLDatabase::class, 'dbConn');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($db);
                return MySQLiAdapter::create($conn);
            }
        }

        throw new \RuntimeException('connection cannot be found');
    }

    /**
     * @param iterable|DataObject[] $dataObjects
     */
    public function write($dataObjects)
    {
        if (empty($dataObjects)) return;

        foreach ($dataObjects as $dataObject) {
            $this->onBeforeWrite($dataObject);
        }

        $this->writeTablePostfix($dataObjects);

        foreach ($dataObjects as $dataObject) {
            $this->onAfterWrite($dataObject);
        }
    }

    /**
     * @param DataObject $object
     * @return mixed
     */
    protected function onBeforeWrite(DataObject $object)
    {
        $onBeforeWriteMethod = new ReflectionMethod($object, 'onBeforeWrite');
        $onBeforeWriteMethod->setAccessible(true);
        return $onBeforeWriteMethod->invoke($object);
    }

    /**
     * @param iterable|DataObject[] $dataObjects
     * @param string $postfix
     * @return mixed
     */
    private function writeTablePostfix($dataObjects, $postfix = '')
    {
        $types = [];

        $date = date('Y-m-d H:i:s');
        foreach ($dataObjects as $dataObject) {
            $action = 'insert';
            if ($dataObject->getField('ID')) {
                $action = 'update';
            }

            if (!$dataObject->getField('Created')) {
                $dataObject->setField('Created', $date);
            }
            $dataObject->setField('LastEdited', $date);

            $types[$dataObject->ClassName][$action][] = $dataObject;
        }

        foreach ($types as $className => $actions) {
            $classSingleton = singleton($className);
            $ancestry = array_filter($classSingleton->getClassAncestry(), function ($class) {
                return DataObject::getSchema()->classHasTable($class);
            });
            $rootClass = array_shift($ancestry);

            foreach ($actions as $action => $objects) {
                /** @var DataObject[] $objects */
                $this->adapter->insertClass($rootClass, $objects, false, $action === 'update', $postfix);

                if ($action === 'insert') {
                    $sql = 'SELECT LAST_INSERT_ID() AS ID, ROW_COUNT() AS Count';

                    $row = DB::query($sql)->first();

                    // check count?
                    $id = intval($row['ID']);
                    foreach ($objects as $obj) {
                        $obj->setField('ID', $id);
                        $id += self::$autoIncrementIncrement;
                    }
                }

                foreach ($ancestry as $class) {
                    $this->adapter->insertClass($class, $objects, true, $action === 'update', $postfix);
                }

                $objects[0]->flushCache();
            }
        }

        return $dataObjects;
    }

    /**
     * @param DataObject $object
     * @return mixed
     */
    protected function onAfterWrite(DataObject $object)
    {
        $onAfterWriteMethod = new ReflectionMethod($object, 'onAfterWrite');
        $onAfterWriteMethod->setAccessible(true);
        return $onAfterWriteMethod->invoke($object);
    }

    /**
     * @param iterable|DataObject[] $dataObjects
     * @param string[] $stages
     */
    public function writeToStage($dataObjects, ...$stages)
    {
        if (empty($dataObjects)) return;

        foreach ($dataObjects as $dataObject) {
            $this->onBeforeWrite($dataObject);
        }

        foreach ($stages as $stage) {
            $this->writeTablePostfix($dataObjects, $stage);
        }

        foreach ($dataObjects as $dataObject) {
            $this->onAfterWrite($dataObject);
        }
    }

    /**
     * @param DataObject[][] $sets
     * @throws Exception
     */
    public function writeManyMany($sets)
    {
        if (empty($sets)) return;

        $types = [];

        foreach ($sets as $set) {
            $types[$set[0]->ClassName][$set[2]->ClassName][] = $set;
        }

        foreach ($types as $className => $belongs) {
            foreach ($belongs as $sets) {
                if (empty($sets)) continue;

                $relationFields = $this->getRelationFields($sets[0][0], $sets[0][1]);

                $tableName = $relationFields[0];
                $columns = [$relationFields[1], $relationFields[2]];

//                $extraFields = $object->many_many_ExtraFields($relation);

                $inserts = [];
                $insert = '(?,?)';
                $params = [];
                foreach ($sets as $set) {
                    $params[] = intval($set[0]->getField('ID'));
                    $params[] = intval($set[2]->getField('ID'));
                    $inserts[] = $insert;
                    // todo extra fields
                }

                $columns = implode(',', array_map(function ($name) {
                    return "`{$name}`";
                }, $columns));

                $inserts = implode(',', $inserts);

                $sql = "INSERT INTO `{$tableName}` ({$columns}) VALUES {$inserts}";

                $this->adapter->insertManyMany($sql, $params);
            }
        }
    }

    /**
     * @param DataObject $parent
     * @param $relation
     * @return array
     */
    private function getRelationFields($parent, $relation)
    {
        if (isset($this->relations[$parent->ClassName][$relation])) {
            return $this->relations[$parent->ClassName][$relation];
        }

        $dataObjectSchema = DataObject::getSchema();
        $manyMany = $dataObjectSchema->manyManyComponent($parent, $relation);

        if ($manyMany === null) {
            throw new RuntimeException(); // TODO
        }

        $relationFields = [$manyMany['join'], $manyMany['parentField'], $manyMany['childField']];
        $this->relations[$parent->ClassName][$relation] = $relationFields;
        return $relationFields;
    }

    /**
     * @param DataList|DataObject[] $dataObjects
     */
    public function delete($dataObjects)
    {
        $types = [];

        foreach ($dataObjects as $dataObject) {
            $types[$dataObject->ClassName][] = $dataObject->getField('ID');
        }

        foreach ($types as $className => $ids) {
            $this->deleteTablePostfix($className, $ids);
        }
    }

    /**
     * @param string $className
     * @param iterable|int[] $ids
     * @param string $stage
     */
    private function deleteTablePostfix($className, $ids, $stage = '')
    {
        if (empty($ids)) return;

        $dataObjectSchema = DataObject::getSchema();
        $ancestry = ClassInfo::ancestry($className, true);

        $stage = static::getStageTableSuffix($stage);
        $ids = '(' . implode(',', $ids) . ')';

        foreach ($ancestry as $class) {
            $table = $dataObjectSchema->tableName($class) . $stage;
            DB::query("DELETE FROM \"{$table}\" WHERE \"ID\" IN {$ids}");
        }
    }

    /**
     * @param string $stage
     * @return string
     */
    public static function getStageTableSuffix($stage)
    {
        if (empty($stage) || $stage === Versioned::DRAFT) return '';
        return strpos($stage, '_') === false ? "_{$stage}" : $stage;
    }

    /**
     * @param $className
     * @param $ids
     */
    public function deleteIDs($className, $ids)
    {
        $this->deleteTablePostfix($className, $ids);
    }

    /**
     * @param iterable|DataObject[] $dataObjects
     * @param string[] $stages
     */
    public function deleteFromStage($dataObjects, ...$stages)
    {
        $types = [];

        foreach ($dataObjects as $dataObject) {
            $types[$dataObject->ClassName][] = $dataObject->getField('ID');
        }

        foreach ($types as $className => $ids) {
            foreach ($stages as $stage) {
                $this->deleteTablePostfix($className, $ids, $stage);
            }
        }
    }

    /**
     * @param string $className
     * @param iterable|int[] $ids
     * @param string[] $stages
     */
    public function deleteIDsFromStage($className, $ids, ...$stages)
    {
        foreach ($stages as $stage) {
            $this->deleteTablePostfix($className, $ids, $stage);
        }
    }
}
