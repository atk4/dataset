
.. _Model:

=====
Model
=====

.. php:class:: Model

Probably the most significant class in ATK Data - Model - acts as a Parent for all your
entity classes::

   class User extends \Atk4\Data\Model

You must define individual classes for all your business entities. Other frameworks may rely
on XML or annotations, in ATK everything is defined inside your "Model" class through
pure PHP (See Initialization below)

Once you create instance of your model class, it can be recycled. With a single
object you can load/unload individual records (See Single record Operations below)::

   $m = new User($db);

   $m->load(3);
   $m->set('note', 'just updating');
   $m->save();
   $m->unload();

   $m->load(8);
   ....

and even perform operations on multiple records (See `Persistence Actions` below)::

   $m = new User($db);
   $m->addCondition('expired', true);

   $m->action('delete')->execute(); // performs mass delete, hooks are not executed
   
   $m->each(function () use ($m) { $m->delete(); }); // deletes each record, hooks are executed

When data is loaded from associated Persistence, it is automatically converted into
a native PHP type (such as DateTime object) through a process called Typecasting. Various
rules apply when you set value for model fields (Normalization) or when data is stored
into database that does support a field type (Serialization)

Furthermore, because you define Models as a class, it is very easy to introduce your own
extensions which may include Hooks and Actions.

There are many advanced topics that ATK Data covers, such as References, Joins, Aggregation,
SQL actions, Unions, Deep Traversal and Containment.

The design is also very extensible allowing you to introduce new Field types, Join strategies,
Reference patterns, Action types.

I suggest you to read the next section to make sure you fully understand the Model and its role
in ATK Data.


Understanding Model
===================

Please understand that Model in ATK Data is unlike models in other data frameworks. The
Model class can be seen as a "gateway" between your code and many other features of ATK Data.

For example - you may define fields and relations for the model::

   $model->addField('age', ['type'=>'number']);
   $model->hasMany('Children', ['model' => [Person::class]]);

Methods `addField` and `hasMany` will ultimatelly create and link model with a corresponding
`Field` object and `Reference` object. Those classes contain the logic, but in 95% of the use-cases,
you will not have to dive deep into them.

Model object = Data Set
-----------------------
From the moment when you create instance of your model class, it represents a DataSet - set of records
that share some common traits::

   $all_users = new User($db); // John, Susan, Leo, Bill, Karen

Certain operations may "shrink" this set, such as adding conditions::

   $male_users = $all_users->addCondition('gender', 'M');

   send_email_to_users($male_users);

This essentially filters your users without fetching them from the database server. In my example,
when I pass `$male_users` to the method, no records are loaded yet from the database. It is up to
the implementation of `send_email_to_users` to load or iterate records or perhaps approach the
data-set differently, e.g. execute multi-record operation.

Note that most
operations on Model are mutating (meaning that in example above `$all_users` will also be filtered
and in fact, `$all_users` and `$male_users` will reference same object. Use `clone` if you do not wish
to affect `$all_users`.

Model object = meta information
-------------------------------
By design, Model object does not have direct knowledge of higher level objects or specific
implementations. Still - Model will be a good place to deposit some meta-information::

   $model->addField('age', ['ui'=>['caption'=>'Put your age here']]);

Model and Field class will simply store the "ui" property which may (or may not) be used by ATK UI
component or some add-on.


Domain vs Persistence
---------------------
When you declare a model Field you can also store some persistence-related meta-information::

   // override how your persistence formats date field
   $model->addField('date_of_birth', ['type'=>'date', 'persistence'=>['format'=>'Ymd']]);

   // declare field which is not saved
   $model->addField('secret', ['never_persist'=>true]);

   // rellocate into a different field
   $model->addField('old_field', ['actual'=>'new_field']);

   // or even into a different table
   $model->join('new_table')->addField('extra_field');

Model also has a property `$table`, which indicate name of default table/collection/file to be
used by persistence. (Name of property is decided to avoid beginner confusion)


Good naming for a Model
-----------------------
Some parts of this documentation were created years ago and may use class notation: `Model_User`.
We actually recommend you to use namespaces instead::

   namespace yourapp\Model;

   use \Atk4\Data\Model;

   class User extends Model {
      function init(): void {
         parent::init();

         $this->addField('name');

         $this->hasMany('Invoices', ['model' => [Invoice::class]]);
      }
   }

PHP does not have a "class" type, so `Invoice::class` will translate into a string "yourapp\Model\Invoice"
and is a most efficient way to specify related class name.

You way also use `new Invoice()` there but be sure not to specify any argument, unless you intend
to use cross-persistence referencing (this is further explained in Advanced section)


Initialization
==============

.. php:method:: init

Method init() will automatically be called when your Model is associated with
Persistence object. It is commonly used to declare fields, conditions, relations, hooks and more::

    class Model_User extends Atk4\Data\Model
    {
        function init(): void {
            parent::init();

            $this->addField('name');
            $this->addField('surname');
        }
    }

You may safely rely on `$this->persistence` property to make choices::

   if ($this->persistence instanceof \Atk4\Data\Persistence\Sql) {

      // Calculating on SQL server is more efficient!!
      $this->addExpression('total', '[amount] + [vat]');
   } else {

      // Fallback
      $this->addCalculatedField('total', function($m) {
         return $m->get('amount') + $m->get('vat');
      } );
   }

To invoke code from `init()` methods of ALL models (for example soft-delete logic),
you use Persistence's "afterAdd" hook. This will not affect ALL models but just models
which are associated with said persistence::

   $db->onHook(Persistence::HOOK_AFTER_ADD, function($p, $m) use($acl) {

      $fields = $m->getFields();

      $acl->disableRestrictedFields($fields);

   });

   $invoice = new Invoice($db);

Fields
------
Each model field is represented by a Field object::

   $model->addField('name');

   var_dump($model->getField('name'));

Other persistence framework will use "properties", because individual objects may impact
performance. In ATK Data this is not an issue, because "Model" is re-usable::

   foreach(new User($db) as $user) {

      // will be the same object every time!!
      var_dump($user->getField['name']);

      // this is also the same object every time!!
      var_dump($user)

   }

Instead, Field handles many very valuable operations which would otherwise fall on the
shoulders of developer (Read more here :php:class:`Field`)

.. php:method:: addField($name, $seed)

Creates a new field object inside your model (by default the class is 'Field').
The fields are implemented on top of Containers from Agile Core.

Second argument to addField() will contain a seed for the Field class::

   $this->addField('surname', ['default'=>'Smith']);

Additionally, `type` property can be used to determine the best `Field` class to handle
the type::

   $field = $this->addField('is_married', ['type'=>'boolean']);

   // $field class now will be Field\Boolean

You may also specify your own Field implementation::

   $field = $this->addField('amount_and_currency', new MyAmountCurrencyField());

Read more about :php:class:`Field`

.. php:method:: addFields(array $fields, $defaults = [])

Creates multiple field objects in one method call. See multiple syntax examples::

    $m->addFields(['name'], ['default' => 'anonymous']);

    $m->addFields([
        'last_name',
        'login' => ['default' => 'unknown'],
        'salary' => ['type'=>'money', CustomField::class, 'default' => 100],
        ['tax', CustomField::class, 'type'=>'money', 'default' => 20],
        'vat' => new CustomField(['type'=>'money', 'default' => 15]),
    ]);


Read-only Fields
^^^^^^^^^^^^^^^^
Although you may make any field read-only::

   $this->addField('name', ['read_only'=>true]);

There are two methods for adding dynamically calculated fields.

.. php:method:: addExpression($name, $definition)

Defines a field as server-side expression (e.g. SQL)::

   $this->addExpression('total', '[amount] + [vat]');

The above code is executed on the server (SQL) and can be very powerful.
You must make sure that expression is valid for current `$this->persistence`::

   $product->addExpression('discount', $this->refLink('category_id')->fieldQuery('default_discount'));
   // expression as a sub-select from referenced model (Category) imported as a read-only field
   // of $product model

   $product->addExpression('total', 'if([is_discounted], ([amount]+[vat])*[discount], [amount] + [vat])');
   // new "total" field now contains complex logic, which is executed in SQL

   $product->addCondition('total', '<', 10);
   // filter products that cost less than 10.00 (including discount)


For the times when you are not working with SQL persistence, you can calculate field in PHP.

.. php:method:: addCalculatedField($name, $callback)

Creates new field object inside your model. Field value will be automatically
calculated by your callback method right after individual record is loaded by the model::

   $this->addField('term', ['caption'=>'Repayment term in months', 'default'=>36]);
   $this->addField('rate', ['caption'=>'APR %', 'default'=>5]);

   $this->addCalculatedField('interest', function($m) {
      return $m->calculateInterest();
   });

.. important:: always use argument `$m` instead of `$this` inside your callbacks. If model is to be
   `clone`d, the code relying on `$this` would reference original model, but the code using
   `$m` will properly address the model which triggered the callback.

This can also be useful for calculating relative times::

   class MyModel extends Model {
      use HumanTiming; // See https://stackoverflow.com/questions/2915864/php-how-to-find-the-time-elapsed-since-a-date-time

      function init(): void {
         parent::init();

         $this->addCalculatedField('event_ts_human_friendly', function($m) {
            return $this->humanTiming($m->get('event_ts'));
         });

      }
   }


Strict Fields
^^^^^^^^^^^^^

.. php:property:: strict_fields

By default model will only allow you to operate with values for the fields
that have been defined through addField(). If you attempt to get, set or
otherwise access the value of any other field that has not been properly
defined, you'll get an exception. Read more about :php:class:`Field`

If you set `strict_fields` to false, then the check will not be performed.

Actions
-------
Another common thing to define inside :php:meth:`Model::init()` would be
a user invokable actions::

   class User extends Model {

      function init(): void {

         parent::init();

         $this->addField('name');
         $this->addField('email');
         $this->addField('password', ['type'=>'password']);

         $this->addUserAction('send_new_password');

      }

      function send_new_password()
      {
         // .. code here

         $this->save(['password'=> .. ]);

         return 'generated and sent password to '.$m->get('name');
      }
   }

With a method alone, you can generate and send passwords::

   $user->load(3);
   $user->send_new_password();

but using `$this->addUserAction()` exposes that method to the ATK UI wigets,
so if your admin is using `Crud`, a new button will be available allowing
passwords to be generated and sent to the users::

   Crud::addTo($app)->setModel(new User($app->db));

Read more about :php:class:`Model\UserAction`

Hooks
-----
Hooks (behaviours) can allow you to define callbacks which would trigger
when data is loaded, saved, deleted etc. Hooks are typically defined in
:php:meth:`Model::init()` but will be executed accordingly.

There are countless uses for hooks and even more opportunities to use
hook by all sorts of extensions.

Validation
^^^^^^^^^^

Validation is an extensive topic, but the simplest use-case would be through
a hook::

   $this->addField('name');

   $this->onHookShort(Model::HOOK_VALIDATE, function() {
      if ($this->get('name') === 'C#') {
         return ['name'=>'No sharp objects are allowed'];
      }
   });

Now if you attempt to save object, you will receive :php:class:`ValidationException`::

   $model->set('name', 'Swift');
   $model->saveAndUnload();      // all good

   $model->set('name', 'C#');
   $model->saveAndUnload();      // exception here


Other Uses
^^^^^^^^^^

Other uses for model hooks are explained in :ref:`Hooks`


Inheritance
-----------
ATK Data models are really good for structuring hierarchically. Here is example::

   class VipUser extends User {
      function init(): void {
         parent::init();

         $this->addCondition('purchases', '>', 1000);

         $this->addUserAction('send_gift');
      }

      function send_gift() {
          ...
      }
   }

This introduces a new business object, which is a sub-set of User. The new class will
inherit all the fields, methods and actions of "User" class but will introduce one new
action - `send_gift`.

There are some advanced techniques like "SubTypes" or class substitution,
for example, this hook may be placed in the "User" class init()::

   $this->onHookShort(Model::HOOK_AFTER_LOAD, function() {
      if ($this->get('purchases') > 1000) {
         $this->breakHook($this->asModel(VipUser::class);
      }
   });

See also :php:class:`Field\\SubTypeSwitch`


Associating Model with Database
===============================

After talking extensively about model definition, lets discuss how model is associated
with persistence. In the most basic form, model is associated with persistence like this::

   $m = new User($db);

If model was created without persistence :php:meth:`Model::init()` will not fire. You can
explicitly associate model with persistence like this::

   $m = new User();

   // ....

   $db->add($m);  // links with persistence

Multiple models can be associated with the same persistence. Here are also some examples
of static persistence::

   $m = new Model(new Persistence\Static_(['john', 'peter', 'steve']);

   $m->load(1);
   echo $m->get('name');  // peter

See :php:class:`Persistence\\Static_`

.. php:attr:: persistence

Refers to the persistence driver in use by current model. Calling certain
methods such as save(), addCondition() or action() will rely on this property.

.. php:attr:: persistence_data

DO NOT USE: Array containing arbitrary data by a specific persistence layer.

.. php:attr:: table

If $table property is set, then your persistence driver will use it as default
table / collection when loading data. If you omit the table, you should specify
it when associating model with database::

   $m = new User($db, 'user');

This also overrides current table value.

.. php:method:: withPersistence($persistence, $id = null, $class = null)

Creates a duplicate of a current model and associate new copy with a specified
persistence. This method is useful for moving model data from one persistence
to another.

.. php:method:: asModel($class, $options = [])

Casts current model into another class. The new model class should be compatible
with $this - you can do `$user->asModel(VipUser::class)` but converting `$user`
into `Invoice::class` is a bad idea.

Although class is switched, the new model will retain current record data, replace all
fields/actions and will combine conditions (avoiding identical conditions).

Populating Data
===============

.. php:method:: insert($row)

    Inserts a new record into the database and returns $id. It does not affect
    currently loaded record and in practice would be similar to::

        $m_x = $m;
        $m_x->unload();
        $m_x->setMulti($row);
        $m_x->save();
        return $m_x;

    The main goal for insert() method is to be as fast as possible, while still
    performing data validation. After inserting method will return cloned model.

.. php:method:: import($data)

    Similar to insert() however works across array of rows. This method will
    not return any IDs or models and is optimized for importing large amounts
    of data.

    The method will still convert the data needed and operate with joined
    tables as needed. If you wish to access tables directly, you'll have to look
    into Persistence::insert($m, $data, $table);



Working with selective fields
=============================

When you normally work with your model then all fields are available and will be
loaded / saved. You may, however, specify that you wish to load only a sub-set
of fields.

(In ATK4.3 we call those fields "Actual Fields")

.. php:method:: onlyFields($fields)

    Specify array of fields. Only those fields will be accessible and will be
    loaded / saved. Attempt to access any other field will result in exception.

.. php:method:: allFields()

    Restore to full set of fields. This will also unload active record.

.. php:attr:: only_fields

    Contains list of fields to be loaded / accessed.

.. _Active Record:

Setting and Getting active record data
======================================

When your record is loaded from database, record data is stored inside the $data
property:

.. php:attr:: data

    Contains the data for an active record.

Model allows you to work with the data of single a record directly. You should
use the following syntax when accessing fields of an active record::

    $m->set('name', 'John');
    $m->set('surname', 'Peter');
    // or
    $m->setMulti(['name' => 'John', 'surname' => 'Peter']);

When you modify active record, it keeps the original value in the $dirty array:

.. php:method:: set($field, $value)

    Set field to a specified value. The original value will be stored in
    $dirty property.

.. php:method:: setMulti($fields)

    Set multiple field values.

.. php:method:: setNull($field)

    Set value of a specified field to NULL, temporarily ignoring normalization routine.
    Only use this if you intend to set a correct value shortly after.

.. php:method:: unset($field)

    Restore field value to it's original::

        $m->set('name', 'John');
        echo $m->get('name'); // John

        $m->_unset('name');
        echo $m->get('name'); // Original value is shown

    This will restore original value of the field.

.. php:method:: get

    Returns one of the following:

     - If value was set() to the field, this value is returned
     - If field was loaded from database, return original value
     - if field had default set, returns default
     - returns null.

.. php:method:: isset

    Return true if field contains unsaved changes (dirty)::

        $m->_isset('name'); // returns false
        $m->set('name', 'Other Name');
        $m->_isset('name'); // returns true


.. php:method:: isDirty

    Return true if one or multiple fields contain unsaved changes (dirty)::

        if ($m->isDirty(['name','surname'])) {
           $m->set('full_name', $m->get('name').' '.$m->get('surname'));
        }

    When the code above is placed in beforeSave hook, it will only be executed
    when certain fields have been changed. If your recalculations are expensive,
    it's pretty handy to rely on "dirty" fields to avoid some complex logic.

.. php:attr:: dirty

    Contains list of modified fields since last loading and their original
    values.

.. php:method:: hasField($field)

    Returns true if a field with a corresponding name exists.

.. php:method:: getField($field)

    Finds a field with a corresponding name. Throws exception if field not found.


Full example::

    $m = new Model_User($db, 'user');

    // Fields can be added after model is created
    $m->addField('salary', ['default'=>1000]);

    echo $m->_isset('salary');  // false
    echo $m->get('salary');          // 1000

    // Next we load record from $db
    $m->load(1);

    echo $m->get('salary');          // 2000 (from db)
    echo $m->_isset('salary');  // false, was not changed

    $m->set('salary', 3000);

    echo $m->get('salary');          // 3000 (changed)
    echo $m->_isset('salary');  // true

    $m->_unset('salary');        // return to original value

    echo $m->get('salary');          // 2000
    echo $m->_isset('salary');  // false

    $m->set('salary', 3000);
    $m->save();

    echo $m->get('salary');          // 3000 (now in db)
    echo $m->_isset('salary');  // false

.. php:method:: protected normalizeFieldName

    Verify and convert first argument got get / set;

Title Field, ID Field and Model Caption
=======================================

Those are three properties that you can specify in the model or pass it through
defaults::

    class MyModel ..
        public $title_field = 'full_name';

or as defaults::

    $m = new MyModel($db, ['title_field'=>'full_name']);


.. _id_field:

ID Field
--------

.. php:attr:: id_field

    If your data storage uses field different than ``id`` to keep the ID of your
    records, then you can specify that in $id_field property.

.. tip:: You can change ID value of the current ID field by calling::

        $m->set('id', $new_id);
        $m->save();

    This will update existing record with new $id. If you want to save your
    current field over another existing record then::

        $m->setId($new_id);
        $m->save();

    You must remember that only dirty fields are saved, though. (We might add
    replace() function though).

.. _title_field:

Title Field
-----------

.. php:attr:: title_field

    This field by default is set to 'name' will act as a primary title field of
    your table. This is especially handy if you use model inside UI framework,
    which can automatically display value of your title field in the header,
    or inside drop-down.

    If you don't have field 'name' but you want some other field to be title,
    you can specify that in the property. If title_field is not needed, set it
    to false or point towards a non-existent field.

    See: :php:meth::`hasOne::addTitle()` and :php:meth::`hasOne::withTitle()`

.. php:method:: public getTitle

    Return title field value of currently loaded record.

.. php:method:: public getTitles

    Returns array of title field values of all model records in format [id => title].

.. _caption:

Model Caption
-------------

.. php:attr:: caption

    This is caption of your model. You can use it in your UI components.

.. php:method:: public getModelCaption

    Returns model caption. If caption is not set, then try to generate one from
    model class name.


Setting limit and sort order
============================

.. php:method:: public setLimit($count, $offset = null)

    Sets limit on how many records to select. Will select only $count records
    starting from $offset record.

.. php:method:: public setOrder($field, $desc = null)

    Sets sorting order of returned data records. Here are some usage examples.
    All these syntaxes work the same::

        $m->setOrder('name, salary desc');
        $m->setOrder(['name', 'salary desc']);
        $m->setOrder(['name', 'salary'=>true]);
        $m->setOrder(['name'=>false, 'salary'=>true]);
        $m->setOrder([ ['name'], ['salary','desc'] ]);
        $m->setOrder([ ['name'], ['salary',true] ]);
        $m->setOrder([ ['name'], ['salary desc'] ]);
        // and there can be many more similar combinations how to call this

    Keep in mind - `true` means `desc`, desc means descending. Otherwise it will be ascending order by default.

    You can also use \Atk4\Dsql\Expression or array of expressions instead of field name here.
    Or even mix them together::

        $m->setOrder($m->expr('[net]*[vat]'));
        $m->setOrder([$m->expr('[net]*[vat]'), $m->expr('[closing]-[opening]')]);
        $m->setOrder(['net', $m->expr('[net]*[vat]', 'ref_no')]);
