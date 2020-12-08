
.. _Expressions:

===========
Expressions
===========

.. php:class:: Model

You already know that you can define fields inside your Model with addField.
While a regular field maps to physical field inside your database, sometimes you
want to do something different - execute expression or function inside SQL and
use result as an output.

Expressions solve this problem by adding a read-only field to your model that
corresponds to an expression:

.. php:method:: addExpression($link, $model);

Example will calculate "total_gross" by adding up values for "net" and "vat"::

    $m = new Model_Invoice($db);
    $m->addFields(['total_net', 'total_vat']);

    $m->addExpression('total_gross', '[total_net]+[total_vat]');
    $m->load(1);

    echo $m->get('total_gross');

The query using during load() will look like this:

.. code-block:: sql

    select
        `id`,`total_net`,`total_vat`,
        (`total_net`+`total_vat`) `total_gross`
    from `invoice`',

Defining Expression
-------------------

The simplest format to define expression is by simply passing a string. The
argument is executed through Model::expr() which automatically substitutes
values for the other fields including other expressions.

There are other ways how you can specify expression::

    $m->addExpression('total_gross',
        $m->expr('[total_net]+[total_vat] + [fee]', ['fee'=>$fee])
    );

This format allow you to supply additional parameters inside expression.
You should always use parameters instead of appending values inside your
expression string (for safety)

You can also use expressions to pass a select action for a specific field::

No-table Model Expression
-------------------------

Agile Data allows you to define a model without table. While this may have
no purpose initially, it does come in handy in some cases, when you need to
unite a few statistical queries. Let's start by looking a at a very basic
example::

    $m = new Model($db, false);
    $m->addExpression('now', 'now()');
    $m->loadAny();
    echo $m->get('now');

In this example the query will look like this:

.. code-block:: sql

    select (1) `id`, (now()) `now` limit 1

so that ``$m->getId()`` will always be 1 which will make it a model that you can
actually use consistently throughout the system. The real benefit from this
can be gained when you need to pull various statistical values from your
database at once::

    $m = new Model($db, false);
    $m->addExpression('total_orders', (new Model_Order($db))->action('count'));
    $m->addExpression('total_payments', (new Model_Payment($db))->action('count'));
    $m->addExpression('total_received', (new Model_Payment($db))->action('fx0', ['sum', 'amount']));

    $data = $m->loadAny()->get();

Of course you can also use a DSQL for this::

    $q = $db->dsql();
    $q->field(new Model_Order($db)->action('count'), 'total_orders');
    $q->field(new Model_Payment($db)->action('count'), 'total_orders');
    $q->field(new Model_Payment($db)->action('fx0', ['sum', 'amount']), 'total_received');
    $data = $q->getRow();

You can decide for yourself based on circumstances.

Expression Callback
-------------------

You can use a callback method when defining expression::

    $m->addExpression('total_gross', function($m, $q) {
        return '[total_net]+[total_vat]';
    });

Model Reloading after Save
--------------------------

When you add SQL Expressions into your model, that means that some of the fields
might be out of sync and you might need your SQL to recalculate those expressions.

To simplify your life, Agile Data implements smart model reloading. Consider
the following model::

    class Model_Math extends \Atk4\Data\Model
    {
        public $table = 'math';
        function init(): void
        {
            parent::init();

            $this->addFields(['a', 'b']);

            $this->addExpression('sum', '[a]+[b]');
        }
    }

    $m = new Model_Math($db);
    $m->set('a', 4);
    $m->set('b', 6);

    $m->save();

    echo $m->get('sum');

When $m->save() is executed, Agile Data will perform reloading of the model.
This is to ensure that expression 'sum' would be re-calculated for the values of
4 and 6 so the final line will output a desired result - 10;

Reload after save will only be executed if you have defined any expressions
inside your model, however you can affect this behavior::

    $m = new Model_Math($db, ['reload_after_save' => false]);
    $m->set('a', 4);
    $m->set('b', 6);

    $m->save();

    echo $m->get('sum');   // outputs null

    $m->reload();
    echo $m->get('sum');   // outputs 10

Now it requires an explicit reload for your model to fetch the result. There
is another scenario when your database defines default fields:

.. code-block:: sql

    alter table math change b b int default 10;

Then try the following code::

    class Model_Math extends \Atk4\Data\Model
    {
        public $table = 'math';
        function init(): void
        {
            parent::init();

            $this->addFields(['a', 'b']);
        }
    }

    $m = new Model_Math($db);
    $m->set('a', 4);

    $m->save();

    echo $m->get('a')+$m->get('b');

This will output 4, because model didn't reload itself due to lack of any
expressions. This time you can explicitly enable reload after save::

    $m = new Model_Math($db, ['reload_after_save' => true]);
    $m->set('a', 4);

    $m->save();

    echo $m->get('a')+$m->get('b'); // outputs 14

.. note:: If your model is using reload_after_save, but you wish to insert
    data without additional query - use :php:meth:`Model::insert()` or
    :php:meth:`Model::import()`.
