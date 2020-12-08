
.. php:namespace:: Atk4\Data\Field

==========
Data Types
==========

ATK Data framework implements a consistent and extensible type system with the
following goals:

Type specification
==================

 - Provide list of out-of-the-box types, such as "percentage"
 - Provide list of classes such as :php:class:`Fraction`
 - Mechanism to find corresponding class configuration based on selected type

Specifying one of supported types will ensure that your field format is
recognized universally, can be stored, loaded, presented to user through UI
inside a Table or Form and can be exported through RestAPI::

    $this->addField('is_vip', ['type'=>'boolean']);

We also allow use of custom Field implementation::

    $this->addField('encrypted_password', new \Atk4\Login\Field\Password());

A properly implemented type will still be able to offer some means to present
it in human-readable format, however in some cases, if you plan on using ATK UI,
you would have to create a custom decorators/FormField to properly read and
present your type value. See :php:attr:`\\Atk4\\Ui\\Field::ui`.

Persistence mechanics and Serialization
=======================================

All type values can be specified as primitives. For example `DateTime` object
class is associated with the `type=time` will be converted into string with
default format of "21:43:05".

Types that cannot be converted into primitive, there exist a process of "serialization",
which can use JSON or standard serialize() method to store object inside
incompatible database/persistence.

Serialization abilities allow us to get rid of many arbitrary types such as "array_json"
and simply use this::

    $model->addField('selection', ['type'=>'array', 'serialize'=>'json']);

Field configuration
===================

Fields can be further configured. For numeric fields it's possible to provide
precision. For instance, when user specifies `type=money` it is represented
as `['Number', 'precision'=>2, 'prefix'=>'€']`

Not only this allows us make a flexible and re-usable functionality for fields,
but also allows for an easy way to override::

    $model->addField('salary', ['type'=>'money', 'precision'=>4', 'prefix'=>false, 'postfix'=>'Rub']);

Although some configuration of the field may appear irrelevant (prefix/postfix)
to operations with data from inside PHP, those properties can be used by
ATK UI or data export routines to properly input or display values.

Typecasting
===========

ATK Data uses PHP native types and classes. For example, 'time' type is using
DateTime object.

When storing or displaying a type-casting takes place which will format the
value accordingly. Type-casting can be persistence-specific, for instance,
when storing "datetime" into SQL, the ISO format will be used, but when displayed
to the user a regional format is used instead. 

Supported Types
===============

ATK Data prior to 1.5 supports the following types:

 - string
 - boolean ([':php:class:`Boolean`'])
 - integer ([':php:class:`Number`', 'precision'=>0])
 - money ([':php:class:`Number`', 'prefix'=>'€', 'precision'=>2])
 - float ([':php:class:`Number`', 'type'=>'float'])
 - date ([':php:class:`DateTime`'])
 - datetime ([':php:class:`DateTime`'])
 - time ([':php:class:`DateTime`'])
 - password ([':php:class:`Password`])
 - array
 - object

In ATK Data the number of supported types has been extended with:

 - percent (34.2%) ([':php:class:`Number`', 'format'=>function($v){ return $v*100; }, 'postfix'=>'%'])
 - rating (3 out of 5) ([':php:class:`Number`', 'max'=>5, 'precision'=>0])
 - uuid (xxxxxxxx-xxxx-...) ([':php:class:`Number`', 'base'=>16, 'mask'=>'########-##..'])
 - hex (number with base 16) ([':php:class:`Number`', 'base'=>16])
 - ip (123.2.44.1) ([':php:class:`Number`', 'base'=>256, 'mask'=>'#.#.#.#'])
 - ipv6 ([':php:class:`Number`', 'base'=>16', 'mask'=>'####:####:..']);
 - model (used for containment)
 - fraction (5/7) ([':php:class:`Fraction`'])

Additionally there is a support for 

 - distance ([':php:class:`Units`', 'scale'=>['m'=>1, 'km'=>1000, 'mm'=>0.001])
 - duration
 - mass
 - area
 - volume

All measurements are implemented with :php:class:`Units` and can be further extended::

    $model->addField('speed', ['Units', 'postfix'=>'/s', 'scale'=>['m'=>1, 'km'=>1000]]);
    $model->set('speed', '30km/s');

    echo $model->get('speed'); // 30000
    echo $model->getField('speed')->format(); // 30km/s
    echo $model->getField('speed')->format('m'); // 30000m/s


Supported Serialization
=======================

ATK Data prior to 1.5 supported:

 - 'serialize' - for storing PHP objects, uses `serialize`, `unserialize`
 - 'json' - for storing objects and arrays, uses `json_encode`, `json_decode`
 - 'base64' - for storing encoded strings, uses `base64_encode`, `base64_decode`
 - [serialize_callback, unserialize_callback] - for custom serialization

In 1.5 we have added support for more:

 - list - separate values with comma, good for storing IDs
 - binary - incredibly compact format for numbers

List of Field Classes
=====================

.. toctree::
    :maxdepth: 1

    field_boolean
    field_text
    field_number
    field_datetime
    field_units


