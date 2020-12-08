<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Static_ as Persistence_Static;

/**
 * Sample trait designed to extend model.
 *
 * @target Model
 */
trait UaReminder
{
    public function send_reminder()
    {
        $this->save(['reminder_sent' => true]);

        return 'sent reminder to ' . $this->getTitle();
    }

    public function backup_clients()
    {
        return 'backs up all clients';
    }
}

class UaClient extends Model
{
    use UaReminder;
    public $caption = 'UaClient';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('reminder_sent', ['type' => 'boolean']);

        // this action can be invoked from UI
        $this->addUserAction('send_reminder');

        // this action will be system action, so it will not be invokable from UI
        $this->addUserAction('backup_clients', ['appliesTo' => Model\UserAction::APPLIES_TO_ALL_RECORDS, 'system' => true]);
    }
}

/**
 * Implements various tests for UserAction.
 */
class UserActionTest extends \Atk4\Schema\PhpunitTestCase
{
    public $pers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name' => 'John'],
            2 => ['name' => 'Peter'],
        ]);
    }

    public function testBasic()
    {
        $client = new UaClient($this->pers);

        $actions = $client->getUserActions();
        $this->assertSame(4, count($actions)); // don't return system actions here, but include add/edit/delete
        $this->assertSame(0, count($client->getUserActions(Model\UserAction::APPLIES_TO_ALL_RECORDS))); // don't return system actions here

        $act1 = $actions['send_reminder'];

        // action takes no arguments. If it would, we should be able to find info about those
        $this->assertSame([], $act1->args);
        $this->assertSame(Model\UserAction::APPLIES_TO_SINGLE_RECORD, $act1->appliesTo);

        // load record, before executing, because scope is single record
        $client->load(1);

        $this->assertNotTrue($client->get('reminder_sent'));
        $res = $act1->execute();
        $this->assertTrue($client->get('reminder_sent'));

        $this->assertSame('sent reminder to John', $res);
        $client->unload();

        // test system action
        $act2 = $client->getUserAction('backup_clients');

        // action takes no arguments. If it would, we should be able to find info about those
        $this->assertSame([], $act2->args);
        $this->assertSame(Model\UserAction::APPLIES_TO_ALL_RECORDS, $act2->appliesTo);

        $res = $act2->execute();
        $this->assertSame('backs up all clients', $res);

        // non-existing action
        $this->assertFalse($client->hasUserAction('foo'));
    }

    public function testPreview()
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('say_name', function ($m) {
            return $m->get('name');
        });

        $client->load(1);
        $this->assertSame('John', $client->getUserAction('say_name')->execute());

        $client->getUserAction('say_name')->preview = function ($m, $arg) {
            return ($m instanceof UaClient) ? 'will say ' . $m->get('name') : 'will fail';
        };
        $this->assertSame('will say John', $client->getUserAction('say_name')->preview('x'));

        $client->addUserAction('also_backup', ['callback' => 'backup_clients']);
        $this->assertSame('backs up all clients', $client->getUserAction('also_backup')->execute());

        $client->getUserAction('also_backup')->preview = 'backup_clients';
        $this->assertSame('backs up all clients', $client->getUserAction('also_backup')->preview());

        $this->assertSame('Also Backup UaClient', $client->getUserAction('also_backup')->getDescription());
    }

    public function testPreviewFail()
    {
        $this->expectExceptionMessage('specify preview callback');
        $client = new UaClient($this->pers);
        $client->getUserAction('backup_clients')->preview();
    }

    public function testAppliesTo1()
    {
        $client = new UaClient($this->pers);
        $this->expectExceptionMessage('load existing record');
        $client->executeUserAction('send_reminder');
    }

    public function testAppliesTo2()
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('new_client', ['appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS]);
        $client->load(1);

        $this->expectExceptionMessage('can be executed on non-existing record');
        $client->executeUserAction('new_client');
    }

    public function testAppliesTo3()
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('new_client', ['appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS, 'atomic' => false]);

        $this->expectExceptionMessage('not defined');
        $client->executeUserAction('new_client');
    }

    public function testException1()
    {
        $this->expectException(\Atk4\Core\Exception::class);
        $client = new UaClient($this->pers);
        $client->getUserAction('non_existant_action');
    }

    public function testDisabled1()
    {
        $client = new UaClient($this->pers);
        $client->load(1);

        $client->getUserAction('send_reminder')->enabled = false;

        $this->expectExceptionMessage('disabled');
        $client->getUserAction('send_reminder')->execute();
    }

    public function testDisabled2()
    {
        $client = new UaClient($this->pers);
        $client->load(1);

        $client->getUserAction('send_reminder')->enabled = function () {
            return false;
        };

        $this->expectExceptionMessage('disabled');
        $client->getUserAction('send_reminder')->execute();
    }

    public function testDisabled3()
    {
        $client = new UaClient($this->pers);
        $client->load(1);

        $client->getUserAction('send_reminder')->enabled = function () {
            return true;
        };

        $client->getUserAction('send_reminder')->execute();
        $this->assertTrue(true); // no exception
    }

    public function testFields()
    {
        $client = new UaClient($this->pers);
        $a = $client->addUserAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client->load(1);

        $this->assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $a->execute();
        $this->assertSame('Peter', $client->get('name'));
    }

    public function testFieldsTooDirty1()
    {
        $client = new UaClient($this->pers);
        $a = $client->addUserAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client->load(1);

        $this->assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $client->set('reminder_sent', true);
        $this->expectExceptionMessage('dirty fields');
        $a->execute();
        $this->assertSame('Peter', $client->get('name'));
    }

    public function testFieldsIncorrect()
    {
        $client = new UaClient($this->pers);
        $a = $client->addUserAction('change_details', ['callback' => 'save', 'fields' => 'whops_forgot_brackets']);

        $client->load(1);

        $this->assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $this->expectExceptionMessage('array');
        $a->execute();
        $this->assertSame('Peter', $client->get('name'));
    }

    public function testConfirmation()
    {
        $client = new UaClient($this->pers);
        $client->load(1);
        $action = $client->addUserAction('test');

        $this->assertFalse($action->getConfirmation());

        $action->confirmation = true;
        $this->assertSame('Are you sure you wish to execute Test using John?', $action->getConfirmation());

        $action->confirmation = 'Are you sure?';
        $this->assertSame('Are you sure?', $action->getConfirmation());

        $action->confirmation = function ($action) {
            return 'Proceed with Test: ' . $action->getModel()->getTitle();
        };
        $this->assertSame('Proceed with Test: John', $action->getConfirmation());
    }
}
