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

    public function writeToStage($dataObjects)
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

    public function delete($object)
    {

    }

    public function finish()
    {
        while (!empty($this->batches) || !empty($this->stagedBatches) || !empty($this->manyManyBatches)) {
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
        }
    }
}
