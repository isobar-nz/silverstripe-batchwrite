<?php

class BatchedWriter
{
    private $batchSize;

    private $batches = array();
    private $batchLookup = array();
    private $batchSearch = array();

    private $stagedBatches = array();
    private $stagedBatchLookup = array();
    private $stagedBatchSearch = array();

    private $deleteBatches = array();
    private $stagedDeleteBatches = array();

    private $manyManyBatches = array();

    public function __construct($batchSize = 100)
    {
        $this->batch = new Batch();
        $this->batchSize = $batchSize;
    }

    public function write($dataObjects)
    {
        if ($dataObjects instanceof DataObject) {
            $dataObjects = array($dataObjects);
        }

        foreach ($dataObjects as $object) {
            $className = $object->ClassName;

            // check if batch contains object
            if ($object->ID
                && isset($this->batchLookup[$className][$object->ID])
            ) {
                continue;
            } else if (isset($this->batchSearch[$className])
                && in_array($object, $this->batchSearch[$className])
            ) {
                continue;
            }

            $this->batches[$className][] = $object;

            // add to lookup
            if ($object->ID) {
                $this->batchLookup[$className][$object->ID] = $object;
            } else {
                $this->batchSearch[$className][] = $object;
            }

            if (count($this->batches[$className]) >= $this->batchSize) {
                $this->batch->write($this->batches[$className]);
                unset($this->batches[$className]);
                unset($this->batchLookup[$className]);
                unset($this->batchSearch[$className]);
            }
        }
    }

    public function writeToStage($dataObjects, $stage)
    {
        $stages = array_slice(func_get_args(), 1);

        foreach ($stages as $stage) {
            if ($dataObjects instanceof DataObject) {
                $dataObjects = array($dataObjects);
            }

            foreach ($dataObjects as $object) {
                $className = $object->ClassName;

                // check if stagedBatch contains object
                if ($object->ID
                    && isset($this->stagedBatchLookup[$stage][$className][$object->ID])
                ) {
                    continue;
                } else if (isset($this->stagedBatchSearch[$stage][$className])
                    && in_array($object, $this->stagedBatchSearch[$stage][$className])
                ) {
                    continue;
                }

                $this->stagedBatches[$stage][$className][] = $object;

                // add to lookup
                if ($object->ID) {
                    $this->stagedBatchLookup[$stage][$className][$object->ID] = $object;
                } else {
                    $this->stagedBatchSearch[$stage][$className][] = $object;
                }

                if (count($this->stagedBatches[$stage][$className]) >= $this->batchSize) {
                    $this->batch->writeToStage($this->stagedBatches[$stage][$className], $stage);
                    unset($this->stagedBatches[$stage][$className]);
                    unset($this->stagedBatchLookup[$stage][$className]);
                    unset($this->stagedBatchSearch[$stage][$className]);
                    if (empty($this->stagedBatches[$stage])) {
                        unset($this->stagedBatches[$stage]);
                    }
                }
            }
        }
    }

    public function writeManyMany($object, $relation, $belongs)
    {
        foreach ($belongs as $belong) {
            $this->manyManyBatches[$object->ClassName][$relation][] = array($object, $relation, $belong);
        }

        if (count($this->manyManyBatches[$object->ClassName][$relation]) >= $this->batchSize) {
            $this->batch->writeManyMany($this->manyManyBatches[$object->ClassName][$relation]);

            unset($this->manyManyBatches[$object->ClassName][$relation]);
            if (empty($this->manyManyBatches[$object->ClassName])) {
                unset($this->manyManyBatches[$object->ClassName]);
            }
        }
    }

    public function delete($objects)
    {
        foreach ($objects as $object) {
            $className = $object->ClassName;
            $this->deleteBatches[$className][] = $object->ID;
            if (count($this->deleteBatches[$className]) >= $this->batchSize) {
                $this->batch->deleteIDs($className, $this->deleteBatches[$className]);
                unset($this->deleteBatches[$className]);
            }
        }
    }

    public function deleteIDs($className, $ids)
    {
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

    public function deleteFromStage($objects, $stage)
    {
        $stages = array_slice(func_get_args(), 1);

        foreach ($stages as $stage) {
            foreach ($objects as $object) {
                $className = $object->ClassName;
                $this->stagedDeleteBatches[$stage][$className][] = $object->ID;
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

    public function deleteIDsFromStage($className, $ids, $stage)
    {
        $stages = array_slice(func_get_args(), 2);

        foreach ($stages as $stage) {
            if (!isset($this->stagedDeleteBatches[$stage][$className])) {
                $this->stagedDeleteBatches[$stage][$className] = $ids;
            } else {
                $this->stagedDeleteBatches[$stage][$className] = array_merge($this->stagedDeleteBatches[$stage][$className], $ids);
            }
            if (count($this->stagedDeleteBatches[$className]) >= $this->batchSize) {
                $this->batch->deleteIDsFromStage($className, $this->stagedDeleteBatches[$stage][$className], $stage);

                unset($this->stagedDeleteBatches[$stage][$className]);
                if ($this->stagedDeleteBatches[$stage]) {
                    unset($this->stagedDeleteBatches[$stage]);
                }
            }
        }
    }

    public function finish()
    {
        while (!empty($this->batches)
            || !empty($this->stagedBatches)
            || !empty($this->manyManyBatches)
            || !empty($this->deleteBatches)
            || !empty($this->stagedDeleteBatches)
        ) {
            foreach ($this->batches as $className => $objects) {
                $this->batch->write($objects);
                unset($this->batches[$className]);
                unset($this->batchLookup[$className]);
                unset($this->batchSearch[$className]);
            }

            foreach ($this->stagedBatches as $stage => $classNames) {
                foreach ($classNames as $className => $objects) {
                    $this->batch->writeToStage($objects, $stage);
                    unset($this->stagedBatches[$stage][$className]);
                    unset($this->stagedBatchLookup[$stage][$className]);
                    unset($this->stagedBatchSearch[$stage][$className]);
                    if (empty($this->stagedBatches[$stage])) {
                        unset($this->stagedBatches[$stage]);
                    }
                }
            }

            foreach ($this->manyManyBatches as $className => $relations) {
                foreach ($relations as $relation => $sets) {
                    $this->batch->writeManyMany($sets);
                }
                unset($this->manyManyBatches[$className]);
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
