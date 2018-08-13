<?php

namespace LittleGiant\BatchWrite\Tests;

use LittleGiant\BatchWrite\Helpers\Batch;
use LittleGiant\BatchWrite\Tests\DataObjects\Batman;
use LittleGiant\BatchWrite\Tests\DataObjects\Child;
use LittleGiant\BatchWrite\Tests\DataObjects\Human;

/**
 * Class BatchWriteManyManyTest
 * @package LittleGiant\BatchWrite\Tests
 */
class BatchWriteManyManyTest extends BaseTest
{
    /**
     *
     */
    public function testWriteManyMany_CreateParentAndChildren_WritesManyMany()
    {
        $parent = Batman::create();
        $parent->Name = 'Bruce Wayne';
        $parent->Car = 'Bat mobile';

        $children = [];
        for ($i = 0; $i < 5; $i++) {
            $child = Child::create();
            $child->Name = $this->faker->name;
            $children[] = $child;
        }

        $batch = Batch::create();

        $batch->write([$parent]);
        $batch->write($children);

        $sets = [];
        foreach ($children as $child) {
            $sets[] = [$parent, 'Children', $child];
        }
        $batch->writeManyMany($sets);

        /** @var Human $parent */
        $parent = Human::get()->first();
        $this->assertCount(5, $parent->Children());
    }
}
