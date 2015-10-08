<?php

class Batch
{
    public function write($dataObjects)
    {
        return $this->writeTablePostfix($dataObjects);
    }

    public function writeToStage($dataObjects)
    {
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
}
