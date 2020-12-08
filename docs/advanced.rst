
===============
Advanced Topics
===============

Agile Data allow you to implement various tricks.


SubTypes
========

Disjoint subtypes is a concept where you give your database just a little bit of
OOP by allowing to extend addional types without duplicating columns. For example,
if you are implementing "Account" and "Transaction" models. You may want to have
multiple transaction types. Some of those types would even require additional
fields. The pattern suggest you should add a new table "transaction_transfer" and
store extra fields there. In your code::

    class Transaction_Transfer extends Transaction {
        function init(): void {
            parent::init();
            $j = $this->join('transaction_transfer.transaction_id');
            $j->addField('destination_account');
        }
    }

(See also: http://nearly.guru/blog/data/disjoint-subtypes-in-php)

As you implement single Account and multiple Transaction types, you want to relate
both::

    $account->hasMany('Transactions', new Transaction());

There are however two difficulties here:

 1. sometimes you want to operate with specific sub-type.
 2. when iterating, you want to have appropriate class, not Transaction()

Best practice for specifying relation type
------------------------------------------

Although there is no magic behind it, I recommend that you use the following
code pattern when dealing with multiple types::

    $account->hasMany('Transactions', new Transaction());
    $account->hasMany('Transactions:Deposit', new Transaction\Deposit());
    $account->hasMany('Transactions:Transfer', new Transaction\Transfer());

You can then use type-specific reference::

    $account->ref('Transaction:Deposit')->insert(['amount'=>10]);

and the code would be clean. If you introduce new type, you would have to add
extra line to your "Account" model, but it will not be impacting anything, so
that should be pretty safe.

Type substitution on loading
----------------------------

Another technique is for ATK Data to replace your object when data is being
loaded. You can treat "Transaction" class as a "shim"::

    $obj = $account->ref('Transactions')->load(123);

Normally $obj would be instance of `Transaction` class, however we want this
class to be selected based on transaction type. Therefore a more broad
record for 'Transaction' should be loaded first and then, if necessary,
replaced with the correct class transparently, so that the code above
would work without a change.

Another scenario which could benefit by type substitution would be::

    foreach($accoutn->ref('Transactions') as $tr) {
        echo get_class($tr)."\n";
    }

ATK Data allow class substitution during load and iteration by breaking "afterLoad"
hook. Place the following inside Transaction::init()::

    $this->onHookShort(Model::HOOK_AFTER_LOAD, function () {
        if (get_class($this) != $this->getClassName()) {
            $cl = '\\'.$this->getClassName();
            $cl = new $cl($this->persistence);
            $cl->load($this->getId());

            $this->breakHook($cl);
        }
    });

You would need to implement method "getClassName" which would return DESIRED class
of the record. Finally to help with performance, you can implement a switch::

    public $typeSubstitution = false;

    ...

    function init(): void {
        ..

        if ($this->typeSubstitution) {
            $this->onHook(Model::HOOK_AFTER_LOAD,
                ..........
            )
        }
    }

Now, every time you iterate (or load) you can decide if you want to invoke type
substitution::

    foreach($account->ref('Transactions', ['typeSubstitution'=>true]) as $tr) {

        $tr->verify();  // verify() method can be overloaded!
    }


    // however, for export, we don't need expensive substitution
    $transaction_data = $account->ref('Transaction')->export();

Audit Fields
============

If you wish to have a certain field inside your models that will be automatically
changed when the record is being updated, this can be easily implemented in
Agile Data.

I will be looking to create the following fields:

- created_dts
- updated_dts
- created_by_user_id
- updated_by_user_id

To implement the above, I'll create a new class::

    class Controller_Audit {

        use \Atk4\Core\InitializerTrait {
            init as _init;
        }
        use \Atk4\Core\TrackableTrait;
        use \Atk4\Core\AppScopeTrait;

    }

TrackableTrait means that I'll be able to add this object inside model with
``$model->add(new Controller_Audit())`` and that will automatically populate
$owner, and $app values (due to AppScopeTrait) as well as execute init() method,
which I want to define like this::


    protected function init(): void {
        $this->_init();

        if(isset($this->getOwner()->no_audit)){
            return;
        }

        $this->getOwner()->addField('created_dts', ['type'=>'datetime', 'default'=>new \DateTime()]);

        $this->getOwner()->hasOne('created_by_user_id', 'User');
        if(isset($this->getApp()->user) && $this->getApp()->user->loaded()) {
            $this->getOwner()->getField('created_by_user_id')->default = $this->getApp()->user->getId();
        }

        $this->getOwner()->hasOne('updated_by_user_id', 'User');

        $this->getOwner()->addField('updated_dts', ['type'=>'datetime']);

        $this->getOwner()->onHook(Model::HOOK_BEFORE_UPDATE, function($m, $data) {
            if(isset($this->getApp()->user) && $this->getApp()->user->loaded()) {
                $data['updated_by'] = $this->getApp()->user->getId();
            }
            $data['updated_dts'] = new \DateTime();
        });
    }

In order to add your defined behavior to the model. The first check actually
allows you to define models that will bypass audit altogether::

    $u1 = new Model_User($db);   // Model_User::init() includes audit

    $u2 = new Model_User($db, ['no_audit' => true]);  // will exclude audit features

Next we are going to define 'created_dts' field which will default to the
current date and time.

The default value for our 'created_by_user_id' field would depend on a currently
logged-in user, which would typically be accessible through your application.
AppScope allows you to pass $app around through all the objects, which means
that your Audit Controller will be able to get the current user.

Of course if the application is not defined, no default is set. This would be
handy for unit tests where you could manually specify the value for this field.

The last 2 fields (update_*) will be updated through a hook - beforeUpdate() and
will provide the values to be saved during ``save()``. beforeUpdate() will not
be called when new record is inserted, so those fields will be left as "null"
after initial insert.

If you wish, you can modify the code and insert historical records into other
table.

.. _soft_delete:

Soft Delete
===========

Most of the data frameworks provide some way to enable 'soft-delete' for tables
as a core feature. Design of Agile Data makes it possible to implement soft-delete
through external controller. There may be a 3rd party controller for comprehensive
soft-delete, but in this section I'll explain how you can easily build your own
soft-delete controller for Agile Data (for educational purposes).

Start by creating a class::

    class Controller_SoftDelete {

        use \Atk4\Core\InitializerTrait {
            init as _init;
        }
        use \Atk4\Core\TrackableTrait;

        function init(): void {
            $this->_init();

            if(isset($this->getOwner()->no_soft_delete)){
                return;
            }

            $this->getOwner()->addField('is_deleted', ['type'=>'boolean']);

            if (isset($this->getOwner()->deleted_only)) {
                $this->getOwner()->addCondition('is_deleted', true);
                $this->getOwner()->addMethod('restore', \Closure::fromCallable([$this, 'restore']));
            } else {
                $this->getOwner()->addCondition('is_deleted', false);
                $this->getOwner()->addMethod('softDelete', \Closure::fromCallable([$this, 'softDelete']));
            }
        }

        function softDelete($m) {
            if (!$m->loaded()) {
                throw (new \Atk4\Core\Exception('Model must be loaded before soft-deleting'))->addMoreInfo('model', $m);
            }

            $id = $m->getId();
            if ($m->hook('beforeSoftDelete') === false) {
                return $m;
            }

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>true])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterSoftDelete', [$id]);
            return $m;
        }

        function restore($m) {
            if (!$m->loaded()) {
                throw (new \Atk4\Core\Exception(['Model must be loaded before restoring'))->addMoreInfo('model', $m);
            }

            $id = $m->getId();
            if ($m->hook('beforeRestore') === false) {
                return $m;
            }

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>false])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterRestore', [$id]);
            return $m;
        }
    }

This implementation of soft-delete can be turned off by setting model's property
'deleted_only' to true (if you want to recover a record).

When active, a new field will be defined 'is_deleted' and a new dynamic method
will be added into a model, allowing you to do this::

    $m = new Model_Invoice($db);
    $m->load(10);
    $m->softDelete();

The method body is actually defined in our controller. Notice that we have
defined 2 hooks - beforeSoftDelete and afterSoftDelete that work similarly to
beforeDelete and afterDelete.

beforeSoftDelete will allow you to "break" it in certain cases to bypass the
rest of method, again, this is to maintain consistency with the rest of before*
hooks in Agile Data.

Hooks are called through the model, so your call-back will automatically receive
first argument $m, and afterSoftDelete will pass second argument - $id of deleted
record.

I am then setting reload_after_save value to false, because after I set
'is_deleted' to false, $m will no longer be able to load the record - it will
fall outside of the DataSet. (We might implement a better method for saving
records outside of DataSet in the future).

After softDelete active record is unloaded, mimicking behavior of delete().

It's also possible for you to easily look at deleted records and even restore
them::

    $m = new Model_Invoice($db, ['deleted_only'=>true]);
    $m->load(10);
    $m->restore();

Note that you can call $m->delete() still on any record to permanently delete it.

Soft Delete that overrides default delete()
-------------------------------------------

In case you want $m->delete() to perform soft-delete for you - this can also be
achieved through a pretty simple controller. In fact I'm reusing the one from
before and just slightly modifying it::

    class Controller_SoftDelete {

        use \Atk4\Core\InitializerTrait {
            init as _init;
        }
        use \Atk4\Core\TrackableTrait;

        function init(): void {
            $this->_init();

            if(isset($this->getOwner()->no_soft_delete)){
                return;
            }

            $this->getOwner()->addField('is_deleted', ['type'=>'boolean']);

            if (isset($this->getOwner()->deleted_only)) {
                $this->getOwner()->addCondition('is_deleted', true);
                $this->getOwner()->addMethod('restore', \Closure::fromCallable([$this, 'restore']));
            } else {
                $this->getOwner()->addCondition('is_deleted', false);
                $this->getOwner()->onHook(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'softDelete']), null, 100);
            }
        }

        function softDelete(Model $m) {
            if (!$m->loaded()) {
                throw (new \Atk4\Core\Exception('Model must be loaded before soft-deleting'))->addMoreInfo('model', $m);
            }

            $id = $m->getId();

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>true])->unload();
            $m->reload_after_save = $rs;

            $m->hook(Model::HOOK_AFTER_DELETE, [$id]);

            $m->breakHook(false); // this will cancel original delete()
        }

        function restore($m) {
            if (!$m->loaded()) {
                throw (new \Atk4\Core\Exception('Model must be loaded before restoring'))->addMoreInfo('model', $m);
            }

            $id = $m->getId();
            if ($m->hook('beforeRestore') === false) {
                return $m;
            }

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>false])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterRestore', [$id]);
            return $m;
        }
    }

Implementation of this controller is similar to the one above, however instead
of creating softDelete() it overrides the delete() method through a hook.
It will still call 'afterDelete' to mimic the behavior of regular delete() after
the record is marked as deleted and unloaded.

You can still access the deleted records::

    $m = new Model_Invoice($db, ['deleted_only'=>true]);
    $m->load(10);
    $m->restore();

Calling delete() on the model with 'deleted_only' property will delete it
permanently.

Creating Unique Field
=====================

Database can has UNIQUE constraint, but this does work if you use DataSet.
For instance, you may be only able to create one 'Category' with name 'Book',
but what if there is a soft-deleted record with same name or record that belongs
to another user?

With Agile Data you can create controller that will ensure that certain fields
inside your model are unique::

    class Controller_UniqueFields {
        use \Atk4\Core\InitializerTrait {
            init as _init;
        }
        use \Atk4\Core\TrackableTrait;

        protected $fields = null;

        function init(): void {
            $this->_init();

            // by default make 'name' unique
            if (!$this->fields) {
                $this->fields = [$this->getOwner()->title_field];
            }

            $this->getOwner()->onHook(Model::HOOK_BEFORE_SAVE, \Closure::fromCallable([$this, 'beforeSave']));
        }

        function beforeSave(Model $m)
        {
            foreach ($this->fields as $field) {
                if ($m->dirty[$field]) {
                    $mm = clone $m;
                    $mm->addCondition($mm->id_field != $this->id);
                    $mm->tryLoadBy($field, $m->get($field));

                    if ($mm->loaded()) {
                        throw (new \Atk4\Core\Exception('Duplicate record exists'))
                            ->addMoreInfo('field', $field)
                            ->addMoreInfo('value', $m->get($field));
                    }
                }
            }
        }
    }

As expected - when you add a new model the new values are checked against
existing records. You can also slightly modify the logic to make addCondition
additive if you are verifying for the combination of matched fields.

Using WITH cursors
==================

Many SQL database engines support defining WITH cursors to use in select, update
and even delete statements.

.. php:method:: addWith(Model $model, string $alias, array $fields, bool $recursive = false)

    Agile toolkit data models also support these cursors. Usage is like this::

    $invoices = new Invoice();

    $contacts = new Contact();
    $contacts->addWith($invoices, 'inv', ['contact_id'=>'cid', 'ref_no', 'total_net'=>'invoiced'], false);
    $contacts->join('inv.cid');

.. code-block:: sql

    with
        `inv` (`cid`, `ref_no`, `total_net`) as (select `contact_id`, `ref_no`, `total_net` from `invoice`)
    select
        *
    from `contact`
        join `inv` on `inv`.`cid`=`contact`.`id`

.. note:: Supported starting from MySQL 8.x. MariaDB supported it earlier.

Creating Many to Many relationship
==================================

Depending on the use-case many-to-many relationships can be implemented
differently in Agile Data. I will be focusing on the practical approach.
My system has "Invoice" and "Payment" document and I'd like to introduce
"invoice_payment" that can link both entities together with fields
('invoice_id', 'payment_id', and 'amount_closed').
Here is what I need to do:

1. Create Intermediate Entity - InvoicePayment
----------------------------------------------

Create new Model::

    class Model_InvoicePayment extends \Atk4\Data\Model {
        public $table='invoice_payment';
        function init(): void
        {
            parent::init();
            $this->hasOne('invoice_id', 'Model_Invoice');
            $this->hasOne('payment_id', 'Model_Payment');
            $this->addField('amount_closed');
        }
    }

2. Update Invoice and Payment model
-----------------------------------

Next we need to define reference. Inside Model_Invoice add::

    $this->hasMany('InvoicePayment');

    $this->hasMany('Payment', [function($m) {
        $p = new Model_Payment($m->persistence);
        $j = $p->join('invoice_payment.payment_id');
        $j->addField('amount_closed');
        $j->hasOne('invoice_id', 'Model_Invoice');
    }, 'their_field'=>'invoice_id']);

    $this->onHookShort(Model::HOOK_BEFORE_DELETE, function(){
        $this->ref('InvoicePayment')->action('delete')->execute();

        // If you have important per-row hooks in InvoicePayment
        // $payment = $this->ref('InvoicePayment'); $payment->each(function () use ($payment) { $payment->delete(); });
    });

You'll have to do a similar change inside Payment model. The code for '$j->'
have to be duplicated until we implement method Join->importModel().


3. How to use
-------------

Here are some use-cases. First lets add payment to existing invoice. Obviously
we cannot close amount that is bigger than invoice's total::

    $i->ref('Payment')->insert([
        'amount'=>$paid,
        'amount_closed'=> min($paid, $i->get('total')),
        'payment_code'=>'XYZ'
    ]);

Having some calculated fields for the invoice is handy. I'm adding `total_payments`
that shows how much amount is closed and `amount_due`::

    // define field to see closed amount on invoice
    $this->hasMany('InvoicePayment')
        ->addField('total_payments', ['aggregate'=>'sum', 'field'=>'amount_closed']);
    $this->addExpression('amount_due', '[total]-coalesce([total_payments],0)');

Note that I'm using coalesce because without InvoicePayments the aggregate sum
will return NULL. Finally let's build allocation method, that allocates new
payment towards a most suitable invoice::


    // Add to Model_Payment
    function autoAllocate()
    {
        $client = $this->ref['client_id'];
        $invoices = $client->ref('Invoice');

        // we are only interested in unpaid invoices
        $invoices->addCondition('amount_due', '>', 0);

        // Prioritize older invoices
        $invoices->setOrder('date');

        while($this->get('amount_due') > 0) {

            // See if any invoices match by 'reference';
            $invoices->tryLoadBy('reference', $this->get('reference'));

            if (!$invoices->loaded()) {

                // otherwise load any unpaid invoice
                $invoices->tryLoadAny();

                if(!$invoices->loaded()) {

                    // couldn't load any invoice.
                    return;
                }
            }

            // How much we can allocate to this invoice
            $alloc = min($this->get('amount_due'), $invoices->get('amount_due'))
            $this->ref('InvoicePayment')->insert(['amount_closed'=>$alloc, 'invoice_id'=>$invoices->getId()]);

            // Reload ourselves to refresh amount_due
            $this->reload();
        }
    }

The method here will prioritize oldest invoices unless it finds the one that
has a matching reference. Additionally it will allocate your payment towards
multiple invoices. Finally if invoice is partially paid it will only allocate
what is due.



Creating Related Entity Lookup
==============================

Sometimes when you add a record inside your model you want to specify some
related records not through ID but through other means. For instance, when
adding invoice, I want to make it possible to specify 'Category' through the
name, not only category_id. First, let me illustrate how can I do that with
category_id::

    class Model_Invoice extends \Atk4\Data\Model {
        function init(): void {

            parent::init();

            ...

            $this->hasOne('category_id', 'Model_Category');

            ...
        }
    }

    $m = new Model_Invoice($db);
    $m->insert(['total'=>20, 'client_id'=>402, 'category_id'=>6]);

So in situations when client_id and category_id is not known (such as import or
API call) this approach will require us to perform 2 extra queries::

    $m = new Model_Invoice($db);
    $m->insert([
        'total'=>20,
        'client_id'=>$m->ref('client_id')->loadBy('code', $client_code)->getId(),
        'category_id'=>$m->ref('category_id')->loadBy('name', $category)->getId(),
    ]);

The ideal way would be to create some "non-persistable" fields that can be used
to make things easier::

    $m = new Model_Invoice($db);
    $m->insert([
        'total'=>20,
        'client_code'=>$client_code,
        'category'=>$category
    ]);

Here is how to add them. First you need to create fields::

    $this->addField('client_code', ['never_persist'=>true]);
    $this->addField('client_name', ['never_persist'=>true]);
    $this->addField('category', ['never_persist'=>true]);

I have declared those fields with never_persist so they will never be used by
persistence layer to load or save anything. Next I need a beforeSave handler::

    $this->onHookShort(Model::HOOK_BEFORE_SAVE, function() {
        if($this->_isset('client_code') && !$this->_isset('client_id')) {
            $cl = $this->refModel('client_id');
            $cl->addCondition('code',$this->get('client_code'));
            $this->set('client_id', $cl->action('field',['id']));
        }

        if($this->_isset('client_name') && !$this->_isset('client_id')) {
            $cl = $this->refModel('client_id');
            $cl->addCondition('name', 'like', $this->get('client_name'));
            $this->set('client_id', $cl->action('field',['id']));
        }

        if($this->_isset('category') && !$this->_isset('category_id')) {
            $c = $this->refModel('category_id');
            $c->addCondition($c->title_field, 'like', $this->get('category'));
            $this->set('category_id', $c->action('field',['id']));
        }
    });

Note that isset() here will be true for modified fields only and behaves
differently from PHP's default behavior. See documentation for Model::isset

This technique allows you to hide the complexity of the lookups and also embed
the necessary queries inside your "insert" query.

Fallback to default value
-------------------------

You might wonder, with the lookup like that, how the default values will work?
What if the user-specified entry is not found? Lets look at the code::

    if($m->_isset('category') && !$m->_isset('category_id')) {
        $c = $this->refModel('category_id');
        $c->addCondition($c->title_field, 'like', $m->get('category'));
        $m->set('category_id', $c->action('field',['id']));
    }

So if category with a name is not found, then sub-query will return "NULL".
If you wish to use a different value instead, you can create an expression::

    if($m->_isset('category') && !$m->_isset('category_id')) {
        $c = $this->refModel('category_id');
        $c->addCondition($c->title_field, 'like', $m->get('category'));
        $m->set('category_id', $this->expr('coalesce([],[])',[
            $c->action('field',['id']),
            $m->getField('category_id')->default
        ]));
    }

The beautiful thing about this approach is that default can also be defined
as a lookup query::

    $this->hasOne('category_id','Model_Category');
    $this->getField('category_id')->default =
        $this->refModel('category_id')->addCondition('name','Other')
            ->action('field',['id']);


Inserting Hierarchical Data
===========================

In this example I'll be building API that allows me to insert multi-model
information. Here is usage example::

    $invoice->insert([
        'client'=>'Joe Smith',
        'payment'=>[
            'amount'=>15,
            'ref'=>'half upfront',
        ],
        'lines'=>[
            ['descr'=>'Book','qty'=>3, 'price'=>5]
            ['descr'=>'Pencil','qty'=>1, 'price'=>10]
            ['descr'=>'Eraser','qty'=>2, 'price'=>2.5]
        ],
    ]);

Not only 'insert' but 'set' and 'save' should be able to use those fields for
'payment' and 'lines', so we need to first define those as 'never_persist'.
If you curious about client lookup by-name, I have explained it in the previous
section. Add this into your Invoice Model::

    $this->addField('payment',['never_persist'=>true]);
    $this->addField('lines',['never_persist'=>true]);

Next both payment and lines need to be added after invoice is actually created,
so::

    $this->onHookShort(Model::HOOK_AFTER_SAVE, function($is_update){
        if($this->_isset('payment')) {
            $this->ref('Payment')->insert($this->get('payment'));
        }

        if($this->_isset('lines')) {
            $this->ref('Line')->import($this->get('lines'));
        }
    });

You should never call save() inside afterSave hook, but if you wish to do some
further manipulation, you can reload a clone::

    $mm = clone $m;
    $mm->reload();
    if ($mm->get('amount_due') == 0) {
        $mm->save(['status'=>'paid']);
    }

Related Record Conditioning
===========================

Sometimes you wish to extend one Model into another but related field type
can also change. For example let's say we have Model_Invoice that extends
Model_Document and we also have Model_Client that extends Model_Contact.

In theory Document's 'contact_id' can be any Contact, however when you create
'Model_Invoice' you wish that 'contact_id' allow only Clients. First, lets
define Model_Document::

    $this->hasOne('client_id', 'Model_Contact');

One option here is to move 'Model_Contact' into model property, which will be
different for the extended class::

    $this->hasOne('client_id', $this->client_class);

Alternatively you can replace model in the init() method of Model_Invoice::

    $this->getRef('client_id')->model = 'Model_Client';

You can also use array here if you wish to pass additional information into
related model::

    $this->getRef('client_id')->model = ['Model_Client', 'no_audit'=>true];

Combined with our "Audit" handler above, this should allow you to relate
with deleted clients.

The final use case is when some value inside the existing model should be
passed into the related model. Let's say we have 'Model_Invoice' and we want to
add 'payment_invoice_id' that points to 'Model_Payment'. However we want this
field only to offer payments made by the same client. Inside Model_Invoice add::

    $this->hasOne('client_id', 'Client');

    $this->hasOne('payment_invoice_id', function($m){
        return $m->ref('client_id')->ref('Payment');
    });

    /// how to use

    $m = new Model_Invoice($db);
    $m->set('client_id', 123);

    $m->set('payment_invoice_id', $m->ref('payment_invoice_id')->tryLoadAny()->getId());

In this case the payment_invoice_id will be set to ID of any payment by client
123. There also may be some better uses::

    $cl->ref('Invoice')->each(function($m) {

        $m->set('payment_invoice_id', $m->ref('payment_invoice_id')->tryLoadAny()->getId());
        $m->save();

    });

Narrowing Down Existing References
==================================

Agile Data allow you to define multiple references between same entities, but
sometimes that can be quite useful. Consider adding this inside your Model_Contact::

    $this->hasMany('Invoice', 'Model_Invoice');
    $this->hasMany('OverdueInvoice', function($m){
        return $m->ref('Invoice')->addCondition('due','<',date('Y-m-d'))
    });

This way if you extend your class into 'Model_Client' and modify the 'Invoice'
reference to use different model::

    $this->getRef('Invoice')->model = 'Model_Invoice_Sale';

The 'OverdueInvoice' reference will be also properly adjusted.

