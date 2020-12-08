<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;

/**
 * @coversDefaultClass \Atk4\Data\Model
 *
 * Tests cases when model have to work with data that does not have ID field
 */
class ModelWithoutIdTest extends \Atk4\Schema\PhpunitTestCase
{
    public $m;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $this->m = new Model($db, ['user', 'id_field' => false]);

        $this->m->addFields(['name', 'gender']);
    }

    /**
     * Basic operation should work just fine on model without ID.
     */
    public function testBasic()
    {
        $this->m->tryLoadAny();
        $this->assertSame('John', $this->m->get('name'));

        $this->m->setOrder('name', 'desc');
        $this->m->tryLoadAny();
        $this->assertSame('Sue', $this->m->get('name'));

        $n = [];
        foreach ($this->m as $row) {
            $n[] = $row->get('name');
        }
        $this->assertSame(['Sue', 'John'], $n);
    }

    public function testGetIdException()
    {
        $this->m->loadAny();
        $this->expectException(Exception::class);
        $this->expectErrorMessage('ID field is not defined');
        $this->m->dummy = $this->m->getId();
    }

    public function testSetIdException()
    {
        $this->expectException(Exception::class);
        $this->expectErrorMessage('ID field is not defined');
        $this->m->setId(1);
    }

    public function testFail1()
    {
        $this->expectException(Exception::class);
        $this->m->load(1);
    }

    /**
     * Inserting into model without ID should be OK.
     */
    public function testInsert()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $this->m->insert(['name' => 'Joe']);
        $this->assertEquals(3, $this->m->action('count')->getOne());
    }

    /**
     * Since no ID is set, a new record will be created if saving is attempted.
     */
    public function testSave1()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $this->m->tryLoadAny();
        $this->m->saveAndUnload();

        $this->assertEquals(3, $this->m->action('count')->getOne());
    }

    /**
     * Calling save will always create new record.
     */
    public function testSave2()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $this->m->tryLoadAny();
        $this->m->save();

        $this->assertEquals(3, $this->m->action('count')->getOne());
    }

    /**
     * Conditions should work fine.
     */
    public function testLoadBy()
    {
        $this->m->loadBy('name', 'Sue');
        $this->assertSame('Sue', $this->m->get('name'));
    }

    public function testLoadCondition()
    {
        $this->m->addCondition('name', 'Sue');
        $this->m->loadAny();
        $this->assertSame('Sue', $this->m->get('name'));
    }

    public function testFailDelete1()
    {
        $this->expectException(\TypeError::class);
        $this->m->delete(4);
    }
}
