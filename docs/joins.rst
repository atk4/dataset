
.. _Joins:

.. php:namespace:: Atk4\Data\Model

=================================
Model from multiple joined tables
=================================

.. php:class:: Join

Sometimes model logically contains information that is stored in various places
in the database. Your database may want to split up logical information into
tables for various reasons, such as to avoid repetition or to better optimize
indexes.

Join Basics
===========

Agile Data allows you to map multiple table fields into a single business model
by using joins::

    $user->addField('username');
    $j_contact = $user->join('contact');
    $j_contact->addField('address');
    $j_contact->addField('county');
    $j_contact->hasOne('Country');

This code will load data from two tables simultaneously and if you do change any
of those fields they will be update in their respective tables. With SQL the
load query would look like this:

.. code-block:: sql

    select
        u.username, c.address, c.county, c.country_id
        (select name from country where country.id=c.country_id) country
    from user u
    join contact c on c.id=u.contact_id
    where u.id = $id

If driver is unable to query both tables simultaneously, then it will load one
record first, then load other record and will collect fields together::

    $user_data = $user->find($id);
    $contact_data = $contact->find($user_data->get('contact_id'));

When saving the record, Joins will automatically record data correctly:

.. code-block:: sql

    insert into contact (address, county, country_id) values ($, $, $);
    @join_c = last_insert_id();
    insert into user (username, contact_id) values ($, @join_c)

Strong and Weak joins
---------------------

When you are joining tables, then by default a strong join is used. That means
that both records are mandatory and when adding records, they will both be added
and linked.

Weak join is used if you do not really want to modify the other table.
For example it can be used to pull country information based on user.country_id
but you wouldn't want that adding a new user would create a new country::

    $user->addField('username');
    $user->addField('country_id');
    $j_country = $user->weakJoin('country', ['prefix'=>'country_']);
    $j_country->addField('code');
    $j_country->addField('name');
    $j_country->addField('default_currency', ['prefix'=>false]);

After this you will have the following fields in your model:

- username
- country_id
- country_code [read_only]
- country_name [read_only]
- default_currency [read_only]

.. php:method:: importModel

You can achieve a similar functionality with hasOne reference, but with weak
join you can pull multiple fields into your model.
Finally you can even join using existing models::

    $user->addField('username');
    $user->addField('country_id');
    $user->weakJoin('country')->importModel('Country');

This will automatically import fields, expressions, references and conditions
from 'Country' model into $user model and will also re-map field names in
process.

.. php:method:: weakJoinModel

To save you some time with weakJoin() and importModel(), if you wish to simply
import another model fields, you can actually use this syntax::

    $user->weakJoinModel('Country', ['code', 'name', 'default_currency']);

When joining model like that, all the fields will be prefixed automatically
using Country::$table property.

Note that weak joins are not yet fully implemented !!!


Join relationship definitions
-----------------------------

When defining joins, you need to outline two fields that must match. In our
earlier examples, we the master table was "user" that contained reference to
"contact". The condition would look like this ``user.contact_id=contact.id``.
In some cases, however, a relation should be reversed::

    $j_contact = $user->join('contact.user_id');

This will result in the following join condition: ``user.id=contact.user_id``.
The first argument to join defines both the table that we need to join and
can optionally define the field in the foreign table. If field is set, we will
assume that it's a reverse join.

Reverse joins are saved in the opposite order - primary table will be saved
first and when id of a primary table is known, foreign table record is stored
and ID is supplied. You can pass option 'master_field' to the join() which will
specify which field to be used for matching. By default the field is calculated
like this: foreign_table.'_id'. Here is usage example::

    $user->addField('username');
    $j_cc = $user->join('credit_card', [
        'prefix'=>'cc_',
        'master_field'=>'default_credit_card_id'
    ]);
    $j_cc->addField('number');  // creates cc_number
    $j_cc->addField('name');    // creates cc_name

Master field can also be specified as an object of a Field class.

There are more options that you can pass inside join(), but those are
vendor-specific and you'll have to look into documentation for sql\Join and
mongo\Join respectfully.

Method Proxying
---------------

Once your join is defined, you can call several methods on the join objects, that
will create fields, other joins or expressions but those would be associated
with a foreign table.


.. php:method:: addField

    same as :php:meth:`Model::addField` but associates field with foreign table.

.. php:method:: join

    same as :php:meth:`Model::join` but links new table with this foreign table.

.. php:method:: weakJoin

    same as :php:meth:`Model::weakJoin` but links new table with this foreign
    table.

    Not yet implemented !

.. php:method:: hasOne

    same as :php:meth:`Model::hasOne` but reference ID field will be associated
    with foreign table.

.. php:method:: hasMany

    same as :php:meth:`Model::hasMany` but condition for related model will be
    based on foreign table field and :php:attr:`Reference::their_field` will be
    set to $foreign_table.'_id'.

.. php:method:: containsOne

    same as :php:meth:`Model::hasOne` but the data will be stored in
    a field inside foreign table.

    Not yet implemented !

.. php:method:: containsMany

    same as :php:meth:`Model::hasMany` but the data will be stored in
    a field inside foreign table.

    Not yet implemented !


Create and Delete behavior
--------------------------

Updating joined records are simple, but when it comes to creation and deletion,
there are some conditions. First we look at dependency. If master table contains
id of a foreign table, then foreign table record must be created first, so that
we can store its ID in a master table. If the join is reversed, the master
record is created first and then foreign record is inserted along with the value
of master id.

When it comes to deleting record, there are three possible conditions:

1. [delete_behaivour = cascade, reverse = false]
   If we are using strong join and master table contains ID of foreign table,
   then foreign master table record is deleted first. Foreign table record is
   deleted after. This is done to avoid error with foreign constraints.
2. [delete_behaviour = cascade, reverse = true]
   If we are using strong join and foreign table contains ID of master table,
   then foreign table record is deleted first followed by the master table record.

3. [delete_behaviour = ignore, reverse = false]
   If we are using weak join and the master table contains ID of foreign table,
   then master table is deleted first. Foreign table record is not deleted.

4. [delete_behaviour = setnull, reverse = true]
   If we are using weak join and foreign table contains ID of master table,
   then foreign table is updated to set ID of master table to NULL first.
   Then the master table record is deleted.

Based on the way how you define join an appropriate strategy is selected and
Join will automatically decide on $delete_behaviour and $reverse values.
There are situations, however when it's impossible to determine in which order
the operations have to be performed. A good example is when you define both
master/foreign fields.

In this case system will default to "reverse=false" and will delete master
record first, however you can specify a different value for "reverse".

Sometimes it's also sensible to set delete_behaviour = ignore and perform your
own delete operation yourself.



Implementation Detail
---------------------

Joins are implemented like this:

- all the fields that has 'joinName' property set will not be saved into default
  table by default driver
- join will add either `beforeInsert` or `afterInsert` hook inside your model.
  When save is executed, it will execute additional query to update foreign table.
- while $model->getId() stores the ID of the main table active record, $join->id
  stores ID of the foreign record and will be used when updating.
- option 'delete_behaviour' is 'cascade' for strong joins and 'ignore' for weak
  joins, but you can set some other value. If you use "setnull" value and you
  are using reverse join, then foreign table record will not be updated, but
  value of the foreign field will be set to null.


.. php:class:: Join\Sql

SQL-specific joins
==================

When your model is associated with SQL-capable driver, then instead of using
`Join` class, the `Join\\Sql` is used instead. This class is designed to improve
loading technique, because SQL vendors can query multiple tables simultaneously.

Vendors that cannot do JOINs will have to implement compatibility by pulling
data from collections in a correct order.

Implementation Details
----------------------

- although some SQL vendors allow update .. join .. syntax, this will not be
  used. That is done to ensure better compatibility.
- when field has the 'joinName' option set, trying to convert this field into
  expression will prefix the field properly with the foreign table alias.
- join will be added in all queries
- strong join can potentially reduce your data-set as it exclude table rows
  that cannot be matched with foreign table row.

Specifying complex ON logic
---------------------------

When you're dealing with SQL drivers, you can specify `\\Atk4\\Dsql\\Expression` for your
"on" clause::

    $stats = $user->join('stats', [
        'on'=>$user->expr('year({}) = _st.year'),
        'foreign_alias'=>'_st'
    ]);

You can also specify ``'on'=>false`` then the ON clause will not be used at all
and you'll have to add additional where() condition yourself.

``foreign_alias`` can be specified and will be used as table alias and prefix
for all fields. It will default to ``"_".$foreign_table->get(0)``. Agile Data will
also resolve situations when multiple tables have same first character so the
prefixes will be named '_c' ,'_c_2', '_c_3' etc.


Additional arguments accepted by SQL joins are:

- 'kind' - will be "inner" for strong join and "left" for weak join, but you can
  specify other kind of join, for example, "right"'.

