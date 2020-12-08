<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql as PersistenceSql;

/**
 * THIS IS NOT A TEST. This file is a template which you can duplicate
 * to create a new TestCase.
 */
class _Template extends \Atk4\Schema\PhpunitTestCase
{
    public function sampleTest()
    {
        $dbData = [
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ];
        $this->setDb($dbData);

        $db = new PersistenceSql($this->db->connection);

        $m = new Model($db, 'item');
        $m->addField('name');
        $m->load(2);

        $this->assertNotNull($m->getId());

        $this->assertSame($dbData, $this->getDb());
    }
}
