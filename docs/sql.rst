
.. _SQL:

==============
SQL Extensions
==============

Databases that support SQL language can use :php:class:`Persistence\Sql`.
This driver will format queries to the database using SQL language.

In addition to normal operations you can extend and customize various queries.

Default Model Classes
=====================

When using Persistence\Sql model building will use different classes for fields,
expressions, joins etc:

 - addField - :php:class:`FieldSql` (field can be used as part of DSQL Expression)
 - hasOne - :php:class:`Reference\HasOneSql` (allow importing fields)
 - addExpression - :php:class:`FieldSqlExpression` (define expression through DSQL)
 - join - :php:class:`Join\Sql` (join tables query-time)


SQL Field
---------

.. php:class:: FieldSql

.. php:attr:: actual

    :php:class:`Persistence\Sql` supports field name mapping. Your field could
    have different column name in your schema::

        $this->addField('name', ['actual'=>'first_name']);

    This will apply to load / save operations as well as query mapping.

.. php:method:: getDsqlExpression

    SQL Fields can be used inside other SQL expressions::

        $q = new \Atk4\Dsql\Expression('[age] + [birth_year]', [
                'age'        => $m->getField('age'),
                'birth_year' => $m->getField('birth_year'),
            ]);

SQL Reference
-------------

.. php:class:: Reference\HasOneSql

    Extends :php:class:`Reference\HasOne`

.. php:method:: addField

    Allows importing field from a referenced model::

        $model->hasOne('country_id', new Country())
            ->addField('country_name', 'name');

    Second argument could be array containing additional settings for the field::

        $model->hasOne('account_id', new Account())
            ->addField('account_balance', ['balance', 'type'=>'money']);

    Returns new field object.

.. php:method:: addFields

    Allows importing multiple fields::

        $model->hasOne('country_id', new Country())
            ->addFields(['country_name', 'country_code']);

    You can specify defaults to be applied on all fields::

        $model->hasOne('account_id', new Account())
            ->addFields([
                'opening_balance',
                'balance'
            ], ['type'=>'money']);

    You can also specify aliases::

        $model->hasOne('account_id', new Account())
            ->addFields([
                'opening_balance',
                'account_balance'=>'balance'
            ], ['type'=>'money']);

    If you need to pass more details to individual field, you can also use sub-array::

        $model->hasOne('account_id', new Account())
            ->addFields([
            [
                ['opening_balance', 'caption'=>'The Opening Balance'],
                'account_balance'=>'balance'
            ], ['type'=>'money']);

    Returns $this.

.. php:method:: ref

    While similar to :php:meth:`Reference\HasOne::ref` this implementation
    implements deep traversal::

        $country_model = $customer_model->addCondition('is_vip', true)
            ->ref('country_id');           // $model was not loaded!

.. php:method:: refLink

    Creates a model for related entity with applied condition referencing field
    of a current model through SQL expression rather then value. This is usable
    if you are creating sub-queries.

.. php:method:: addTitle

    Similar to addField, but will import "title" field and will come up with
    good name for it::

        $model->hasOne('country_id', new Country())
            ->addTitle();

        // creates 'country' field as sub-query for country.name

    You may pass defaults::

        $model->hasOne('country_id', new Country())
            ->addTitle(['caption'=>'Country Name']);

    Returns new field object.

.. php:method:: withTitle

    Similar to addTitle, but returns $this.

Expressions
-----------

.. php:class:: FieldSqlExpression

    Extends :php:class:`FieldSql`

Expression will map into the SQL code, but will perform as read-only field otherwise.

.. php:attr:: expr

    Stores expression that you define through DSQL expression::

        $model->addExpression('age', 'year(now())-[birth_year]');
        // tag [birth_year] will be automatically replaced by respective model field

.. php:method:: getDsqlExpression

    SQL Expressions can be used inside other SQL expressions::

        $model->addExpression('can_buy_alcohol', ['if([age] > 25, 1, 0)', 'type'=>'boolean']);

Adding expressions to model will make it automatically reload itself after save
as default behavior, see :php:attr:`Model::reload_after_save`.

Transactions
============

.. php:class:: Persistence\Sql

.. php:method:: atomic

This method allows you to execute code within a 'START TRANSACTION / COMMIT' block::

    class Invoice {

        function applyPayment(Payment $p) {

            $this->persistence->atomic(function() use ($p) {

                $this->set('paid', true);
                $this->save();

                $p->set('applied', true);
                $p->save();

            });

        }
    }

Callback format of this method allows a more intuitive syntax and nested execution
of various blocks. If any exception is raised within the block, then transaction
will be automatically rolled back. The return of atomic() is same as return of
user-defined callback.

Custom Expressions
==================

.. php:method:: expr

    This method is also injected into the model, that is associated with
    Persistence\Sql so the most convenient way to use this method is by calling
    `$model->expr('foo')`.

This method is quite similar to \Atk4\Dsql\Query::expr() method explained here:
http://dsql.readthedocs.io/en/stable/expressions.html

There is, however, one difference. Expression class requires all named arguments
to be specified. Use of Model::expr() allows you to specify field names and those
field expressions will be automatically substituted. Here is long / short format::

    $q = new \Atk4\Dsql\Expression('[age] + [birth_year]', [
            'age' => $m->getField('age'),
            'birth_year' => $m->getField('birth_year')
        ]);

    // identical to

    $q = $m->expr('[age] + [birth_year']);

This method is automatically used by :php:class:`FieldSqlExpression`.


Actions
=======

The most basic action you can use with SQL persistence is 'select'::

    $action = $model->action('select');

Action is implemented by DSQL library, that is further documented at
http://dsql.readthedocs.io (See section Queries).


Action: select
--------------

This action returns a basic select query. You may pass one argument - array
containing list of fields::

    $action = $model->action('select', ['name', 'surname']);

Passing false will not include any fields into select (so that you can include
them yourself)::

    $action = $model->action('select', [false]);
    $action->field('count(*)', 'c);


Action: insert
--------------

Will prepare query for performing insert of a new record.

Action: update, delete
----------------------

Will prepare query for performing update or delete of records.
Applies conditions set.

Action: count
-------------

Returns query for `count(*)`::

    $action = $model->action('count');
    $cnt = $action->getOne();

You can also specify alias::

    $action = $model->action('count', ['alias'=>'cc']);
    $data = $action->getRow();
    $cnt = $data->get('cc');

Action: field
-------------

Get query for a specific field::

    $action = $model->action('field', ['age']);
    $age = $action->limit(1)->getOne();

You can also specify alias::

    $action = $model->action('field', ['age', 'alias'=>'the_age']]);
    $age = $action->limit(1)->getRow()['the_age'];

Action: fx
----------

Executes single-argument SQL function on field::

    $action = $model->action('fx', ['avg', 'age']);
    $avg_age = $action->getOne();

This method also supports alias. Use of alias is handy if you are using those
actions as part of other query (e.g. UNION)

Stored Procedures
=================

SQL servers allow to create and use stored procedures and there are several ways
to invoke them:

1. `CALL` procedure. No data / output.
2. Specify `OUT` parameters.
3. Stored `FUNCTION`, e.g. `select myfunc(123)`
4. Stored procedures that return data.

Agile Data has various ways to deal with above scenarios:

    1. Custom expression through DSQL
    2. Model Method
    3. Model Field
    4. Model Source

Here I'll try to look into each of those approaches but closely pay attention
to the following:

    - Abstraction and concern separation.
    - Security and protecting against injection.
    - Performance and scalability.
    - When to refactor away stored procedures.

Compatibility Warning
---------------------

Agile Data is designed to be cross-database agnostic. That means you should be
able to swap your SQL to NoSQL or RestAPI at any moment. My relying on stored
procedures you will loose portability of your application.

We do have our legacy applications to maintain, so Stored Procedures and SQL
extensions are here to stay. By making your Model rely on those extensions you
will loose ability to use the same model with non-sql persistences.

Sometimes you can fence the code like this::

    if ($this->persistence instanceof \Atk4\Data\Persistence\Sql) {
        .. sql code ..
    }

Or define your pure model, then extend it to add SQL capabilities. Note that
using single model with cross-persistences should still be possible, so you
should be able to retrieve model data from stored procedure then cache it.

as a Model method
-----------------

You should be familiar with http://dsql.readthedocs.io/en/develop/expressions.html.

In short this should allow you to build and execute any SQL statement::

    $this->expr("call get_nominal_sheet([],[],'2014-10-01','2015-09-30',0)", [
        $this->getApp()->system->getId(),
        $this->getApp()->system['contractor_id']
    ])->execute();

Depending on the statement you can also use your statement to retrieve data::

    $data = $this->expr("call get_client_report_data([client_id])", [
        'client_id'=>$client_id
    ])->get();

This can be handy if you wish to create a method for your Model to abstract away
the data::

    class Client extends \Atk4\Data\Model {
        function init(): void {
            ...
        }

        function getReportData($arg) {
            if (!$this->loaded()) {
                throw new Exception('Client must be loaded');
            }

            return $this->expr("call get_client_report_data([client_id, arg])", [
                'arg'       => $arg,
                'client_id' => $client_id,
            ])->get();
        }
    }

Here is another example using PHP generator::

    class Client extends \Atk4\Data\Model {
        function init(): void {
            ...
        }

        function fetchReportData($arg) {
            if (!$this->loaded()) {
                throw new Exception('Client must be loaded');
            }

            foreach($this->expr("call get_client_report_data([client_id, arg])", [
                'arg'       => $arg,
                'client_id' => $client_id,
            ]) as $row) {
                yield $row;
            }
        }
    }

as a Model Field
----------------

.. important:: Not all SQL vendors may support this approach.

:php:meth:`Model::addExpression` is a SQL extension that allow you to define
any expression for your field query. You can use SQL stored function for data
fetching like this::

    class Category extends \Atk4\Data\Model {
        public $table = 'category';
        function init(): void {
            parent::init();

            $this->hasOne('parent_id', new self());
            $this->addField('name');

            $this->addExpression('path', 'get_path([id])');
        }
    }

This should translate into SQL query::

    select parent_id, name, get_path(id) from category;

where once again, stored function is hidden.


as an Action
------------

.. important:: Not all SQL vendors may support this approach.

Method :php:meth:`Persistence\\Sql::action` and :php:meth:`Model::action`
generates queries for most of model operations.  By re-defining this method,
you can significantly affect the query building of an SQL model::

    class CompanyProfit extends \Atk4\Data\Model {

        public $company_id = null; // inject company_id, which will act as a condition/argument
        public $read_only  = true; // instructs rest of the app, that this model is read-only

        function init(): void {
            parent::init();

            $this->addField('date_period');
            $this->addField('profit');
        }

        public function action($mode, $args = [])

            if ($mode == 'select') {

                // must return DSQL object here
                return $this->expr("call get_company_profit([company_id])", [
                    'company_id' => $this->company_id,
                ]);
            }

            if ($mode == 'count') {

                // optionally - expression for counting data rows, for pagination support
                return $this->expr("select count(*) from (call get_company_profit([company_id]))", [
                    'company_id' => $this->company_id,
                ]);
            }

            throw (new \Atk4\Core\Exception('You may only perform "select" or "count" action on this model'))
                ->addMoreInfo('action', $mode);
        }
    }

as a Temporary Table
--------------------

A most convenient (although inefficient) way for stored procedures is to place
output data inside a temporary table. You can perform an actual call to stored
procedure inside Model::init() then set $table property to a temporary table::

    class NominalReport extends \Atk4\Data\Model {
        public $table = 'temp_nominal_sheet';
        public $read_only = true; // instructs rest of the app, that this model is read-only

        function init(): void {
            parent::init();

            $q = $this->expr("call get_nominal_sheet([],[],'2014-10-01','2015-09-30',0)", [
                $this->getApp()->system->getId(),
                $this->getApp()->system['contractor_id']
            ])->execute();

            $this->addField('date', ['type'=>'date']);
            $this->addField('items', ['type'=>'integer']);
            ...
        }
    }


as an Model Source
------------------

.. important:: Not all SQL vendors may support this approach.

Technically you can also specify expression as a $table property of your model::

    class ClientReport extends \Atk4\Data\Model {

        public $table = null; // will be set in init()
        public $read_only = true; // instructs rest of the app, that this model is read-only

        function init(): void {
            parent::init();

            $this->init = $this->expr("call get_report_data()");

            $this->addField('date', ['type'=>'date']);
            $this->addField('items', ['type'=>'integer']);
            ...
        }
    }

Technically this will give you `select date,items from (call get_report_data())`.
