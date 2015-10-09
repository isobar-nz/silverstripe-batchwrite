<?php

class Batch
{
    public function write($dataObjects)
    {
        if (empty($dataObjects)) {
            return;
        }

        return $this->writeTablePostfix($dataObjects);
    }

    public function writeToStage($dataObjects)
    {
        if (empty($dataObjects)) {
            return;
        }

        $stages = func_get_args();
        array_shift($stages);

        foreach ($stages as $stage) {
            $this->writeTablePostfix($dataObjects, $stage);
        }

        return;
    }

    private function writeTablePostfix($dataObjects, $postfix = '')
    {
        if ($postfix === 'Stage') {
            $postfix = '';
        }

        $types = array();

        foreach ($dataObjects as $dataObject) {
            $dataObject->onBeforeWrite();

            $action = 'insert';
            if ($dataObject->exists()) {
                $action = 'update';
            }

            $types[$dataObject->ClassName][$action][] = $dataObject;
        }

        foreach ($types as $className => $actions) {
            foreach ($actions as $action => $objects) {

                $classSingleton = singleton($className);
                $ancestry = array_filter($classSingleton->getClassAncestry(), function ($class) {
                    return DataObject::has_own_table($class);
                });

                foreach ($objects as $obj) {
                    if (!$obj->Created) {
                        $obj->Created = date('Y-m-d H:i:s');
                    }
                    $obj->LastEdited = date('Y-m-d H:i:s');
                }

                $rootClass = array_shift($ancestry);

                $table = $rootClass . ($postfix ? '_' . $postfix : '');
                $this->writeClassTable($objects, $rootClass, $table, false, $action === 'update');

                if ($action === 'insert') {
                    $sql = 'SELECT LAST_INSERT_ID() AS ID, ROW_COUNT() AS Count';

                    $row = DB::query($sql)->first();

                    // check count?
                    $id = intval($row['ID']);

                    foreach ($objects as $obj) {
                        $obj->ID = $id;
                        $id++;
                    }
                }

                foreach ($ancestry as $class) {
                    $table = $class . ($postfix ? '_' . $postfix : '');
                    $this->writeClassTable($objects, $class, $table, true, $action === 'update');
                }

                foreach ($objects as $obj) {
                    $obj->onAfterWrite();
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

        $columnNames = array();

        if ($setID || $update) {
            $columnNames[] = 'ID';
        }

        foreach ($fields as $fieldName => $type) {
            if (!$singleton->hasOwnTableDatabaseField($fieldName)) {
                continue;
            }

            $columnNames[] = $fieldName;
        }

        $values = array();
        foreach ($objects as $obj) {
            $objectValues =  array();

            if ($setID || $update) {
                $field = $singleton->dbObject('ID');
                $objectValues[] = $field->prepValueForDB($obj->ID);
            }

            foreach ($fields as $fieldName => $type) {
                if (!$singleton->hasOwnTableDatabaseField($fieldName)) {
                    continue;
                }

                $field = $singleton->dbObject($fieldName);
                if (!$field) {
                    $field = DBField::create_field('Varchar', null, $fieldName);
                }
                $objectValues[] = $field->prepValueForDB($obj->$fieldName);
            }
            $values[] = $objectValues;
        }

        $columns = implode(', ', array_map(function ($name) {
            return "`{$name}`";
        }, $columnNames));

        $values = implode(', ', array_map(function ($objectValues) {
            return '(' . implode(', ', $objectValues) . ')';
        }, $values));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES {$values}";

        if ($update) {
            $columnValues = implode(', ', array_map(function ($name) {
                return "`{$name}` = VALUES(`{$name}`)";
            }, array_filter($columnNames, function($name) {
                return $name !== 'ID';
            })));
            $sql .= " ON DUPLICATE KEY UPDATE {$columnValues}";
        }

        DB::query($sql);
    }

    public function writeManyMany($sets)
    {
        if (empty($sets)) {
            return;
        }

        $types = array();

        foreach ($sets as $set) {
            $types[$set[0]->ClassName][$set[1]][] = $set;
        }

        foreach ($types as $className => $relations) {
            foreach ($relations as $relation => $sets) {
                $tableName = $className . '_' . $relation;

//                $extraFields = $object->many_many_ExtraFields($relation);

                $columnNames = array($className . 'ID', $sets[0][2]->ClassName . 'ID');

                $values = array();

                foreach ($sets as $set) {
                    $rowValues = array(
                        $set[0]->dbObject('ID')->prepValueForDB($set[0]->ID),
                        $set[2]->dbObject('ID')->prepValueForDB($set[2]->ID),
                    );

                    // todo extra fields

                    $values[] = $rowValues;
                }

                $columns = implode(', ', array_map(function ($name) {
                    return "`{$name}`";
                }, $columnNames));

                $values = implode(', ', array_map(function ($objectValues) {
                    return '(' . implode(', ', $objectValues) . ')';
                }, $values));

                $sql = "INSERT INTO `{$tableName}` ({$columns}) VALUES {$values}";

                DB::query($sql);
            }
        }
    }

    public function delete($dataObjects)
    {
        $types = array();

        foreach ($dataObjects as $dataObject) {
            $types[$dataObject->ClassName][] = $dataObject->ID;
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
            $types[$dataObject->ClassName][] = $dataObject->ID;
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
