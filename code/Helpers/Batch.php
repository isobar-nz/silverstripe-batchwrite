<?php
use BatchWrite\MySQLiAdapter;
use BatchWrite\PDOAdapter;

/**
 * Class Batch
 */
class Batch
{
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
     */
    public function __construct()
    {
        $this->adapter = $this->getAdapter();
    }

    /**
     * @return MySQLiAdapter|PDOAdapter
     * @throws Exception
     */
    private function getAdapter()
    {
        // SS version >= 3.2
        if (class_exists('MySQLiConnector') && class_exists('PDOConnector')) {
            $connector = DB::get_connector();
            if ($connector instanceof MySQLiConnector) {
                $connProperty = new ReflectionProperty('MySQLiConnector', 'dbConn');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($connector);
                return new MySQLiAdapter($conn);
            } else if ($connector instanceof PDOConnector) {
                $connProperty = new ReflectionProperty('PDOConnector', 'pdoConnection');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($connector);
                return new PDOAdapter($conn);
            }
        } else {
            $db = DB::getConn();
            if ($db instanceof MySQLDatabase) {
                $connProperty = new ReflectionProperty('MySQLDatabase', 'dbConn');
                $connProperty->setAccessible(true);
                $conn = $connProperty->getValue($db);
                return new MySQLiAdapter($conn);
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
     * @param $dataObjects
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

            $types[$dataObject->class][$action][] = $dataObject;
        }

        foreach ($types as $className => $actions) {
            foreach ($actions as $action => $objects) {
                $classSingleton = singleton($className);
                $ancestry = array_filter($classSingleton->getClassAncestry(), function ($class) {
                    return DataObject::has_own_table($class);
                });

                $rootClass = array_shift($ancestry);

                $this->adapter->insertClass($rootClass, $objects, false, $action === 'update', $postfix);

                if ($action === 'insert') {
                    $sql = 'SELECT LAST_INSERT_ID() AS ID, ROW_COUNT() AS Count';

                    $row = DB::query($sql)->first();

                    // check count?
                    $id = intval($row['ID']);
                    foreach ($objects as $obj) {
                        $obj->setField('ID', $id);
                        $id++;
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
     * @param $sets
     * @throws Exception
     */
    public function writeManyMany($sets)
    {
        if (empty($sets)) {
            return;
        }

        $types = array();

        foreach ($sets as $set) {
            $types[$set[0]->class][$set[2]->class][] = $set;
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
     * @param $parent
     * @param $relation
     * @return array
     */
    private function getRelationFields($parent, $relation)
    {
        if (isset($this->relations[$parent->class][$relation])) {
            return $this->relations[$parent->class][$relation];
        }

        $ancestry = $parent->getClassAncestry();
        foreach ($ancestry as $parentClass) {
            $singleton = singleton($parentClass);
            $manyMany = $singleton->many_many();

            if (isset($manyMany[$relation])) {
                $belongsClass = $manyMany[$relation];
                if ($belongsClass ===  $parentClass) {
                    $belongsClass = 'Child';
                }
                $relationFields = array($parentClass . '_' . $relation, $parentClass . 'ID', $belongsClass . 'ID');
                $this->relations[$parent->class][$relation] = $relationFields;
                return $relationFields;
            }
        }

        // doesn't exist, should throw error
        return array($parent->class . 'ID', $relation, $parent->class . 'ID');
    }

    /**
     * @param $dataObjects
     */
    public function delete($dataObjects)
    {
        $types = array();

        foreach ($dataObjects as $dataObject) {
            $types[$dataObject->class][] = $dataObject->getField('ID');
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
     * @param $dataObjects
     */
    public function deleteFromStage($dataObjects)
    {
        $stages = array_slice(func_get_args(), 1);

        $types = array();

        foreach ($dataObjects as $dataObject) {
            $types[$dataObject->class][] = $dataObject->getField('ID');
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

        if ($postfix === 'Stage') {
            $postfix = '';
        }

        $singleton = singleton($className);
        $ancestry = array_reverse(array_filter($singleton->getClassAncestry(), function ($class) {
            return DataObject::has_own_table($class);
        }));

        $field = DBField::create_field('Int', null, 'ID');
        $ids = '(' . implode(', ', array_map(function ($id) use ($field) {
                $id = $id instanceof \DataObject ? $id->ID : $id;
                return $field->prepValueForDB($id);
            }, $ids)) . ')';

        foreach ($ancestry as $class) {
            $table = $class . ($postfix ? '_' . $postfix : '');
            $sql = "DELETE FROM `{$table}` WHERE ID IN {$ids}";
            DB::query($sql);
        }
    }
}
