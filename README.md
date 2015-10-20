silverstripe-batchwrite
-----------------------

Batchwrite data objects to improve bulk insert/update/delete performance. **Currently supports MySQL and only when the `mysqli` adapater is used.**

- [Basic usage](#user-content-basic-usage)
- [Abstracting batch by using BatchedWriter](#user-content-abstracting-batch-by-using-batchedwriter)
- [Dealing with has_one, has_many and many_many](#user-content-dealing-with-has_one-has_many-and-many_many)
- [Squeezing out more performance](#user-content-squeezing-out-more-performance)
- [Want to contribute or need another feature?](#user-content-want-to-contribute-or-need-another-feature)

Basic Usage
-------------

    $batch = new Batch();

    $objects = ...;
    $batch->write($objects);

    $versionedObjects = ...;
    $batch->writeToStage($versionedObjects, 'Stage');

    $objectsToDelete = ...;
    $batch->delete($objectsToDelete);

    $versionedObjectsToDelete = ...;
    $batch->deleteFromStage($versionedObjectsToDelete, 'Stage');

    $class = 'DataObjectClass';
    $ids = array(1, 2, 3...)
    $batch->deleteIDs($class, $ids);

    $class = 'VersionedClass';
    $batch->deleteIDsFromStage($versionedClass, $ids);


### write($dataObjects)

Groups the data objects by class and then inserts/updates the objects using one query. This **will** call `onBeforeWrite()` and `onAfterWrite()`. It will also populate the IDs of new data objects

    $batch = new Batch();
    $batch->write($objects);

### writeToStage($dataObjects, $stage...)

Same as `write($dataObjects)` but allows you to specify stages to write objects that extend `Versioned`. You can pass in more than one stage. For example -

    $batch = new Batch();
    $batch->writeToStage($objects, 'Stage'); # writes to the 'Stage'
    $batch->writeToStage($objects, 'Live'); # writes to 'Live' e.g publish()
    $batch->writeToStage($objects, 'Stage', 'Live'); # writes to 'Stage' and 'Live'

### writeManyMany($sets)

Write many many relationships, where `$sets` is an array of "`$set`". Each `$set` is an `array($object, $relation, $belongsObject)`. **This will not check if the relationship already exists, and will add it again**

    # team->many_many = array('TeamMembers' => 'Person')
    # person->belongs_many_many = array('Teams' => 'Team')
    $batch = new Batch();
    $team = new Team();
    $person = new Person();

    $batch->write($team);
    $batch->write($person);
    $sets = array();
    $sets[] = array($team, 'TeamMembers', $person);
    $batch->writeManyMany($sets);

### delete($dataObjects)

Groups the data objects by class then deletes the objects using one query per class/table. This **will not** call `onBeforeDelete` or `onAfterDelete`. No child objects will be deleted either, e.g `SiteTree->Children()` will be orphaned. The versions table will als0 not be updated.

    $objects = DataObjectClass::get();
    $batch = new Batch();
    $batch->delete($batch);

### deleteFromStage($dataObjects, $stage...)

Same as `delete($dataObjects)` but adds support to delet versioned objects. You can pass multiple stages at once.

    $objects = VersionedObject::get();
    $batch = new Batch();
    $batch->deleteToStage($objects, 'Stage'); # deletes from the 'Stage'
    $batch->deleteToStage($objects, 'Live'); # deletes from 'Live' e.g doUnpublish()
    $batch->deleteToStage($objects, 'Stage', 'Live'); # deletes from 'Stage' and 'Live'

### deleteIDs($className, $ids)

Delete the data objects for `$className` with the given `$ids`. As per `delete($objects)` this **will not** call  no `onBeforeDelete` or `onAfterDelete`. This can be used to avoid populating data objects that are just going to be deleted.

    $className = 'DataObjectClass';
    $ids = $className::get()->column('ID');
    $batch = new Batch();
    $batch->deleteIDs($className, $ids);

### deleteIDsFromStage($className, $ids, $stage...)

As per `deleteIDs` but allows you to specify stages to delete from. For example -

    $className = 'VersionedDataObjectClass';
    $ids = $className::get()->column('ID');
    $batch = new Batch();
    $batch->deleteIDsFromStage($className, $ids, 'Stage'); # deleted from 'Stage'
    $batch->deleteIDsFromStage($className, $ids, 'Live'); # deleted from 'Live'
    $batch->deleteIDsFromStage($className, $ids, 'Stage', 'Live'); # 'Stage' and 'Live'

Abstracting batch by using BatchedWriter
----------------------------------------

The `BatchedWriter` class allows you to pass data objects in one at the time, which will then be written when the batch reaches a specified size. The code below will write 100 objects at a time, with the remaining 50 objects written when `finish()` is called.

    $writer = new BatchedWriter(100);

    for ($i = 0; $i < 350; $i++) {
        $object = new DataObjectClass();
        $writer->write($object);
    }

    $writer->finish();

### Methods

The `BatchedWriter` class has the same methods as `Batch` but replaces $objects with $object as the first parameter. The `finish()` method should be called to write/delete the remaining objects.

    $writer = new BatchedWriter();

    $writer->write($object);
    $writer->writeToStage($object, $stage...);

    $writer->delete($object);
    $writer->deleteFromStage($object, $stage...);

    $writer->deleteIDs($className, $id);
    $writer->deleteFromStage($className, $id, $stage...);

    $writer->finish(); # write/delete all the remaining objects

The largest difference is `writeManyMany($object, $relation, $belongsObjects)`

    $writer = new BatchedWriter();
    $team = new Team();
    $person1 = new Person();
    $person2 = new Person();

    $writer->write(array($team, $person1, $person2));

    $afterExists = new OnAfterExists(function () use ($team, $person1, $person2, $writer) {
        $writer->writeManyMany($team, 'TeamMembers', array($person1, $person2));
    });
    $afterExists->addCondition($team);
    $afterExists->addCondition($person1);
    $afterExists->addCondition($person2);

    $writer->finish();

Dealing with has\_one, has\_many and many_many
----------------------------------------------

To reduce the number of writes and help abstract the complexities of writing objects in the correct order a few helper methods can be added to DataObject through the `WriteCallbackExtesion`.

    ---
    Name: mysite-confg
    ---

    ...

    DataObject:
        extensions:
            - WriteCallbackExtension

This adds `onBeforeWriteCallback`, `onAfterWriteCallback` and `onAfterExistsCallback`.

### has_one

    $writer = new BatchedWriter();

    $team = new Team(); # has_one = array('Leader' => 'TeamLeader')
    $team->Title = 'Team Spot';

    $leader = new TeamLeader();
    $leader->Name = 'Big Red';

    $leader->onAfterExistsCallback(function ($leader) use ($writer, $team) {
        $team->LeaderID = $leader->ID;
        # write team when leader has been written
        $writer->write($team);
    });

    # write leader first
    $writer->write($leader);
    ...
    $writer->finish();

To support multiple has_one objects you can use a `OnAfterExists`

    $writer = new BatchedWriter();
    $object = new Object(); # has_one = array('HasOne1', 'HasOne2')
    $hasOne1 = new HasOne1();
    $hasOne2 = new HasOne2();

    # create onAfterExists to write $object when conditions are met
    $afterExists = new OnAfterExists(function () use ($writer, $object) {
        $writer->write($object);
    });

    # add condition that hasOne1 exists
    $afterExists->condition($hasOne1, function ($hasOne1) use ($object) {
        $object->HasOne1 = $hasOne1->ID;
    });

    # add condition that hasOne2 exists
    $afterExists->condition($hasOne2, function ($hasOne2) use ($object) {
        $object->HasOne2 = $hasOne2->ID;
    });

    # write has_ones
    $writer->write($hasOne1);
    $writer->write($hasOne2);
    ...
    $writer->finish();

### has_many

In a similar fashion use `onAfterExistsCallback` to write has_many objects

    $writer = new BatchedWriter();

    $team = new Team(); # has_many = array('TeamMembers' => 'TeamMember')

    for ($i = 0; $i < 10; $i++) {
        $member = new TeamMember(); # has_one = array('Team' => 'Team');
        $team->onAfterExistsCallback(function ($team) use ($member, $writer) {
            $member->TeamID = $team->ID;
            $writer->write($team);
        });
    }

    $writer->write($team);
    $writer->finish();


### many_many

Use `OnAfterExists` to wait until all the objects exist before writing the relationship

    $writer = new BatchedWriter();
    $team = new Team();
    $person1 = new Person();
    $person2 = new Person();

    $writer->write(array($team, $person1, $person2));

    # see below
    $afterExists = new OnAfterExists(function () use ($team, $person1, $person2, $writer) {
        $writer->writeManyMany($team, 'TeamMembers', array($person1, $person2));
    });
    $afterExists->addCondition($team);
    $afterExists->addCondition($person1);
    $afterExists->addCondition($person2);

    $writer->finish();

Squeezing out more performance
------------------------------

Silverstripe data objects and extensions are very powerful. But with that power comes great overhead. A easily avoided overhead is instantiating a new DataObject. To avoid executing the same code for a large number of objects it's more efficient to create a single instance then clone it multiple times. This is easy with the `QuickDataObject` class. **WARNING: this will only call the constructor one time, do not use when you need execute the constructor for every instance**

    $className = 'SomeDataObject';
    for ($i = 0; $i < 1000; $i++) {
        $object = QuickDataObject::create($className);
    }

Want to contribute or need another feature?
-------------------------------------------

Contributions are always welcome, please create a pull request and I will review and merge when I get a chance. If you have a new feature request or suggestion, create an issue and I'll look into it!
