<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * @coversDefaultClass \Atk4\Data\Model
 */
class ReferenceTest extends AtkPhpunit\TestCase
{
    public function testBasicReferences()
    {
        $user = new Model(['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user->setId(1);

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', [$order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders');

        $this->assertSame(20, $o->get('amount'));
        $this->assertSame(1, $o->get('user_id'));

        $user->hasMany('BigOrders', function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        });

        $this->assertSame(100, $user->ref('BigOrders')->get('amount'));
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption()
    {
        $user = new Model(['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user->setId(1);

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', [$order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        $this->assertSame('My Orders', $user->refModel('Orders')->getModelCaption());
        $this->assertSame('My Orders', $user->ref('Orders')->getModelCaption());
    }

    public function testModelProperty()
    {
        $db = new Persistence();
        $user = new Model($db, ['table' => 'user']);
        $user->setId(1);
        $user->hasOne('order_id', ['model' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order_id');
        $this->assertSame('order', $o->table);
    }

    public function testRefName1()
    {
        $user = new Model(['table' => 'user']);
        $order = new Model();
        $order->addField('user_id');

        $user->hasMany('Orders', $order);
        $this->expectException(Exception::class);
        $user->hasMany('Orders', $order);
    }

    public function testRefName2()
    {
        $order = new Model(['table' => 'order']);
        $user = new Model(['table' => 'user']);

        $user->hasOne('user_id', $user);
        $this->expectException(Exception::class);
        $user->hasOne('user_id', $user);
    }

    public function testRefName3()
    {
        $db = new Persistence();
        $order = new Model($db, ['table' => 'order']);
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });
        $this->expectException(Exception::class);
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });
    }

    public function testCustomRef()
    {
        $p = new Persistence\Array_();

        $m = new Model($p, ['table' => 'user']);
        $m->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });

        $this->assertSame('user_archive', $m->ref('archive')->table);
    }
}
