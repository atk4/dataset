
================
Fetching results
================

.. php:class:: Model

Model linked to a persistence is your "window" into DataSet and you get several
ways which allow you to fetch the data.


Iterate through model data
==========================

.. php:method:: getIterator()

Create your persistence object first then iterate it::

    $db = \Atk4\Data\Persistence::connect($dsn);
    $m = new Model_Client($db);

    foreach($m as $id => $item) {
        echo $id.": ".$item->get('name')."\n";
    }

You must be aware that $item will actually be same as $m and will point to the model.
The model, however, will have the data loaded for you, so you can call methods for
each iteration like this::

    foreach($m as $item) {
        $item->sendReminder();
    }

.. warning:: Currently ATK Data does not create new copy of your model object for
    every row. Instead the same object is re-used, simply $item->data is modified
    by the iterator. For new users this may be surprising that $item is the same
    object through the iterator, but for now it's the most CPU-efficient way.

Additionally model will execute necessary after-load hooks that might trigger some
other calculation or validations.

.. note:: changing query parameter during iteration will has no effect until you
    finish iterating.

Keeping models
--------------
If you wish to preserve the objects that you have loaded (not recommended as they
will consume memory), you can do it like this::

    $cat = [];

    foreach(new Model_Category($db) as $id => $c) {
        $cat[$id] = clone $c;
    }


Raw Data Fetching
----------------

.. php:method:: rawIterator()

If you do not care about the hooks and simply wish to get the data, you can fetch
it::

    foreach($m->rawIterator() as $row) {
        var_dump($row); // array
    }

The $row will also contain value for "id" and it's up to you to find it yourself
if you need it.

.. php:method:: export()

Will fetch and output array of hashes which will represent entirety of data-set.
Similarly to other methods, this will have the data mapped into your fields for
you and server-side expressions executed that are embedded in the query.

By default - 'only_fields' will be presented as well as system fields.

Fetching data through action
----------------------------

You can invoke and iterate action (particularly SQL) to fetch the data::

    foreach($m->action('select') as $row) {
        var_dump($row); // array
    }

This has the identical behavior to $m->rawIterator();


Comparison of various ways of fetching
======================================

- getIterator - action(select), [ fetches row, set ID/Data, call afterLoad hook,
  yields model ], unloads data
- rawIterator - action(select), [ fetches row, yields row ]
- export - action(select), fetches all rows, returns all rows
