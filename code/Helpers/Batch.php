<?php

class Batch
{
    private $connProperty;

    private $dataObjectRecordProperty;

    private $relations = array();

    public function __construct()
    {
        $this->connProperty = new ReflectionProperty('MySQLDatabase', 'dbConn');
        $this->connProperty->setAccessible(true);

        $this->dataObjectRecordProperty = new ReflectionProperty('DataObject', 'record');
        $this->dataObjectRecordProperty->setAccessible(true);
    }

    public function write($dataObjects)
    {
        if (empty($dataObjects)) {
            return;
        }

        foreach ($dataObjects as $dataObject) {
            $dataObject->onBeforeWrite();
        }

        $this->writeTablePostfix($dataObjects);

        foreach ($dataObjects as $dataObject) {
            $dataObject->onAfterWrite();
        }
    }

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

    private function writeTablePostfix($dataObjects, $postfix = '')
    {
        if ($postfix === 'Stage') {
            $postfix = '';
        }

        $types = array();

        $date = date('Y-m-d H:i:s');
        foreach ($dataObjects as $dataObject) {
            $fields = $this->dataObjectRecordProperty->getValue($dataObject);
            $action = 'insert';
            if (!empty($fields['ID'])) {
                $action = 'update';
            }

            if (empty($fields['Created'])) {
                $fields['Created'] = $date;
            }
            $fields['LastEdited'] = $date;
            $this->dataObjectRecordProperty->setValue($dataObject, $fields);

            $types[$dataObject->class][$action][] = $dataObject;
        }

        foreach ($types as $className => $actions) {
            foreach ($actions as $action => $objects) {
                $classSingleton = singleton($className);
                $ancestry = array_filter($classSingleton->getClassAncestry(), function ($class) {
                    return DataObject::has_own_table($class);
                });

                $rootClass = array_shift($ancestry);

                $table = $rootClass . ($postfix ? '_' . $postfix : '');
                $this->writeClassTable($objects, $rootClass, $table, false, $action === 'update');

                if ($action === 'insert') {
                    $sql = 'SELECT LAST_INSERT_ID() AS ID, ROW_COUNT() AS Count';

                    $row = DB::query($sql)->first();

                    // check count?
                    $id = intval($row['ID']);
                    foreach ($objects as $obj) {
                        $fields = $this->dataObjectRecordProperty->getValue($obj);
                        $fields['ID'] = $id;
                        $this->dataObjectRecordProperty->setValue($obj, $fields);
                        $id++;
                    }
                }

                foreach ($ancestry as $class) {
                    $table = $class . ($postfix ? '_' . $postfix : '');
                    $this->writeClassTable($objects, $class, $table, true, $action === 'update');
                }

                $objects[0]->flushCache();
            }
        }

        return $dataObjects;
    }

    private function writeClassTable($objects, $class, $table, $setID = false, $update = false)
    {
        $fields = DataObject::database_fields($class);

        $singleton = singleton($class);

        $fields = array_filter(array_keys($fields), function ($field) use ($singleton) {
            return $singleton->hasOwnTableDatabaseField($field);
        });

        if ($setID || $update) {
            array_unshift($fields, 'ID');
        }

        $typeLookup = array(
            'ID' => 'i',
        );
        foreach ($fields as $field) {
            $dbObject = $singleton->dbObject($field);
            if ($dbObject instanceof Boolean || $dbObject instanceof Int) {
                $typeLookup[$field] = 'i';
            } else if ($dbObject instanceof Float || $dbObject instanceof Decimal || $dbObject instanceof Money) {
                $typeLookup[$field] = 'd';
            } else {
                $typeLookup[$field] = 's';
            }
        }

        $inserts = array();
        $insert = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $types = '';
        $params = array();
        foreach ($objects as $obj) {
            $record = $this->dataObjectRecordProperty->getValue($obj);

            foreach ($fields as $field) {
                $type = $typeLookup[$field];
                $types .= $type;
                $value = isset($record[$field]) ? $record[$field] : $obj->getField($field);
                if (is_bool($value)) {
                    $value = (int)$value;
                }
                if ($type != 's' && !$value) {
                    $value = 0;
                }
                $params[] = $value;
            }
            $inserts[] = $insert;
        }
        array_unshift($params, $types);

        $columns = implode(', ', array_map(function ($name) {
            return "`{$name}`";
        }, $fields));

        $inserts = implode(',', $inserts);

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES {$inserts}";

        if ($update) {
            $mappings = array();
            foreach ($fields as $field) {
                if ($field !== 'ID') {
                    $mappings[] = "`{$field}` = VALUES(`{$field}`)";
                }
            }
            $mappings = implode(',', $mappings);
            $sql .= " ON DUPLICATE KEY UPDATE {$mappings}";
        }

        $this->executeQuery($sql, $params);
    }

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

                $types = '';
                $inserts = array();
                $insert = '(?,?)';
                $params = array();
                foreach ($sets as $set) {
                    $types .= 'ii';
                    $params[] = intval($set[0]->ID);
                    $params[] = intval($set[2]->ID);
                    $inserts[] = $insert;
                    // todo extra fields
                }
                array_unshift($params, $types);

                $columns = implode(',', array_map(function ($name) {
                    return "`{$name}`";
                }, $columns));

                $inserts = implode(',', $inserts);

                $sql = "INSERT INTO `{$tableName}` ({$columns}) VALUES {$inserts}";

                $this->executeQuery($sql, $params);
            }
        }
    }

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
                $relationFields = array($parentClass . '_' . $relation, $parentClass . 'ID', $manyMany[$relation] . 'ID');
                $this->relations[$parent->class][$relation] = $relationFields;
                return $relationFields;
            }
        }

        // doesn't exist, should throw error
        return array($parent->class . 'ID', $relation, $parent->class . 'ID');
    }

    private function executeQuery($sql, $params)
    {
        $conn = $this->connProperty->getValue(DB::getConn());
        $stmt = $conn->prepare($sql);

        $refs = array();
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }

        call_user_func_array(array($stmt, 'bind_param'), $refs);
        $stmt->execute();
    }

    public function delete($dataObjects)
    {
        $types = array();

        foreach ($dataObjects as $dataObject) {
            $fields = $this->dataObjectRecordProperty->getValue($dataObject);
            $types[$dataObject->class][] = $fields['ID'];
        }

        foreach ($types as $className => $ids) {
            $this->deleteTablePostfix($className, $ids);
        }
    }

    public function deleteIDs($className, $ids)
    {
        $this->deleteTablePostfix($className, $ids);
    }

    public function deleteFromStage($dataObjects)
    {
        $stages = array_slice(func_get_args(), 1);

        $types = array();

        foreach ($dataObjects as $dataObject) {
            $fields = $this->dataObjectRecordProperty->getValue($dataObject);
            $types[$dataObject->class][] = $fields['ID'];
        }

        foreach ($types as $className => $ids) {
            foreach ($stages as $stage) {
                $this->deleteTablePostfix($className, $ids, $stage);
            }
        }
    }

    public function deleteIDsFromStage($className, $ids)
    {
        $stages = array_slice(func_get_args(), 2);

        foreach ($stages as $stage) {
            $this->deleteTablePostfix($className, $ids, $stage);
        }
    }

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
