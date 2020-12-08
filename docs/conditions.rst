
.. _DataSet:
.. _conditions:

.. php:namespace:: Atk4\Data

======================
Conditions and DataSet
======================

.. php:class:: Model

When model is associated with the database, you can specify a default table
either explicitly or through a $table property inside a model::

    $m = new Model_User($db, 'user');
    $m->load(1);
    echo $m->get('gender');   // "M"


Following this line, you can load ANY record from the table. It's possible to
narrow down set of "loadable" records by introducing a condition::

    $m = new Model_User($db, 'user');
    $m->addCondition('gender','F');
    $m->load(1);    // exception, user with ID=1 is M

Conditions serve important role and must be used to intelligently restrict
logically accessible data for a model before you attempt the loading.

Basic Usage
===========

.. php:method:: addCondition($field, $operator = null, $value = null)

There are many ways to execute addCondition. The most basic one that will be
supported by all the drivers consists of 2 arguments or if operator is '='::

    $m->addCondition('gender', 'F');         // or
    $m->addCondition('gender', '=', 'F');

Once you add a condition, you can't get rid of it, so if you want
to preserve the state of your model, you need to use clone::

    $m = new Model_User($db, 'user');
    $girls = (clone $m)->addCondition('gender','F');

    $m->load(1);        // success
    $girls->load(1);    // exception

Operations
----------

Most database drivers will support the following additional operations::

    >, <, >=, <=, !=, in, not in, like, not like, regexp, not regexp

The operation must be specified as second argument::

    $m = new Model_User($db, 'user');
    $girls = (clone $m)->addCondition('gender', 'F');
    $not_girls = (clone $m)->addCondition('gender', '!=', 'F');

When you use 'in' or 'not in' you should pass value as array::

    $m = new Model_User($db, 'user');
    $girls_or_boys = (clone $m)->addCondition('gender', 'in', ['F', 'M']);

Multiple Conditions
-------------------

You can set multiple conditions on the same field even if they are contradicting::

    $m = new Model_User($db, 'user');
    $noone = (clone $m)
        ->addCondition('gender', 'F')
        ->addCondition('gender', 'M');

Normally, however, you would use a different fields::

    $m = new Model_User($db, 'user');
    $girl_sue = (clone $m)
        ->addCondition('gender', 'F')
        ->addCondition('name', 'Sue');

You can have as many conditions as you like.

Adding OR Conditions
--------------------

In Agile Data all conditions are additive. This is done for security - no matter
what condition you are adding, it will not allow you to circumvent previously
added condition.

You can, however, add condition that contains multiple clauses joined with OR
operator::

    $m->addCondition(Model\Scope::createOr(
        ['name', 'John'],
        ['surname', 'Smith'],
    ));

This will add condition that will match against records with either
name=John OR surname=Smith.
If you are building multiple conditions against the same field, you can use this
format::

    $m->addCondition('name', ['John', 'Joe']);

For all other cases you can implement them with :php:meth:`Model::expr`::

    $m->addCondition($m->expr("(day([birth_date]) = day([registration_date]) or day([birth_date]) = [])", 10));

This rather unusual condition will show user records who have registered on same
date when they were born OR if they were born on 10th. (This is really silly
condition, please don't judge, if you have a better example, I'd love to hear).

Defining your classes
---------------------

Although I have used in-line addition of the arguments, normally you would want
to set those conditions inside the init() method of your model::


    class Model_Girl extends Model_User
    {
        function init(): void
        {
            parent::init();

            $this->addCondition('gender', 'F');
        }
    }

Note that the field 'gender' should be defined inside Model_User::init().

Vendor-dependent logic
======================

There are many other ways to set conditions, but you must always check if they
are supported by the driver that you are using.

Field Matching
--------------

Supported by: SQL   (planned for Array, Mongo)

Usage::

    $m->addCondition('name', $m->getField('surname'));

Will perform a match between two fields.


Expression Matching
-------------------

Supported by: SQL   (planned for Array)

Usage::

    $m->addCondition($m->expr('[name] > [surname]');

Allow you to define an arbitrary expression to be used with fields. Values
inside [blah] should correspond to field names.


SQL Expression Matching
-----------------------

.. php:method:: expr($expression, $arguments = [])

    Basically is a wrapper to create DSQL Expression, however this will find any
    usage of identifiers inside the template that do not have a corresponding
    value inside $arguments and replace it with the field::

        $m->expr('[age] > 20'); // same as
        $m->expr('[age] > 20', ['age'=>$m->getField('age')); // same as



Supported by: SQL

Usage::

    $m->addCondition($m->expr('[age] between [min_age] and [max_age]'));

Allow you to define an arbitrary expression using SQL language.


Custom Parameters in Expressions
--------------------------------

Supported by: SQL

Usage::

    $m->addCondition(
        $m->expr('[age] between [min_age] and [max_age]'),
        ['min_age'=>10, 'max_age'=>30]
    );

Allow you to pass parameters into expressions. Those can be nested and consist
of objects as well as actions::


    $m->addCondition(
        $m->expr('[age] between [min_age] and [max_age]'),
        [
            'min_age'=>$m->action('min', ['age']),
            'max_age'=>$m->expr('(20 + [])', [20])
        ]
    );

This will result in the following condition:

.. code-block:: sql

    WHERE
        `age` between
            (select min(`age`) from `user`)
            and
            (20 + :a)

where the other 20 is passed through parameter. Refer to
http://dsql.readthedocs.io/en/develop/expressions.html for full documentation
on expressions.


Expression as first argument
----------------------------

Supported by: SQL, (Planned: Array, Mongo)

The $field of addCondition() can be passed as either an expression or any
object implementing Atk4\Dsql\Expressionable interface. Same logic applies
to the $value::

    $m->addCondition($m->getField('name'), '!=', $this->getField('surname'));


Using withId
============

.. php:method:: withId($id)

This method is similar to load($id) but instead of loading the specified record,
it sets condition for ID to match. Technically that saves you one query if you
do not need actual record by are only looking to traverse::

    $u = new Model_User($db);
    $books = $u->withId(20)->ref('Books');

Advanced Usage
==============


Model Scope
-----------

Using the Model::addCondition method is the basic way to limit the model scope of records. Under the hood
Agile Data utilizes a special set of classes (Condition and Scope) to apply the conditions as filters on records retrieved.
These classes can be used directly and independently from Model class.

.. php:method:: scope()

This method provides access to the model scope enabling conditions to be added::

   $contact->scope()->addCondition($condition); // adding condition to a model

.. php:class:: Scope

Scope object has a single defined junction (AND or OR) and can contain multiple nested Condition and/or Scope objects referred to as nested conditions.
This makes creating Model scopes with deep nested conditions possible, 
e.g ((Name like 'ABC%' and Country = 'US') or (Name like 'CDE%' and (Country = 'DE' or Surname = 'XYZ')))

Scope can be created using new Scope() statement from an array or joining Condition objects or condition arguments arrays::

   // $condition1 will be used as nested condition
   $condition1 = new Condition('name', 'like', 'ABC%');
   
   // $condition2 will converted to Condtion object and used as nested condition
   $condition2 = ['country', 'US'];
   
   // $scope1 is created using AND as junction and $condition1 and $condition2 as nested conditions
   $scope1 = Scope::createAnd($condition1, $condition2);
   
   $condition3 = new Condition('country', 'DE');
   $condition4 = ['surname', 'XYZ'];
   
   // $scope2 is created using OR as junction and $condition3 and $condition4 as nested conditions
   $scope2 = Scope::createOr($condition3, $condition4);

   $condition5 = new Condition('name', 'like', 'CDE%');
   
   // $scope3 is created using AND as junction and $condition5 and $scope2 as nested conditions
   $scope3 = Scope::createAnd($condition5, $scope2);

   // $scope is created using OR as junction and $scope1 and $scope3 as nested conditions
   $scope = Scope::createOr($scope1, $scope3);
   
   
Scope is an independent object not related to any model. Applying scope to model is using the Model::scope()->add($condition) method::

   $contact->scope()->add($condition); // adding condition to a model
   $contact->scope()->add($conditionXYZ); // adding more conditions
   
.. php:method:: __construct($nestedConditions = [], $junction = Scope::AND);

Creates a Scope object from an array::

   // below will create 2 conditions and nest them in a compound conditions with AND junction
   $scope1 = new Scope([
      ['name', 'like', 'ABC%'],
      ['country', 'US']
   ]);
   
.. php:method:: negate();

Negate method has behind the full map of conditions so any condition object can be negated, e.g negating '>=' results in '<', etc.
For compound conditionss this method is using De Morgan's laws, e.g::

   // using $scope1 defined above
   // results in "(Name not like 'ABC%') or (Country does not equal 'US')"
   $scope1->negate();

.. php:method:: createAnd(...$conditions);

Merge $conditions using AND as junction. Returns the resulting Scope object.

.. php:method:: createOr(...$conditions);

Merge $conditions using OR as junction. Returns the resulting Scope object.

.. php:method:: simplify();

Peels off single nested conditions. Useful for converting (((field = value))) to field = value.

.. php:method:: clear();

Clears the condition from nested conditions.

.. php:method:: isOr();

Checks if scope components are joined by OR

.. php:method:: isAnd();

Checks if scope components are joined by AND

.. php:namespace:: Atk4\Data\Model\Scope

.. php:class:: Condition

Condition represents a simple condition in a form [field, operation, value], similar to the functionality of the 
Model::addCondition method

.. php:method:: __construct($key, $operator = null, $value = null);

Creates condition object based on provided arguments. It acts similar to Model::addCondition

$key can be Model field name, Field object, Expression object, FALSE (interpreted as Expression('false')), TRUE (interpreted as empty condition) or an array in the form of [$key, $operator, $value]
$operator can be one of the supported operators >, <, >=, <=, !=, in, not in, like, not like, regexp, not regexp
$value can be Field object, Expression object, array (interpreted as 'any of the values') or other scalar value

If $value is omitted as argument then $operator is considered as $value and '=' is used as operator

.. php:method:: negate();

Negates the condition, e.g::

	// results in 'name is not John'
	$condition = (new Condition('name', 'John'))->negate(); 

.. php:method:: toWords(Model $model = null);

Converts the condition object to human readable words. Condition must be assigned to a model or model argument provided::

	// results in 'Contact where Name is John'
	(new Condition('name', 'John'))->toWords($contactModel); 

Conditions on Referenced Models
-------------------------------

Agile Data allows for adding conditions on related models for retrieval of type 'model has references where'.

Setting conditions on references can be done utilizing the Model::refLink method but there is a shorthand format 
directly integrated with addCondition method using "/" to chain the reference names::

	$contact->addCondition('company/country', 'US');
	
This will limit the $contact model to those whose company is in US.
'company' is the name of the reference in $contact model and 'country' is a field in the referenced model.

If a condition must be set directly on the existence or number of referenced records the special symbol "#" can be
utilized to indicate the condition is on the number of records::

	$contact->addCondition('company/tickets/#', '>', 3);
	
This will limit the $contact model to those whose company have more than 3 tickets.
'company' and 'tickets' are the name of the chained references ('company' is a reference in the $contact model and
'tickets' is a reference in Company model)


