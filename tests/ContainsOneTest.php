<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsOne(Address)
 *     - hasOne(Country, SQL)
 *     - containsOne(DoorCode)
 */

/**
 * Invoice model.
 */
class Invoice1 extends Model
{
    public $table = 'invoice';
    public $title_field = 'ref_no';

    protected function init(): void
    {
        parent:: init();

        $this->addField('ref_no', ['required' => true]);

        // will contain one Address
        $this->containsOne('addr', ['model' => [Address1::class]]);
    }
}

/**
 * Address model.
 */
class Address1 extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->hasOne('country_id', ['model' => [Country1::class], 'type' => 'integer']);

        $this->addField('address');
        $this->addField('built_date', ['type' => 'datetime']);
        $this->addField('tags', ['type' => 'array', 'default' => []]);

        // will contain one door code
        $this->containsOne('door_code', ['model' => [DoorCode1::class], 'caption' => 'Secret Code']);
    }
}

/**
 * DoorCode model.
 */
class DoorCode1 extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField('code');
        $this->addField('valid_till', ['type' => 'datetime']);
    }
}

/**
 * Country model.
 */
class Country1 extends Model
{
    public $table = 'country';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
    }
}

// ============================================================================

/**
 * @coversDefaultClass \Atk4\Data\Model
 *
 * ATK Data has support of containsOne / containsMany.
 * Basically data model can contain other data models with one or many records.
 */
class ContainsOneTest extends \Atk4\Schema\PhpunitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our models
        $this->getMigrator(new Country1($this->db))->dropIfExists()->create();
        $this->getMigrator(new Invoice1($this->db))->dropIfExists()->create();

        // fill in some default values
        $m = new Country1($this->db);
        $m->import([
            ['id' => 1, 'name' => 'Latvia'],
            ['id' => 2, 'name' => 'United Kingdom'],
        ]);

        $m = new Invoice1($this->db);
        $m->import([
            ['id' => 1, 'ref_no' => 'A1', 'addr' => null],
            ['id' => 2, 'ref_no' => 'A2', 'addr' => null],
        ]);
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption()
    {
        $a = (new Invoice1($this->db))->ref('addr');

        // test caption of containsOne reference
        $this->assertSame('Secret Code', $a->getField('door_code')->getCaption());
        $this->assertSame('Secret Code', $a->refModel('door_code')->getModelCaption());
        $this->assertSame('Secret Code', $a->ref('door_code')->getModelCaption());
    }

    /**
     * Test containsOne.
     */
    public function testContainsOne()
    {
        $i = new Invoice1($this->db);
        $i->loadBy('ref_no', 'A1');

        // check do we have address set
        $a = $i->ref('addr');
        $this->assertFalse($a->loaded());

        // now store some address
        $a->setMulti($row = ['id' => 1, 'country_id' => 1, 'address' => 'foo', 'built_date' => new \DateTime('2019-01-01 UTC'), 'tags' => ['foo', 'bar'], 'door_code' => null]);
        $a->save();

        // now reload invoice and see if it is saved
        $this->assertEquals($row, $i->ref('addr')->get());
        $i->reload();
        $this->assertEquals($row, $i->ref('addr')->get());

        // now try to change some field in address
        $i->ref('addr')->set('address', 'bar')->save();
        $this->assertSame('bar', $i->ref('addr')->get('address'));

        // now add nested containsOne - DoorCode
        $c = $i->ref('addr')->ref('door_code');
        $c->setMulti($row = ['id' => 1, 'code' => 'ABC', 'valid_till' => new \DateTime('2019-07-01 UTC')]);
        $c->save();
        $this->assertEquals($row, $i->ref('addr')->ref('door_code')->get());

        // update DoorCode
        $i->reload();
        $i->ref('addr')->ref('door_code')->save(['code' => 'DEF']);
        $this->assertEquals(array_merge($row, ['code' => 'DEF']), $i->ref('addr')->ref('door_code')->get());

        // try hasOne reference
        $c = $i->ref('addr')->ref('country_id');
        $this->assertSame('Latvia', $c->get('name'));
        $i->ref('addr')->set('country_id', 2)->save();
        $c = $i->ref('addr')->ref('country_id');
        $this->assertSame('United Kingdom', $c->get('name'));

        // let's test how it all looks in persistence without typecasting
        $exp_addr = $i->setOrder('id')->export(null, null, false)[0]['addr'];
        $this->assertSame(
            '{"id":1,"country_id":"2","address":"bar","built_date":"2019-01-01T00:00:00+00:00","tags":"[\"foo\",\"bar\"]","door_code":"{\"id\":1,\"code\":\"DEF\",\"valid_till\":\"2019-07-01T00:00:00+00:00\"}"}',
            $exp_addr
        );

        // so far so good. now let's try to delete door_code
        $i->ref('addr')->ref('door_code')->delete();
        $this->assertNull($i->ref('addr')->get('door_code'));
        $this->assertFalse($i->ref('addr')->ref('door_code')->loaded());

        // and now delete address
        $i->ref('addr')->delete();
        $this->assertNull($i->get('addr'));
        $this->assertFalse($i->ref('addr')->loaded());

        //var_dump($i->export(), $i->export(null,null,false));
    }

    /**
     * How containsOne performs when not all values are stored or there are more values in DB than fields in model.
     */
    public function testContainsOneWhenChangeModelFields()
    {
        $i = new Invoice1($this->db);
        $i->loadBy('ref_no', 'A1');

        // with address
        $a = $i->ref('addr');
        $a->setMulti($row = ['id' => 1, 'country_id' => 1, 'address' => 'foo', 'built_date' => new \DateTime('2019-01-01'), 'tags' => [], 'door_code' => null]);
        $a->save();

        // now let's add one more field in address model and save
        $a->addField('post_index');
        $a->set('post_index', 'LV-1234');
        $a->save();

        $this->assertEquals(array_merge($row, ['post_index' => 'LV-1234']), $a->get());

        // now this one is a bit tricky
        // each time you call ref() it returns you new model object so it will not have post_index field
        $this->assertFalse($i->ref('addr')->hasField('post_index'));

        // now reload invoice just in case
        $i->reload();

        // and it references to same old Address model without post_index field - no errors
        $a = $i->ref('addr');
        $this->assertEquals($row, $a->get());
    }

    /*
     * Model should be loaded before traversing to containsOne relation.
     * Imants: it looks that this is not actually required - disabling.
     */
    /*
    public function testEx1()
    {
        $i = new Invoice1($this->db);
        $this->expectException(Exception::class);
        $i->ref('addr');
    }
    */
}
