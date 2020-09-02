<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Exception;
use atk4\data\Model;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsMany(Line)
 *     - hasOne(VatRate, SQL)
 *     - containsMany(Discount)
 */

/**
 * Invoice model.
 */
class Invoice2 extends Model
{
    public $table = 'invoice';
    public $title_field = 'ref_no';

    protected function init(): void
    {
        parent:: init();

        $this->addField('ref_no', ['required' => true]);
        $this->addField('amount', ['type' => 'money']);

        // will contain many Lines
        $this->containsMany('lines', ['model' => [Line2::class], 'caption' => 'My Invoice Lines']);

        // total_gross - calculated by php callback not by SQL expression
        $this->addCalculatedField('total_gross', function ($m) {
            $total = 0;
            foreach ($m->ref('lines') as $line) {
                $total += $line->get('total_gross');
            }

            return $total;
        });

        // discounts_total_sum - calculated by php callback not by SQL expression
        $this->addCalculatedField('discounts_total_sum', function ($m) {
            $total = 0;
            foreach ($m->ref('lines') as $line) {
                $total += $line->get('total_gross') * $line->get('discounts_percent') / 100;
            }

            return $total;
        });
    }
}

/**
 * Invoice lines model.
 */
class Line2 extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->hasOne('vat_rate_id', ['model' => [VatRate2::class]]);

        $this->addField('price', ['type' => 'money', 'required' => true]);
        $this->addField('qty', ['type' => 'float', 'required' => true]);
        $this->addField('add_date', ['type' => 'datetime']);

        $this->addExpression('total_gross', function ($m) {
            return $m->get('price') * $m->get('qty') * (1 + $m->ref('vat_rate_id')->get('rate') / 100);
        });

        // each line can have multiple discounts and calculate total of these discounts
        $this->containsMany('discounts', ['model' => [Discount2::class]]);

        $this->addCalculatedField('discounts_percent', function ($m) {
            $total = 0;
            foreach ($m->ref('discounts') as $d) {
                $total += $d->get('percent');
            }

            return $total;
        });
    }
}

/**
 * VAT rate model.
 */
class VatRate2 extends Model
{
    public $table = 'vat_rate';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('rate', ['type' => 'integer']);
    }
}

/**
 * Each line can have multiple discounts.
 */
class Discount2 extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField('percent', ['type' => 'integer', 'required' => true]);
        $this->addField('valid_till', ['type' => 'datetime']);
    }
}

// ============================================================================

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * ATK Data has support of containsOne / containsMany.
 * Basically data model can contain other data models with one or many records.
 */
class ContainsManyTest extends \atk4\schema\PhpunitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our models
        $this->getMigrator(new VatRate2($this->db))->drop()->create();
        $this->getMigrator(new Invoice2($this->db))->drop()->create();

        // fill in some default values
        $m = new VatRate2($this->db);
        $m->import([
            ['id' => 1, 'name' => '21% rate', 'rate' => 21],
            ['id' => 2, 'name' => '15% rate', 'rate' => 15],
        ]);

        $m = new Invoice2($this->db);
        $m->import([
            ['id' => 1, 'ref_no' => 'A1', 'amount' => 123],
            ['id' => 2, 'ref_no' => 'A2', 'amount' => 456],
        ]);
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption()
    {
        $i = new Invoice2($this->db);

        // test caption of containsMany reference
        $this->assertSame('My Invoice Lines', $i->getField('lines')->getCaption());
        $this->assertSame('My Invoice Lines', $i->refModel('lines')->getModelCaption());
        $this->assertSame('My Invoice Lines', $i->ref('lines')->getModelCaption());
    }

    /**
     * Test containsMany.
     */
    public function testContainsMany()
    {
        $i = new Invoice2($this->db);
        $i->loadBy('ref_no', 'A1');

        // now let's add some lines
        $l = $i->ref('lines');
        $rows = [
            1 => ['id' => 1, 'vat_rate_id' => 1, 'price' => 10, 'qty' => 2, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')],
            2 => ['id' => 2, 'vat_rate_id' => 2, 'price' => 15, 'qty' => 5, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')],
            3 => ['id' => 3, 'vat_rate_id' => 1, 'price' => 40, 'qty' => 1, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')],
        ];

        foreach ($rows as $row) {
            $l->insert($row);
        }

        // reload invoice just in case
        $this->assertEquals($rows, $i->ref('lines')->export());
        $i->reload();
        $this->assertEquals($rows, $i->ref('lines')->export());

        // now let's delete line with id=2 and add one more line
        $i->ref('lines')
            ->load(2)->delete()
            ->insert(['vat_rate_id' => 2, 'price' => 50, 'qty' => 3, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')]);
        $rows = [
            1 => ['id' => 1, 'vat_rate_id' => 1, 'price' => 10, 'qty' => 2, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')],
            3 => ['id' => 3, 'vat_rate_id' => 1, 'price' => 40, 'qty' => 1, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')],
            4 => ['id' => 4, 'vat_rate_id' => 2, 'price' => 50, 'qty' => 3, 'discounts' => null, 'add_date' => new \DateTime('2019-01-01')],
        ];
        $this->assertEquals($rows, $i->ref('lines')->export());

        // try hasOne reference
        $v = $i->ref('lines')->load(4)->ref('vat_rate_id');
        $this->assertSame(15, $v->get('rate'));

        // test expression fields
        $v = $i->ref('lines')->load(4);
        $this->assertSame(50 * 3 * (1 + 15 / 100), $v->get('total_gross'));

        // and what about calculated field?
        $i->reload(); // we need to reload invoice for changes in lines to be recalculated
        $this->assertSame(10 * 2 * (1 + 21 / 100) + 40 * 1 * (1 + 21 / 100) + 50 * 3 * (1 + 15 / 100), $i->get('total_gross')); // =245.10

        //var_dump($i->export(), $i->export(null,null,false));
    }

    /**
     * Model should be loaded before traversing to containsMany relation.
     */
    /* Imants: it looks that this is not actually required - disabling
    public function testEx1()
    {
        $i = new Invoice2($this->db);
        $this->expectException(Exception::class);
        $i->ref('lines');
    }
    */

    /**
     * Nested containsMany tests.
     */
    public function testNestedContainsMany()
    {
        $i = new Invoice2($this->db);
        $i->loadBy('ref_no', 'A1');

        // now let's add some lines
        $l = $i->ref('lines');

        $rows = [
            1 => ['id' => 1, 'vat_rate_id' => 1, 'price' => 10, 'qty' => 2, 'add_date' => new \DateTime('2019-06-01')],
            2 => ['id' => 2, 'vat_rate_id' => 2, 'price' => 15, 'qty' => 5, 'add_date' => new \DateTime('2019-07-01')],
        ];
        foreach ($rows as $row) {
            $l->insert($row);
        }

        // add some discounts
        $l->load(1)->ref('discounts')->insert(['id' => 1, 'percent' => 5, 'valid_till' => new \DateTime('2019-07-15')]);
        $l->load(1)->ref('discounts')->insert(['id' => 2, 'percent' => 10, 'valid_till' => new \DateTime('2019-07-30')]);
        $l->load(2)->ref('discounts')->insert(['id' => 1, 'percent' => 20, 'valid_till' => new \DateTime('2019-12-31')]);

        // reload invoice to be sure all is saved and to recalculate all fields
        $i->reload();

        // ok, so now let's test
        $this->assertEquals([
            1 => ['id' => 1, 'percent' => 5, 'valid_till' => new \DateTime('2019-07-15')],
            2 => ['id' => 2, 'percent' => 10, 'valid_till' => new \DateTime('2019-07-30')],
        ], $i->ref('lines')->load(1)->ref('discounts')->export());

        // is total_gross correctly calculated?
        $this->assertSame(10 * 2 * (1 + 21 / 100) + 15 * 5 * (1 + 15 / 100), $i->get('total_gross')); // =110.45

        // do we also correctly calculate discounts from nested containsMany?
        $this->assertSame(24.2 * 15 / 100 + 86.25 * 20 / 100, $i->get('discounts_total_sum')); // =20.88

        // let's test how it all looks in persistence without typecasting
        $exp_lines = $i->export(null, null, false)[0]['lines'];
        $this->assertSame(
            json_encode([
                '1' => [
                    'id' => 1, 'vat_rate_id' => '1', 'price' => '10', 'qty' => '2', 'add_date' => (new \DateTime('2019-06-01'))->format('Y-m-d\TH:i:sP'), 'discounts' => json_encode([
                        '1' => ['id' => 1, 'percent' => '5', 'valid_till' => (new \DateTime('2019-07-15'))->format('Y-m-d\TH:i:sP')],
                        '2' => ['id' => 2, 'percent' => '10', 'valid_till' => (new \DateTime('2019-07-30'))->format('Y-m-d\TH:i:sP')],
                    ]),
                ],
                '2' => [
                    'id' => 2, 'vat_rate_id' => '2', 'price' => '15', 'qty' => '5', 'add_date' => (new \DateTime('2019-07-01'))->format('Y-m-d\TH:i:sP'), 'discounts' => json_encode([
                        '1' => ['id' => 1, 'percent' => '20', 'valid_till' => (new \DateTime('2019-12-31'))->format('Y-m-d\TH:i:sP')],
                    ]),
                ],
            ]),
            $exp_lines
        );
    }
}
