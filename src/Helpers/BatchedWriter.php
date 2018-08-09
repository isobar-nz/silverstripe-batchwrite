<?php

/**
 * Class BatchedWriter
 */
class BatchedWriter
{
    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var array
     */
    private $batches = array();
    /**
     * @var array
     */
    private $batchLookup = array();
    /**
     * @var array
     */
    private $batchSearch = array();

    /**
     * @var array
     */
    private $stagedBatches = array();
    /**
     * @var array
     */
    private $stagedBatchLookup = array();
    /**
     * @var array
     */
    private $stagedBatchSearch = array();

    /**
     * @var array
     */
    private $deleteBatches = array();
    /**
     * @var array
     */
    private $stagedDeleteBatches = array();

    /**
     * @var array
     */
    private $manyManyBatches = array();

    /**
     * @var ReflectionProperty
     */
    private $dataObjectRecordProperty;

    /**
     * @param int $batchSize
     */
    public function __construct($batchSize = 100)
    {
        $this->batch = new Batch();
        $this->batchSize = $batchSize;

        $this->dataObjectRecordProperty = new ReflectionProperty('DataObject', 'record');
        $this->dataObjectRecordProperty->setAccessible(true);
    }

    /**
     * @param $dataObjects
     */
    public function write($dataObjects)
    {
        if ($dataObjects instanceof DataObject) {
            $dataObjects = array($dataObjects);
        }

        foreach ($dataObjects as $object) {
            $className = $object->class;
            $record = $this->dataObjectRecordProperty->getValue($object);
            $id = !empty($record['ID']) ? $record['ID'] : 0;

            // check if batch contains object
            if ($id && isset($this->batchLookup[$className][$id])) {
                continue;
            } else if (isset($this->batchSearch[$className])
                && in_array($object, $this->batchSearch[$className])
            ) {
                continue;
            }

            $this->batches[$className][] = $object;

            // add to lookup
            if ($id) {
                $this->batchLookup[$className][$id] = $object;
            } else {
                $this->batchSearch[$className][] = $object;
            }

            if (count($this->batches[$className]) >= $this->batchSize) {
                $batch = $this->batches[$className];
                unset($this->batches[$className]);
                unset($this->batchLookup[$className]);
                unset($this->batchSearch[$className]);

                $this->batch->write($batch);
            }
        }
    }

    /**
     * @param $dataObjects
     * @param $key
     */
    public function writeToStage($dataObjects, $key)
    {
        $stages = array_slice(func_get_args(), 1);
        $key = serialize($stages);

        if ($dataObjects instanceof DataObject) {
            $dataObjects = array($dataObjects);
        }

        foreach ($dataObjects as $object) {
            $className = $object->class;
            $record = $this->dataObjectRecordProperty->getValue($object);
            $id = !empty($record['ID']) ? $record['ID'] : 0;

            // check if stagedBatch contains object
            if ($id && isset($this->stagedBatchLookup[$key][$className][$id])) {
                continue;
            } else if (isset($this->stagedBatchSearch[$key][$className])
                && in_array($object, $this->stagedBatchSearch[$key][$className])
            ) {
                continue;
            }

            $this->stagedBatches[$key][$className][] = $object;

            // add to lookup
            if ($id) {
                $this->stagedBatchLookup[$key][$className][$id] = $object;
            } else {
                $this->stagedBatchSearch[$key][$className][] = $object;
            }

            if (count($this->stagedBatches[$key][$className]) >= $this->batchSize) {
                $batch = $this->stagedBatches[$key][$className];
                unset($this->stagedBatches[$key][$className]);
                unset($this->stagedBatchLookup[$key][$className]);
                unset($this->stagedBatchSearch[$key][$className]);
                if (empty($this->stagedBatches[$key])) {
                    unset($this->stagedBatches[$key]);
                }

                // hard code 2 stages at once
                if (count($stages) === 2) {
                    $this->batch->writeToStage($batch, $stages[0], $stages[1]);
                } else {
                    foreach ($stages as $stage) {
                        $this->batch->writeToStage($batch, $stage);
                    }
                }
            }
        }
    }

    /**
     * @param $object
     * @param $relation
     * @param $belongs
     */
    public function writeManyMany($object, $relation, $belongs)
    {
        $className = $object->class;

        foreach ($belongs as $belong) {
            $this->manyManyBatches[$className][$relation][] = array($object, $relation, $belong);
        }

        if (count($this->manyManyBatches[$className][$relation]) >= $this->batchSize) {
            $this->batch->writeManyMany($this->manyManyBatches[$className][$relation]);

            unset($this->manyManyBatches[$className][$relation]);
            if (empty($this->manyManyBatches[$className])) {
                unset($this->manyManyBatches[$className]);
            }
        }
    }

    /**
     * @param $objects
     */
    public function delete($objects)
    {
        foreach ($objects as $object) {
            $className = $object->class;
            $record = $this->dataObjectRecordProperty->getValue($object);
            $id = !empty($record['ID']) ? $record['ID'] : 0;
            $this->deleteBatches[$className][] = $id;

            if (count($this->deleteBatches[$className]) >= $this->batchSize) {
                $this->batch->deleteIDs($className, $this->deleteBatches[$className]);
                unset($this->deleteBatches[$className]);
            }
        }
    }

    /**
     * @param $className
     * @param $ids
     */
    public function deleteIDs($className, $ids)
    {
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        if (!isset($this->deleteBatches[$className])) {
            $this->deleteBatches[$className] = $ids;
        } else {
            $this->deleteBatches[$className] = array_merge($this->deleteBatches[$className], $ids);
        }

        if (count($this->deleteBatches[$className]) >= $this->batchSize) {
            $this->batch->deleteIDs($className, $this->deleteBatches[$className]);
            unset($this->deleteBatches[$className]);
        }
    }

    /**
     * @param $objects
     * @param $stage
     */
    public function deleteFromStage($objects, $stage)
    {
        $stages = array_slice(func_get_args(), 1);

        foreach ($stages as $stage) {
            foreach ($objects as $object) {
                $className = $object->class;
                $record = $this->dataObjectRecordProperty->getValue($object);
                $id = !empty($record['ID']) ? $record['ID'] : 0;

                $this->stagedDeleteBatches[$stage][$className][] = $id;

                if (count($this->stagedDeleteBatches[$stage][$className]) >= $this->batchSize) {
                    $this->batch->deleteIDsFromStage($className, $this->stagedDeleteBatches[$stage][$className], $stage);

                    unset($this->stagedDeleteBatches[$stage][$className]);
                    if ($this->stagedDeleteBatches[$stage]) {
                        unset($this->stagedDeleteBatches[$stage]);
                    }
                }
            }
        }
    }

    /**
     * @param $className
     * @param $ids
     * @param $stage
     */
    public function deleteIDsFromStage($className, $ids, $stage)
    {
        $stages = array_slice(func_get_args(), 2);

        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($stages as $stage) {
            if (empty($this->stagedDeleteBatches[$stage][$className])) {
                $this->stagedDeleteBatches[$stage][$className] = $ids;
            } else {
                $this->stagedDeleteBatches[$stage][$className] = array_merge($this->stagedDeleteBatches[$stage][$className], $ids);
            }
            if (count($this->stagedDeleteBatches[$stage][$className]) >= $this->batchSize) {
                $this->batch->deleteIDsFromStage($className, $this->stagedDeleteBatches[$stage][$className], $stage);

                unset($this->stagedDeleteBatches[$stage][$className]);
                if ($this->stagedDeleteBatches[$stage]) {
                    unset($this->stagedDeleteBatches[$stage]);
                }
            }
        }
    }

    /**
     *
     */
    public function finish()
    {
        while (!empty($this->batches)
            || !empty($this->stagedBatches)
            || !empty($this->manyManyBatches)
            || !empty($this->deleteBatches)
            || !empty($this->stagedDeleteBatches)
        ) {
            while (!empty($this->batches)) {
                // assign to variable and clear so any new batches will be written next loop
                $batches = $this->batches;
                $this->batches = array();
                $this->batchLookup = array();
                $this->batchSearch = array();

                foreach ($batches as $className => $objects) {
                    $this->batch->write($objects);
                }
            }

            foreach ($this->stagedBatches as $key => $classNames) {
                $stages = unserialize($key);
                $batches = $this->stagedBatches[$key];
                unset($this->stagedBatches[$key]);
                $this->stagedBatchSearch[$key] = array();
                $this->stagedBatchLookup[$key] = array();

                foreach ($batches as $className => $objects) {
                    if (count($stages) === 2) {
                        $this->batch->writeToStage($objects, $stages[0], $stages[1]);
                    } else {
                        foreach ($stages as $stage) {
                            $this->batch->writeToStage($objects, $stage);
                        }
                    }
                }
            }

            foreach ($this->manyManyBatches as $className => $relations) {
                $batches = $this->manyManyBatches[$className];
                unset($this->manyManyBatches[$className]);

                foreach ($batches as $relation => $sets) {
                    $this->batch->writeManyMany($sets);
                }
            }

            foreach ($this->deleteBatches as $className => $ids) {
                $this->batch->deleteIDs($className, $ids);
            }
            $this->deleteBatches = array();

            foreach ($this->stagedDeleteBatches as $stage => $classNames) {
                foreach ($classNames as $className => $ids) {
                    $this->batch->deleteIDsFromStage($className, $ids, $stage);
                }
            }
            $this->stagedDeleteBatches = array();
        }
    }
}
