<?php

namespace BatchWrite\Tests;

/**
 * Class BatchWriteManyManyTest
 * @package BatchWrite\Tests
 */
/**
 * Class BatchWriteManyManyTest
 * @package BatchWrite\Tests
 */
class BatchWriteManyManyTest extends \SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected $extraDataObjects = array(
        'BatchWrite\Tests\Animal',
        'BatchWrite\Tests\Batman',
        'BatchWrite\Tests\Cat',
        'BatchWrite\Tests\Child',
        'BatchWrite\Tests\Child',
        'BatchWrite\Tests\Dog',
        'BatchWrite\Tests\DogPage',
        'BatchWrite\Tests\Human',
    );

    /**
     * BatchWriteManyManyTest constructor.
     */
    public function __construct()
    {
        $this->setUpOnce();
    }

    /**
     *
     */
    public function testWriteManyMany_CreateParentAndChildren_WritesManyMany()
    {
        $parent = new Batman();
        $parent->Name = 'Bruce Wayne';
        $parent->Car = 'Bat mobile';

        $children = array();
        for ($i = 0; $i < 5; $i++) {
            $child = new Child();
            $child->Name = 'Soldier #' . $i;
            $children[] = $child;
        }

        $batch = new \Batch();

        $batch->write(array($parent));
        $batch->write($children);

        $sets = array();
        foreach ($children as $child) {
            $sets[] = array($parent, 'Children', $child);
        }
        $batch->writeManyMany($sets);

        $parent = Human::get()->first();
        $this->assertEquals(5, $parent->Children()->Count());
    }
//
//    public static function tearDownAfterClass()
//    {
//        parent::tearDownAfterClass();
//        \SapphireTest::delete_all_temp_dbs();
//    }
}
