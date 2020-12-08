========
Overview
========

Agile Data is a unique SQL/NoSQL access library that promotes correct Business
Logic design in your PHP application and implements database access in a
flexible and scalable way.

.. image:: images/presentation.png
    :target: https://www.youtube.com/watch?v=XUXZI7123B8

Simple to learn
===============

We have designed Agile Data to be very friendly for those who started programming
recently and teach them correct patterns through clever architectural design.

Agile Data carries the spirit of PHP language in general and gives developer
ability to make choices. The framework can be beneficial even in small
applications, but the true power of Agile Data is realized when it's paired with
Agile UI or Agile API projects.
(https://github.com/atk4/ui, https://github.com/atk4/api).

Not a traditional ORM
=====================

Agile Data implementation has several significant differences to a traditional
ORM (Hibernate / Doctrine style). I will discuss those in more detail further in
documentation, however it's important to note the reason of not following ORM
pattern:

 - More suitable for mapping remote databases
 - Give developer control over generated queries
 - Better support for Persistence-specific features (e.g. SQL expressions)
 - True many-to-many deep traversal and avoiding (explicit eager pre-loading)
 - Better aggregation abstraction

To find out more, how Agile Data compares to other PHP data mappers and ORM frameworks, see
https://medium.com/@romaninsh/objectively-comparing-orm-dal-libraries-e4f095de80b5


Concern Separation
==================

Design of Agile Data follows principle of "concern separation", but all of the
basic functionality is divided into 3 major areas:

 - Fields (or Columns)
 - DataSets (or Rows)
 - Databases (or Persistences)

Each of the above corresponds to a PHP class, which may use composition principle
to hide implementation details.

By design, you will be able to mix and match any any Field with any Database to
work with your DataSets.

If you have worked with other ORMs, read the following sections to avoid confusion:

Class: Field
------------

 - Represent logical data column (e.g. "date_of_birth")
 - Stores column meta-information (e.g. "type" => "date", "caption"=>"Birth Date")
 - Handles value normalization
 - Documentation: :php:class:`Field`

.. note:: Meta-information may be a persistence detail, (:php:attr:`Field::actual`)
    or presentation detail (:php:attr:`Field::ui`). Field class does not interpret
    the value, it only stores it.

Class: Model
------------

 - Represent logical Data Set (e.g. Active Users)
 - Stores data location and criteria
 - Stores list of Fields
 - Stores individual row
 - Handle operations over single or all records from Data Set
 - Documentation: :php:class:`Model`

.. note:: Model object is defined in such a way to contain enough information to
    fully provide all information for generic UI, or generic API, and generic
    persistence implementations.

.. note:: Unlike ORMs Model instances are never created during iterating. Also,
    in most cases, you never instantiate multiple instances of a model class.

Class: Persistence
------------------

 - Represent external data storage (e.g. MySQL database)
 - Stores connection information
 - Translate single or multi-record operations into vendor-specific language
 - Type-casts standard data types into vendor-specific format
 - Documentation: :php:class:`Persistence`



Code Layers
===========

How is code::

    select name from user where id = 1

is different to the code?::

    $user->load(1)->get('name');

While both achieve similar things, the SQL-like code is what we call
"persistence-specific" code. The second example is "domain model" code. The job
of Agile Data is to map "domain model" code into "persistence-specific" code.

The design and features of Agile Data allow you to perform wider range of
operations, be more expressive and efficient while remaining in "domain model".

In normal application, all the database operations can be expressed in domain
model without any degradation in performance due to large data volume or higher
database latency.

It's typical for a web application that uses Agile Data in "domain model" to
execute no more than 3-4 requests per page even for highly complex data pages
(such as dashboards) and without use of stored procedures.

Next I'll show you how code from different "code layers" may look like:

Domain-Model Code
-----------------

Code is unaware of physical location of your data or which persistence are you
using::

    $user = new User($db);

    $user->load(20);            // load specific user record into PHP
    echo $user->get('name').': ';    // access field values

    $gross = $user->ref('Invoice')
        ->addCondition('status', 'due')
        ->ref('Lines')
        ->action('sum', 'gross')
        ->getOne();
                                // get sum of all gross fields for due invoices

Another important aspect of Domain-model code is that fields such as `gross` or
`name` can be either a physical values in the database or can be mapped to
expressions (such as `vat`+`net`).

A typical method of your model class will be written in "domain-model" code.

.. note:: the actual execution and number of queries may vary based on
    capabilities of persistence. The above example executes a total of 2 queries
    if used with SQL database.

Persistence-specific code
-------------------------

This is a type of code which may change if you decide to switch from one
persistence to another. For example, this is how you would define `gross` field
for SQL::

    $model->addExpression('gross', '[net]+[vat]');

If your persistence does not support expressions (e.g. you are using Redis or
MongoDB), you would need to define the field differently::

    $model->addField('gross');
    $model->onHook(Model::HOOK_BEFORE_SAVE, function($m) {
        $m->set('gross', $m->get('net') + $m->get('vat'));
    });

When you use persistence-specific code, you must be aware that it will not map
into persistences that does not support features you have used.

In most cases that is OK as if you prefer to stay with same database type, for
instance, the above expression will still be usable with any SQL vendor, but if
you want it to work with NoSQL, then your solution might be::

    if ($model->hasMethod('addExpression')) {

        // method is injected by Persistence
        $model->addExpression('gross', '[net]+[vat]');

    } else {

        // persistence does not support expressions
        $model->addField('gross');
        $model->onHook(Model::HOOK_BEFORE_SAVE, function($m) {
            $m->set('gross', $m->get('net') + $m->get('vat'));
        });

    }

Generic Persistence-code
------------------------

A final type of code is also persistence-specific, but it is agnostic to your
data-model. The example would be implementation of aggregation with "GROUP BY"
feature in SQL.

https://github.com/atk4/report/blob/develop/src/GroupModel.php

This code is specific to SQL databases, but can be used with any Model, so in
order to use grouping with Agile Data, your code would be::

    $m = new \Atk4\Report\GroupModel(new Sale($db));
    $m->groupBy(['contractor_to', 'type'], [      // groups by 2 columns
        'c'                     => 'count(*)',    // defines aggregate formulas for fields
        'qty'                   => 'sum([])',     // [] refers back to qty
        'total'                 => 'sum([amount])', // can specify any field here
    ]);



Persistence Scaling
===================

Although in most cases you would be executing operation against SQL persistence,
Agile Data makes it very easy to use models with a simpler persistences.

For example, consider you want to output a "table" to the user using HTML by
using Agile UI::

    $htmltable = new \Atk4\Ui\Table();
    $htmltable->invokeInit();

    $htmltable->setModel(new User($db));

    echo $htmltable->render();

Class `\\Atk4\\Ui\\Table` here is designed to work with persistences and models -
it will populate columns of correct type, fetch data, calculate totals if needed.
But what if you have your data inside an array?
You can use :php:class:`Persistence\Static_` for that::

    $htmltable = new \Atk4\Ui\Table();
    $htmltable->invokeInit();

    $htmltable->setModel(new User(new Persistence\Static_([
        ['name'=>'John', 'is_admin'=>false, 'salary'=>34400.00],
        ['name'=>'Peter', 'is_admin'=>false, 'salary'=>42720.00],
    ])));

    echo $htmltable->render();

Even if you don't have a model, you can use Static persistence with Generic
model class to display VAT breakdown table::

    $htmltable = new \Atk4\Ui\Table();
    $htmltable->invokeInit();

    $htmltable->setModel(new Model(new Persistence\Static_([
        ['VAT_rate'=>'12.0%', 'VAT'=>'36.00', 'Net'=>'300.00'],
        ['VAT_rate'=>'10.0%', 'VAT'=>'52.00', 'Net'=>'520.00'],
    ])));

    echo $htmltable->render();

It can be made even simpler::

    $htmltable = new \Atk4\Ui\Table();
    $htmltable->invokeInit();

    $htmltable->setModel(new Model(new Persistence\Static_([
        'John',
        'Peter'
    ])));

    echo $htmltable->render();

Agile UI even offers a wrapper for static persistence::

    $htmltable = new \Atk4\Ui\Table();
    $htmltable->invokeInit();

    $htmltable->setSource([ 'John', 'Peter' ]);

    echo $htmltable->render();
