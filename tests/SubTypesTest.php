<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;

class StAccount extends Model
{
    public $table = 'account';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Transactions', new StGenericTransaction())
            ->addField('balance', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->hasMany('Transactions:Deposit', new StTransaction_Deposit());
        $this->hasMany('Transactions:Withdrawal', new StTransaction_Withdrawal());
        $this->hasMany('Transactions:Ob', new StTransaction_Ob())
            ->addField('opening_balance', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->hasMany('Transactions:TransferOut', new StTransaction_TransferOut());
        $this->hasMany('Transactions:TransferIn', new StTransaction_TransferIn());
    }

    public static function open($persistence, $name, $amount = 0)
    {
        $m = new self($persistence);
        $m->save(['name' => $name]);

        if ($amount) {
            $m->ref('Transactions:Ob')->save(['amount' => $amount]);
        }

        return $m;
    }

    public function deposit($amount)
    {
        return $this->ref('Transactions:Deposit')->save(['amount' => $amount]);
    }

    public function withdraw($amount)
    {
        return $this->ref('Transactions:Withdrawal')->save(['amount' => $amount]);
    }

    public function transferTo(self $account, $amount)
    {
        $out = $this->ref('Transactions:TransferOut')->save(['amount' => $amount]);
        $in = $account->ref('Transactions:TransferIn')->save(['amount' => $amount, 'link_id' => $out->getId()]);
        $out->set('link_id', $in->getId());
        $out->save();
    }
}

class StGenericTransaction extends Model
{
    public $table = 'transaction';
    public $type;

    protected function init(): void
    {
        parent::init();

        $this->hasOne('account_id', new StAccount());
        $this->addField('type', ['enum' => ['Ob', 'Deposit', 'Withdrawal', 'TransferOut', 'TransferIn']]);

        if ($this->type) {
            $this->addCondition('type', $this->type);
        }
        $this->addField('amount', ['type' => 'money']);

        $this->onHookShort(Model::HOOK_AFTER_LOAD, function () {
            if (static::class !== $this->getClassName()) {
                $cl = $this->getClassName();
                $cl = new $cl($this->persistence);
                $cl->load($this->getId());

                $this->breakHook($cl);
            }
        });
    }

    public function getClassName()
    {
        return __NAMESPACE__ . '\StTransaction_' . $this->get('type');
    }
}

class StTransaction_Ob extends StGenericTransaction
{
    public $type = 'Ob';
}

class StTransaction_Deposit extends StGenericTransaction
{
    public $type = 'Deposit';
}

class StTransaction_Withdrawal extends StGenericTransaction
{
    public $type = 'Withdrawal';
}

class StTransaction_TransferOut extends StGenericTransaction
{
    public $type = 'TransferOut';

    protected function init(): void
    {
        parent::init();
        $this->hasOne('link_id', new StTransaction_TransferIn());

        //$this->join('transaction','linked_transaction');
    }
}

class StTransaction_TransferIn extends StGenericTransaction
{
    public $type = 'TransferIn';

    protected function init(): void
    {
        parent::init();
        $this->hasOne('link_id', new StTransaction_TransferOut());
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class SubTypesTest extends \Atk4\Schema\PhpunitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our three models
        $this->getMigrator(new StAccount($this->db))->dropIfExists()->create();
        $this->getMigrator(new StTransaction_TransferOut($this->db))->dropIfExists()->create();
    }

    public function testBasic()
    {
        $inheritance = StAccount::open($this->db, 'inheritance', 1000);
        $current = StAccount::open($this->db, 'current');

        $inheritance->transferTo($current, 500);
        $current->withdraw(350);

        $this->assertSame(StTransaction_Ob::class, get_class($inheritance->ref('Transactions')->load(1)));
        $this->assertSame(StTransaction_TransferOut::class, get_class($inheritance->ref('Transactions')->load(2)));
        $this->assertSame(StTransaction_TransferIn::class, get_class($current->ref('Transactions')->load(3)));
        $this->assertSame(StTransaction_Withdrawal::class, get_class($current->ref('Transactions')->load(4)));

        $cl = [];
        foreach ($current->ref('Transactions') as $tr) {
            $cl[] = get_class($tr);
        }

        $this->assertSame([StTransaction_TransferIn::class, StTransaction_Withdrawal::class], $cl);
    }
}
