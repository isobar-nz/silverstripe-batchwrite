# silverstripe-batchwrite

WARNING: MySQL support only

Write objects in batches rather than one at a time to improve performance. Aimed at increasing performance of command line tools that process a lot of data

    $objects = DataObject::get()->limit(100);

    // modify data objects

    $batch = new Batch();
    $batch->write($objects);

Support for Versioned objects

    $pages = Page::get()->limit(100);

    ...

    $batch = new Batch();
    $batch->writeToStage($pages, 'Stage', 'Live');

## Tests
https://github.com/chrisahhh/silverstripe-batchwrite-tests
