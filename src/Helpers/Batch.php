<?php

namespace LittleGiant\BatchWrite\Helpers;

use Exception;
use http\Exception\RuntimeException;
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
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\Versioned\Versioned;

/**
 * Class Batch
 */
class Batch
{
    use Injectable;

    /**
     * @var array
     */
    private $relations = array();

    /**
     * @var MySQLiAdapter|PDOAdapter
     */
    private $adapter;

    /**
     *
     * @var int @@auto_increment_increment for the MySQL server
     */
    private static $autoIncrementIncrement;

    /**
     *
     */
    public function __construct()
    {
        $this->adapter = $this->getAdapter();
        if(!isset(self::$autoIncrementIncrement)){
            $result = DB::query('SELECT @@auto_increment_increment as increment');
            $row = $result->first();
            self::$autoIncrementIncrement = intval($row['increment']);
        }
    }

    /**
     * @return DBAdapter
     * @throws Exception
     */
    private function getAdapter()
    {
        if (class_exists(MySQLiConnector::class) && class_exists(PDOConnector::class)) {
            $connector = DB::get_connector();
            if ($connector instanceof MySQLiConnector) {
                $connProperty = new ReflectionProperty(MySQLiConnector::class, 'dbConn');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($connector);
                return MySQLiAdapter::create($conn);
            } else if ($connector instanceof PDOConnector) {
                $connProperty = new ReflectionProperty(PDOConnector::class, 'pdoConnection');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($connector);
                return PDOAdapter::create($conn);
            }
        } else {
            $db = DB::get_conn();
            if ($db instanceof MySQLDatabase) {
                $connProperty = new ReflectionProperty(MySQLDatabase::class, 'dbConn');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($db);
                return MySQLiAdapter::create($conn);
            }
        }

        throw new Exception('connection cannot be found');
    }

    /**
     * @param $dataObjects
     */
    public function write($dataObjects)
    {
        if (empty($dataObjects)) {
            return;
        }

        foreach ($dataObjects as $dataObject) {
            $onBeforeWriteMethod = new ReflectionMethod($dataObject, 'onBeforeWrite');
            $onBeforeWriteMethod->setAccessible(true);
            $onBeforeWriteMethod->invoke($dataObject);
        }

        $this->writeTablePostfix($dataObjects);

        foreach ($dataObjects as $dataObject) {
            $onBeforeWriteMethod = new ReflectionMethod($dataObject, 'onAfterWrite');
            $onBeforeWriteMethod->setAccessible(true);
            $onBeforeWriteMethod->invoke($dataObject);
        }
    }

    /**
     * @param $dataObjects
     */
    public function writeToStage($dataObjects)
    {
        if (empty($dataObjects)) {
            return;
        }

        $stages = func_get_args();
        array_shift($stages);

        foreach ($dataObjects as $dataObject) {
            $dataObject->onBeforeWrite();
        }

        foreach ($stages as $stage) {
            $this->writeTablePostfix($dataObjects, $stage);
        }

        foreach ($dataObjects as $dataObject) {
            $dataObject->onAfterWrite();
        }
    }

    /**
     * @param DataList|DataObject[] $dataObjects
     * @param string $postfix
     * @return mixed
     */
    private function writeTablePostfix($dataObjects, $postfix = '')
    {
        if ($postfix === 'Stage') {
            $postfix = '';
        }

        $types = array();

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
     * @param DataObject[][] $sets
     * @throws Exception
     */
    public function writeManyMany($sets)
    {
        if (empty($sets)) {
            return;
        }

        $types = array();

        foreach ($sets as $set) {
            $types[$set[0]->ClassName][$set[2]->ClassName][] = $set;
        }

        foreach ($types as $className => $belongs) {
            foreach ($belongs as $sets) {
                if (empty($sets)) {
                    continue;
                }

                $relationFields = $this->getRelationFields($sets[0][0], $sets[0][1]);

                $tableName = $relationFields[0];
                $columns = array($relationFields[1], $relationFields[2]);

//                $extraFields = $object->many_many_ExtraFields($relation);

                $inserts = array();
                $insert = '(?,?)';
                $params = array();
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
        $types = array();

        foreach ($dataObjects as $dataObject) {
            $types[$dataObject->ClassName][] = $dataObject->getField('ID');
        }

        foreach ($types as $className => $ids) {
            $this->deleteTablePostfix($className, $ids);
        }
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
     * @param DataList|DataObject[] $dataObjects
     */
    public function deleteFromStage($dataObjects)
    {
        $stages = array_slice(func_get_args(), 1);

        $types = array();

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
     * @param $className
     * @param $ids
     */
    public function deleteIDsFromStage($className, $ids)
    {
        $stages = array_slice(func_get_args(), 2);

        foreach ($stages as $stage) {
            $this->deleteTablePostfix($className, $ids, $stage);
        }
    }

    /**
     * @param $className
     * @param $ids
     * @param string $postfix
     */
    private function deleteTablePostfix($className, $ids, $postfix = '')
    {
        if (empty($ids)) {
            return;
        }

        $singleton = singleton($className);
        $dataObjectSchema = DataObject::getSchema();
        $ancestry = array_reverse(array_filter($singleton->getClassAncestry(), function ($class) use ($dataObjectSchema) {
            return $dataObjectSchema->classHasTable($class);
        }));

        $field = DBInt::create('ID');
        $ids = '(' . implode(', ', array_map(function ($id) use ($field) {
                $id = $id instanceof DataObject ? $id->ID : $id;
                return $field->prepValueForDB($id);
            }, $ids)) . ')';

        $postfix = empty($postfix) || $postfix === Versioned::DRAFT ? '' : "_{$postfix}";
        foreach ($ancestry as $class) {
            $table = $dataObjectSchema->baseDataTable($class) . $postfix;
            $sql = "DELETE FROM `{$table}` WHERE ID IN {$ids}";
            DB::query($sql);
        }
    }
}
