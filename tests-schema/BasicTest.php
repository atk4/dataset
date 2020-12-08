<?php

declare(strict_types=1);

namespace Atk4\Schema\Tests;

use Atk4\Schema\PhpunitTestCase;

class BasicTest extends PhpunitTestCase
{
    /**
     * Test constructor.
     *
     * @doesNotPerformAssertions
     */
    public function testCreate()
    {
        $this->dropTableIfExists('user');

        $this->getMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'money'])
//            ->field('en', ['type' => 'enum'])
            ->create();
    }

    /**
     * Tests creating and dropping of tables.
     *
     * @doesNotPerformAssertions
     */
    public function testCreateAndDrop()
    {
        $this->dropTableIfExists('user');

        $this->getMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'money'])
//            ->field('en', ['type' => 'enum'])
            ->create();

        $this->getMigrator()->table('user')->drop();
    }
}
