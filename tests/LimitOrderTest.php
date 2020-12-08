<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class LimitOrderTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testBasic()
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');
        $i->getField($i->id_field)->system = false;

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);
        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());
    }

    public function testReverse()
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 5], // total_gross 15
                ['total_net' => 10, 'total_vat' => 4], // total_gross 14
                ['total_net' => 15, 'total_vat' => 4], // total_gross 19
            ],
        ]);

        $ii = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $ii->addExpression('total_gross', '[total_net]+[total_vat]');
        $ii->getField($ii->id_field)->system = false;

        $i = clone $ii;
        $i->setOrder(['total_net' => 'desc', 'total_gross' => 'desc']);
        $i->onlyFields(['total_net', 'total_gross']);
        $this->assertEquals([
            ['total_net' => 15, 'total_gross' => 19],
            ['total_net' => 10, 'total_gross' => 15],
            ['total_net' => 10, 'total_gross' => 14],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder(['total_net' => 'desc', 'total_gross']);
        $i->onlyFields(['total_net', 'total_gross']);
        $this->assertEquals([
            ['total_net' => 15, 'total_gross' => 19],
            ['total_net' => 10, 'total_gross' => 14],
            ['total_net' => 10, 'total_gross' => 15],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder(['total_net' => 'desc', 'total_gross']);
        $i->onlyFields(['total_net', 'total_vat']);
        $this->assertEquals([
            ['total_net' => 15, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 5],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder(['total_gross' => 'desc', 'total_net']);
        $i->onlyFields(['total_net', 'total_vat']);
        $this->assertEquals([
            ['total_net' => 15, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 5],
            ['total_net' => 10, 'total_vat' => 4],
        ], $i->export());
    }

    public function testArrayParameters()
    {
        $this->setDb([
            'invoice' => [
                ['net' => 10, 'vat' => 5],
                ['net' => 10, 'vat' => 4],
                ['net' => 15, 'vat' => 4],
            ],
        ]);

        $ii = (new Model($this->db, 'invoice'))->addFields(['net', 'vat']);
        $ii->getField($ii->id_field)->system = false;

        // pass parameters as array elements [field,order]
        $i = clone $ii;
        $i->setOrder([['net', 'desc'], ['vat']]);
        $i->onlyFields(['net', 'vat']);
        $this->assertEquals([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());

        // pass parameters as array elements [field=>order]
        $i = clone $ii;
        $i->setOrder(['net' => 'desc', 'vat' => 'asc']);
        $i->onlyFields(['net', 'vat']);
        $this->assertEquals([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());

        // pass parameters as array elements [field=>order], same as above but use 'desc' instead of true
        $i = clone $ii;
        $i->setOrder(['net' => 'desc', 'vat']); // and you can even mix them (see 'vat' is a value not a key here)
        $i->onlyFields(['net', 'vat']);
        $this->assertEquals([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());
    }

    public function testOrderByExpressions()
    {
        $this->setDb([
            'invoice' => [
                ['code' => 'A', 'net' => 10, 'vat' => 5],
                ['code' => 'B', 'net' => 10, 'vat' => 4],
                ['code' => 'C', 'net' => 15, 'vat' => 4],
            ],
        ]);

        // order by expression field
        $i = (new Model($this->db, 'invoice'))->addFields(['code', 'net', 'vat']);
        $i->addExpression('gross', '[net]+[vat]');
        $i->getField($i->id_field)->system = false;

        $i->setOrder('gross');
        $i->onlyFields(['gross']);
        $this->assertEquals([
            ['gross' => 14],
            ['gross' => 15],
            ['gross' => 19],
        ], $i->export());

        // order by expression not defined as separate expression field in model
        $i->order = []; // reset
        $i->setOrder($i->expr('[net]*[vat]'));
        $i->onlyFields(['code']);
        $this->assertSame([
            ['code' => 'B'], // 10 * 4 = 40
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'C'], // 15 * 4 = 60
        ], $i->export());

        // "desc" as part of expression string
        $i->order = []; // reset
        $i->setOrder($i->expr('[net]*[vat] desc'));
        $i->onlyFields(['code']);
        $this->assertSame([
            ['code' => 'C'], // 15 * 4 = 60
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'B'], // 10 * 4 = 40
        ], $i->export());

        // "desc" as 2nd parameter
        $i->order = []; // reset
        $i->setOrder($i->expr('[net]*[vat]'), 'desc');
        $i->onlyFields(['code']);
        $this->assertSame([
            ['code' => 'C'], // 15 * 4 = 60
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'B'], // 10 * 4 = 40
        ], $i->export());

        // order by mixed array of expressions and field names
        $i->order = []; // reset
        $i->setOrder(['vat', $i->expr('[net]*[vat]')]);
        $i->onlyFields(['code']);
        $this->assertSame([
            ['code' => 'B'], // 4, 10 * 4 = 40
            ['code' => 'C'], // 4, 15 * 4 = 60
            ['code' => 'A'], // 5, 10 * 5 = 50
        ], $i->export());
    }

    /**
     * Unsupported order parameter.
     */
    public function testExceptionUnsupportedOrderParam()
    {
        $this->setDb([
            'invoice' => [
                ['net' => 10],
            ],
        ]);

        $i = (new Model($this->db, 'invoice'))->addFields(['net']);
        $i->setOrder(new \DateTime());
        $this->expectException(Exception::class);
        $i->export(); // executes query and throws exception because of DateTime object
    }

    public function testLimit()
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');
        $i->getField($i->id_field)->system = false;

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);
        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());

        $ii = $i;
        $i = clone $ii;
        $i->setLimit(2);
        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
        ], $i->export());

        $i = clone $ii;
        $i->setLimit(2, 1);
        $this->assertEquals([
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());

        $i = clone $ii;
        $i->setLimit(null, 1);
        $this->assertEquals([
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());
    }
}
