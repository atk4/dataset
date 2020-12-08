
.. _Hooks:

=====
Hooks
=====

Hook is a mechanism for adding callbacks. The core features of Hook sub-system
(explained in detail here http://agile-core.readthedocs.io/en/develop/hook.html)
include:

 - ability to define "spots" in PHP code, such as "beforeLoad".
 - ability to add callbacks to be executed when PHP goes over the spot.
 - prioritization of callbacks
 - ability to pass arguments to callbacks
 - ability to collect response from callbacks
 - ability to break hooks (will stop any other hook execution)

:php:ref:`Model` implements hook trait and defines various hooks which will allow
you to execute code before or after various operations, such as save, load etc.

Model Operation Hooks
=====================

All of model operations (adding, updating, loading and deleting) have two
hooks - one that executes before operation and another that executes after.

Those hooks are database-agnostic, so regardless where you save your model data,
your `beforeSave` hook will be triggered.

If database has transaction support, then hooks will be executed while inside
the same transaction:

 - begin transaction
 - beforeSave hook
 - actual save
 - reload (see :php:attr:`Model::_reload_after_save`)
 - afterSave hook
 - commit transaction
 
 In case of error:
 
  - do rollback
  - call onRollback hook

If your afterSave hook creates exception, then the entire operation will be
rolled back.

Example with beforeSave
-----------------------

The next code snippet demonstrates a basic usage of a `beforeSave` hook.
This one will update field values just before record is saved::

    $m->onHook(Model::HOOK_BEFORE_SAVE, function($m) {
        $m->set('name', strtoupper($m->get('name')));
        $m->set('surname', strtoupper($m->get('surname')));
    });

    $m->insert(['name'=>'John', 'surname'=>'Smith']);

    // Will save into DB:  ['name'=>'JOHN', 'surname'=>'SMITH'];

Arguments
---------

When you define a callback, then you'll receive reference to model from all the
hooks.
It's important that you use this argument instead of $this to perform operation,
otherwise you can run into problems with cloned models.

Callbacks does non expect anything to be returned, but you can modify fields
of the model.

Interrupting
------------

You can also break all "before" hooks which will result in cancellation of the
original action::

    $m->breakHook(false);

If you break beforeSave, then the save operation will not take place, although
model will assume the operation was successful.

You can also break beforeLoad hook which can be used to skip rows::

    $model->onHook(Model::HOOK_AFTER_LOAD, function ($m) {
        if ($m->get('date') < $m->date_from) {
            $m->breakHook(false); // will not yield such data row
        }
        // otherwise yields data row
    });

This will also prevent data from being loaded. If you return false from
afterLoad hook, then record which we just loaded will be instantly unloaded.
This can be helpful in some cases, although you should still use
:php:meth:`Model::addCondition` where possible as it is much more efficient.

Insert/Update Hooks
-------------------

Insert/Update are triggered from inside save() method but are based on current
state of :php:meth:`Model::loaded`:

 - beforeInsert($m, &$data) (creating new records only)
 - afterInsert($m, $id)
 - beforeUpdate($m, &$data) (updating existing records only. Not executed if model is not dirty)
 - afterUpdate($m)

The $data argument will contain array of actual data (field=>value) to be saved,
which you can use to withdraw certain fields from actually being saved into the
database (by unsetting it's value).

Note that altering data via $m->set() does not work in beforeInsert and beforeUpdate
hooks, only by altering $data.

afterInsert will receive either $id of new record or null if model couldn't
provide ID field. Also, afterInsert is actually called before
:php:meth:`Model::_reload_after_save` reloading is done.

For some examples, see :ref:`soft_delete`

beforeSave, afterSave Hook
--------------------------

A good place to hook is beforeSave as it will be fired when adding new records
or modifying existing ones:

 - beforeSave($m) (saving existing or new records. Not executed if model is not dirty)
 - afterSave($m, $is_update) (same as above, $is_update is boolean true if it was update and false otherwise)

You might consider "save" to be a higher level hook, as beforeSave is called
pretty early on during saving the record and afterSave is called at the very end
of save.

You may actually drop validation exception inside save, insert or update hooks::

    $m->onHook(Model::HOOK_BEFORE_SAVE, function($m) {
        if ($m->get('name') === 'Yagi') {
            throw new \Atk4\Data\ValidationException(['name' => "We don't serve like you"]);
        }
    });

Loading, Deleting
-----------------

Those are relatively simple hooks:

 - beforeLoad($m, $id) ($m will be unloaded). Break for custom load or skip.
 - afterLoad($m). ($m will contain data). Break to unload and skip.

For the deletion it's pretty similar:

 - beforeDelete($m, $id). Unload and Break to preserve record.
 - afterDelete($m, $id).

A good place to clean-up delete related records would be inside afterDelete,
although if your database consistency requires those related records to be
cleaned up first, use beforeDelete instead.

For some examples, see :ref:`soft_delete`


Hook execution sequence
-----------------------

- beforeSave 

  - beforeInsert [only if insert]
    - beforeInsertQuery [sql only] (query)
    - afterInsertQuery (query, statement)

  - beforeUpdate [only if update]
    - beforeUpdateQuery [sql only] (query)
    - afterUpdateQuery (query, statement)


  - afterUpdate [only if existing record, model is reloaded]
  - afterInsert [only if new record, model not reloaded yet]

  - beforeUnload
  - afterUnload

- afterSave (bool $is_update) [after insert or update, model is reloaded]

How to verify Updates
---------------------

The model is only being saved if any fields have been changed (dirty).
Sometimes it's possible that the record in the database is no longer available
and your update() may not actually update anything. This does not normally
generate an error, however if you want to actually make sure that update() was
effective, you can implement this through a hook::

    $m->onHook(Persistence\Sql::HOOK_AFTER_UPDATE_QUERY, function($m, $update, $st) {
        if (!$st->rowCount()) {
            throw (new \Atk4\Core\Exception('Update didn\'t affect any records'))
                ->addMoreInfo('query', $update->getDebugQuery())
                ->addMoreInfo('statement', $st)
                ->addMoreInfo('model', $m)
                ->addMoreInfo('conditions', $m->conditions);
        }
    });


How to prevent actions
----------------------

In some cases you want to prevent default actions from executing.
Suppose you want to check 'memcache' before actually loading the record from
the database. Here is how you can implement this functionality::

    $m->onHook(Model::HOOK_BEFORE_LOAD, function($m, $id) {
        $data = $m->getApp()->cacheFetch($m->table, $id);
        if ($data) {
            $m->data = $data;
            $m->setId($id);
            $m->breakHook(false);
        }
    });

$app property is injected through your $db object and is passed around to all
the models. This hook, if successful, will prevent further execution of other
beforeLoad hooks and by specifying argument as 'false' it will also prevent call
to $persistence for actual loading of the data.

Similarly you can prevent deletion if you wish to implement
:ref:`soft-delete` or stop insert/modify from occurring.


onRollback Hook
---------------

This hook is executed right after transaction fails and rollback is done.
This can be used in various situations.

Save information into auditLog about failure:

    $m->onHook(Model::HOOK_ROLLBACK, function($m){ 
        $m->auditLog->registerFailure();
    });

Upgrade schema:

    use Atk4\Dsql\Exception as DsqlException;

    $m->onHook(Model::HOOK_ROLLBACK, function($m, $exception) { 
        if ($exception instanceof DsqlException) {
            $m->schema->upgrade();
            $m->breakHook(false); // exception will not be thrown
        }
    });

In first example we will register failure in audit log, but afterwards still throw exception.
In second example we will upgrade model schema and will not throw exception at all because we
break hook and return false boolean value.



Persistence Hooks
=================

Persistence has a few spots which it actually executes through $model->hook(),
so depending on where you save the data, there are some more hooks available.

Persistence\Sql
---------------

Those hooks can be used to affect queries before they are executed.
None of these are breakable:

 - beforeUpdateQuery($m, $dsql_query)
 - afterUpdateQuery($m, $statement). Executed before retrieving data.
 - beforeInsertQUery($m, $dsql_query)
 - afterInsertQuery($m, $statement). Executed before retrieving data.

The delete has only "before" hook:

 - beforeDeleteQuery($m, $dsql_query)

Finally for queries there is hook ``initSelectQuery($m, $query, $type)``.
It can be used to enhance queries generated by "action" for:

 - "count"
 - "update"
 - "delete"
 - "select"
 - "field"
 - "fx" or "fx0"



Other Hooks:
============


.. todo: The following hooks need documentation:

    - onlyFields
    - normalize
    - afterAdd
