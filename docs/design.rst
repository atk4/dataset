====================================
Introduction to Architectural Design
====================================

Layering is one of the most common techniques that software designers use to
break apart a complicated software system. A modern application would have
three primary layers:

 - Presentation - Display of information (HTML generation, UI, API or CLI interface)
 - Domain - Logic that is the real point of the system
 - Data Source - Communication with databases, messaging systems, transaction
   managers, other packages

A persistence mechanism is a way how you save the data from some kind of
in-memory model to the database. Apart from data-bases modern system also use
REST services or interact with caches or files to load/store data.

Due to implementation specifics of the various data sources, making a "universal"
persistence logic that can store Domain objects efficiently is not a trivial task.
Various frameworks implement "Active Record", "ORM" and "Query Builder" patterns
in attempts to improve data access.

The common problems when trying to simplify mapping of domain logic include:

 - Performance
   - Traversing references where you deal with millions of related records
   - Executing multi-row database operation

 - Reduced features
   - Inability to use vendor-specific features such as SQL expression syntax
   - Derive calculations from multi-row sub-selects
   - Tweak persistence-related operations

 - Abstraction
   - Domain objects are often restricted by database schema
   - Difficult to use Domain objects without database connection (e.g. in Unit Tests)


Agile Data implements a fresh concepts that separates your Domain from persistence
cleanly yet manages to solve problems mentioned above.

The concepts implemented by Agile Data framework may require some getting used
to (especially if you used some traditional ORMs or Active Record implementations
before).

Once you learn the concept behind Agile Data, you'll be able to write "Domain objects"
of your application with ease through a readable code and without impact on your
application performance or feature restrictions.


The Domain Layer Scope
=======================

Agile Data is a framework that will allow you to define your Domain objects
and will map them into database of your choice.

You can use Agile Data with SQL (PDO-compatible) vendors, NoSQL (MongoDB) or
memory Arrays. Support for other database vendors can be added through add-ons.


The Danger of Raw Queries
-------------------------

If you still think that writing SQL queries is the most efficient way to work
with database, you are probably not considering other disadvantages of this
approach:

 - Parameters you specify to a query need to be escaped
 - Complex queries are more difficult to write and debug
 - Various parts of your application may want to change query (soft-delete add-on?)
 - Optimization in your database may impact your Domain logic and even presentation
 - Changing your database vendor or storing object data in cache is harder
 - Difficult to maintain code

There are more problems such as difficulty in unit-testing your Domain object
code.

Purity levels of Domain code
----------------------------

Agile Data focuses on creating "patterns" that can live in "Domain" layer.
There are three levels of code "purity":

 - Implement patterns for working with for Domain objects.
 - Implement patterns for "persistence-backed" Domain objects.
 - Implement extensions for "persisting"

Some of your code will focus on working with Domain object without any concern
about "persistence". A good example is "Validation". When you Validate your
Domain object you just need to check field values, you would not even care where
data came from.

Most of your code, however, will assume existence of SOME "persistence", but
will not rely on anything specific. Calculating total amount of your shopping
basked price is such an operation. Basket items are stored somewhere - array,
SQL or NoSQL and all you need is to calculate sum(amount). You don't even know
how "amount" field is called in the database.

While most of relational mapping solutions would load all basket items, Agile
Data performs same operations inside database if possible.

Finally - some of your code may rely of some specific database vendor
features. Example would be defining an expression using "IF (expr, val1, val2) "
expression for some field of Domain model or using stored procedure as the
source instead of table.

Agile Data offers you ability to move as much code as possible to the level with
highest "purity", but even if you have to write chunk of SQL code, you can do
it without compromising cross-vendor compatibility.


Domain Logic
============

When dealing with Domain logic, you work with a single object.

When we start developing a new application, we first decide on the Model structure.
Think what models your application will use and how they are related. Do not
think in terms of "tables", but rather think in terms of "objects" and properties
of those objects.

All of those model properties are "declared".

Domain Models
-------------

Congratulations, you have just designed a model layer of your application.
Remember that it had nothing to do with your database structure, right?

 - Client
 - Order
 - Admin

A code to declare a model::

    class Model_User extends \Atk4\Data\Model { }

    class Model_Client extends Model_User { }

    class Model_Admin extends Model_User { }

    class Model_Order extends \Atk4\Data\Model { }

Domain Model Methods
--------------------

Next we need to write down various "functions" your application would have to
perform and attribute those to individual models. At the same time think
about object inheritance.

 - User
   - sendPasswordReminder()
 - Client (extends User)
   - register()
   - checkout()
 - Admin (extends User)
   - showAuditLog()
 - Order

Code::

    class Model_Client extends Model_User {
        public function sendPasswordReminder() {

            mail($this->get('email'), 'Your password is: '.$this->get('password'));
        }
    }

At this stage you should not think about "saving" your entries. Think of your
objects as if they would forever exist in your memory. Also don't bother with
basic actions such as adding new order or deleting order.


Domain Model Fields
-------------------

Our next step is to define object fields (or properties). Remember that
inheritance is at play here so you can take advantage of OOP:

 - User
   - name, is_vip, email, password, password_change_date
 - Client
   - phone
 - Admin
   - permission_level
 - Order
   - description, amount, is_paid

Those fields are not just mere "properties", but have more "meta" information
behind them and that's why we call them "fields" and not "properties". A typical
field contain information about field name, caption, type, validation rules,
persistence rules, presentation rules and more. Meta information is optional and
it can be used by automated processes (such as presentation or persistence).

For instance, `is_paid` has a `type('boolean')` which means it will be stored as
1/0 in MySQL, but will use true/false in MongoDB. It will be displayed as a
checkbox.
Those decisions are made by the framework and will simplify your life, however
if you want to do things differently, you will still be able to override default
behavior.

Code to declare fields::

    class Model_Order extends \Atk4\Data\Model {
        protected function init(): void {
            parent::init();

            $this->addField('description');
            $this->addField('amount')->type('money');
            $this->addField('is_paid')->type('boolean');
        }
    }

Code to access field values::

    $order->set('amount', 1200.20);

Domain Model Relationship
-------------------------

Next - references. Think how those objects relate to each-other. Think in terms
of "specific object" and not database relations. Client has many Orders. Order
has one Client.

 - User
   - hasMany(Client)
 - Client
   - hasOne(User)

There are no "many-to-many" relationship in Domain Model because relationships
work from a specific record, but more on that later.

Code (add inside `init()`)::

    class Model_Client extends Model_User {
        protected function init(): void {
            parent::init();

            $this->hasMany('Order', new Model_Order());
        }
    }

    class Model_Order extends \Atk4\Data\Model {
        protected function init(): void {
            parent::init();

            $this->hasOne('Client', new Model_Client());

            // addField declarations
        }
    }



Persistence backed Domain Logic
===============================

Once we establish that Model object and set its persistence layer, we can start
accessing it.
Here is the code::

    $order = new Model_Order();
    // $order is not linked with persistence


    $real_order = $db->add('Model_Order');
    // $real_order is associated with specific persistence layer $db


ID Field
--------
Each object is stored with some unique identifier, so you can load and store
object if you know it's ID::

    $order->load(20);
    $order->set('amount', 1200.20);
    $order->save();


Persistence-specific Code
=========================

Finally, some code may rely on specific features of your persistence layer.


Domain Model Expressions
------------------------

A final addition to our Domain Model are expressions. Those are the "formulas"
where the value cannot be changed directly, but is actually derived from other
values.

  - User
    - is_password_expired
  - Client
    - amount_due, total_order_amount

Here field `is_password_expired` is the type of expression that is based on the
field `password_change_date` and system date. In other words the value of this
expression will be different depending on parameter outside of your app.

Field `amount_due` is a sum of amount for all Orders by specific User for which
condition "is_paid=false" is met. `total_order_amount` is similar, however there
is no condition on the order.

With all of the above we have finished our "Domain Model" declaration.
We haven't done any assumptions on where and how data is stored, which vendor we
are using or how we can ensure that expressions will operate.

This is, however, a good point for you to write the initial batch of the code.

Code::

    class Model_User extends \Atk4\Data\Model {
        protected function init(): void {
            parent::init();

            $this->addField('password');
            $this->addField('password_change_date');

            $this->addExpression('is_password_expired', [
                '[password_change_date] < (NOW() - INTERVAL 1 MONTH)',
                'type' => 'boolean',
            ]);
        }
    }

Persistence Hooks
-----------------

Hooks can help you perform operations when object is being persisted::


    class Model_User extends \Atk4\Data\Model {
        protected function init(): void {
            parent::init();

            // addField() declaration
            // addExpression('is_password_expired')

            $this->onHookShort(Model::HOOK_BEFORE_SAVE, function() {
                if ($this->isDirty('password')) {
                    $this->set('password', encrypt_password($this->get('password')));
                    $this->set('password_change_date', $this->expr('now()'));
                }
            });
        }
    }



DataSet Declaration
===================

So far we have only looked at a single record - one User or one Order. In
practice our application must operate with multiple records.


DataSet is an object that represents collection of Domain model records that
are persisted::

    $order = $db->add('Model_Order');
    $order->load(10);

In scenario above we loaded a specific record. Agile Data does not create a
separate object when loading, instead the same object is re-used. This is done
to preserve some memory.

So in the code above `$order` is not created for the record, but it can load
any record from the DataSet. Think of it as a "window" into a large table of
Orders::

    $sum = 0;
    $order = $db->add('Model_Order');
    $order->load(10);
    $sum += $order->get('amount');

    $order->load(11);
    $sum += $order->get('amount');

    $order->load(13);
    $sum += $order->get('amount');

You can iterate over the DataSet::

    $sum = 0;
    foreach($db->add('Model_Order') as $order) {
        $sum += $order->get('amount');
    }

You must remember that the code above will only create a single object and
iterating it will simply make it load different values.

At this point, I'll jump ahead a bit and will show you an alternative code::

    $sum = $db->add('Model_Order')->fx0(['sum','amount'])->getOne();

It will have same effect as the code above, but will perform operation of
adding up all order amounts inside the database and save you a lot of CPU cycles.

Domain Conditions
=================

If your database has 3 clients - 'Joe', 'Bill', and 'Steve' then the DataSet of
"Client" has 3 records.

DataSet concept lives in "Domain Logic" therefore you can use it safely without
worrying that you will introduce unnecessary bindings into persistence and break
single-purpose principle of your objects::

    foreach ($clients as $client) {
        // echo $client->get('name')."\n";
    }

The above is a Domain Model code. It will iterate through the DataSet of
"Clients" and output 3 names. You can also "narrow down" your DataSet by adding
a restriction::

    $sum = 0;
    foreach($db->add('Model_Order')->addCondition('is_paid', true) as $order) {
        $sum += $order->get('amount');
    }

And again it's much more effective to do this on database side::


    $sum = $db->add('Model_Order')
                ->addCondition('is_paid', true)
                ->fx0(['sum','amount'])
                ->getOne();


Related DataSets
================

Next, let's look on the orders of specific user. How would you load orders of a
specific user.
Depending on your past experience you might think about "querying" Order table
with condition on user_id. We can't do that, because "query", "table" and
"user_id" are persistence details and we must keep them outside of business logic.
Other ORM solution give you something like this::

    $array_of_orders = $user->orders();

Unfortunately this has practical performance implications and scalability
constraints. What if your user is having millions of orders? Even with
lazy-loading, you will be operating with million "id" records.

Agile Data implements traversal as a simple operation that converts one DataSet
into another::

    $user_dataset->addCondition('is_vip', true);
    $vip_orders = $user_dataset->ref('Order');

    $sum = $vip_orders->fx0(['sum','amount'])->getOne();

The implementation of `ref()` is pretty powerful - $user_dataset can address 3
users in the database and only 2 of those users are VIP. Typical ORM would
require you to fetch all VIP records and then perform additional queries to find
their orders.

Agile Data, however, perform traversal without accessing database at all.
After `ref()` is executed, you have a new DataSet with a condition based on
user sub-query. The actual implementation may be different depending on vendor,
but Agile Data will prefer not to fetch list of "user_id"s without need.

Domain Model Actions
--------------------

Persistence layer in Agile Data uses intelligent mapping of your Domain Logic
into DatabaseVendor-specific operations.

To continue my example from above, I'll use a query method to calculate number
of orders placed by VIP clients::

    $vip_order_count = $vip_orders->fx(['count'])->getOne();

This code will attempt to execute a single-query only, however the ability to
optimize your request relies on the capabilities of database vendor.
The actual database operation(s) might look like this on SQL database:

.. code-block:: sql

    select count(*) from `order` where user_id in
        (select id from user where type="user" and is_vip=1)

While with MongoDB, the query could be different::

    $ids = collections.client.find({'is_vip':true}).field('id');
    return collections.order.find({'user_id':$ids}).count();

Finally the code above will work even if you use a simple Array as a data source::

    $db = new \Atk4\Data\Persistence\Array_([
        'client'=>[
            [
                'name'=>'Joe',
                'email'=>'joe@yahoo.com',
                'Orders'=>[
                    ['amount'=>10],
                    ['amount'=>20]
                ]
            ],[
                'name'=>'Bill',
                'email'=>'bill@yahoo.com',
                'Orders'=>[
                    ['amount'=>35]
                ]
            ]
        ]
    ]);

So getting back to the operation above, lets look at it in more details::

    $vip_order_count = $vip_orders->fx(['count'])->getOne();

While "vip_orders" is actually a DataSet, executing count() will cross you over
into persistence layer. However this method is returning a new object, which is then
executed when you call getOne(). For SQL persistences it returns \Atk4\Dsql\Query
object, for example.

Even though for a brief moment you had your hands on a "database-vendor specific"
object, you have immediately converted Action into an actual value. As result
your code is universal and is not persistence-specific. In Agile Data we permit
code like that in our Domain Model and we call it "Domain Model Action".

Let me define this properly: Domain Model Action is an operation that can be
executed in your Domain Model layer which assumes existence of SOME Persistence
for your model, but not a specific one.

As long as your Domain Model is restricted to generic Domain Model Actions, it
will not violate SRP (Single Responsibility Principle)


Unique Features of Persistence Layer
------------------------------------
More often than not, your application is designed and built with a specific
persistence layer in mind. If you are using SQL database, you want to


_to be continued_







Before we talk "databases", we must outline a few challenges:

 - our business model described above should work with various database vendors
 - we should be able to perform basic Unit tests on our domain logic
 - single vs multiple records
 - ..add more..
