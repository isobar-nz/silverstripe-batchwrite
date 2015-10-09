<?php

class BatchedWriter
{
    private $batchSize;

    private $batches = array();

    private $batchLookup = array();

    private $batchSearch = array();

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
                && isset($this->batchLookup[$className][$object->ID])) {
                continue;
            } else if (isset($this->batchSearch[$className])
                && in_array($object, $this->batchSearch[$className])) {
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

    public function finish()
    {
        while (!empty($this->batches) || !empty($this->manyManyBatches)) {
            foreach ($this->batches as $className => $objects) {
                $this->batch->write($objects);
                unset($this->batches[$className]);
                unset($this->batchLookup[$className]);
                unset($this->batchSearch[$className]);
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
