<?php

declare(strict_types=1);

namespace atk4\data;

use atk4\core\CollectionTrait;
use atk4\core\ContainerTrait;
use atk4\core\DiContainerTrait;
use atk4\core\DynamicMethodTrait;
use atk4\core\FactoryTrait;
use atk4\core\HookTrait;
use atk4\core\InitializerTrait;
use atk4\core\ReadableCaptionTrait;
use atk4\dsql\Query;

/**
 * Data model class.
 *
 * @property Field[]|Reference[] $elements
 * @property mixed               $id       Contains ID of the current record. If the value is null then the record
 *                                         is considered to be new.
 */
class Model implements \IteratorAggregate
{
    use ContainerTrait {
        add as _add;
    }
    use DynamicMethodTrait;
    use HookTrait;
    use InitializerTrait {
        init as _init;
    }
    use DiContainerTrait;
    use FactoryTrait;
    use CollectionTrait;
    use ReadableCaptionTrait;
    use Model\ReferencesTrait;
    use Model\JoinsTrait;
    use Model\UserActionsTrait;

    /** @const string */
    public const HOOK_BEFORE_LOAD = self::class . '@beforeLoad';
    /** @const string */
    public const HOOK_AFTER_LOAD = self::class . '@afterLoad';
    /** @const string */
    public const HOOK_BEFORE_UNLOAD = self::class . '@beforeUnload';
    /** @const string */
    public const HOOK_AFTER_UNLOAD = self::class . '@afterUnload';

    /** @const string */
    public const HOOK_BEFORE_INSERT = self::class . '@beforeInsert';
    /** @const string */
    public const HOOK_AFTER_INSERT = self::class . '@afterInsert';
    /** @const string */
    public const HOOK_BEFORE_UPDATE = self::class . '@beforeUpdate';
    /** @const string */
    public const HOOK_AFTER_UPDATE = self::class . '@afterUpdate';
    /** @const string */
    public const HOOK_BEFORE_DELETE = self::class . '@beforeDelete';
    /** @const string */
    public const HOOK_AFTER_DELETE = self::class . '@afterDelete';

    /** @const string */
    public const HOOK_BEFORE_SAVE = self::class . '@beforeSave';
    /** @const string */
    public const HOOK_AFTER_SAVE = self::class . '@afterSave';

    /** @const string Executed when execution of self::atomic() failed. */
    public const HOOK_ROLLBACK = self::class . '@rollback';

    /** @const string Executed for every field set using self::set() method. */
    public const HOOK_NORMALIZE = self::class . '@normalize';
    /** @const string Executed when self::validate() method is called. */
    public const HOOK_VALIDATE = self::class . '@validate';
    /** @const string Executed when self::onlyFields() method is called. */
    public const HOOK_ONLY_FIELDS = self::class . '@onlyFields';

    // {{{ Properties of the class

    /**
     * The class used by addField() method.
     *
     * @todo use Field::class here and refactor addField() method to not use namespace prefixes.
     *       but because that's backward incompatible change, then we can do that only in next
     *       major version.
     *
     * @var string|array
     */
    public $_default_seed_addField = [Field::class];

    /**
     * The class used by addField() method.
     *
     * @var string|array
     */
    public $_default_seed_addExpression = [Field\Callback::class];

    /**
     * @var array Collection containing Field Objects - using key as the field system name
     */
    protected $fields = [];

    /**
     * Contains name of table, session key, collection or file where this
     * model normally lives. The interpretation of the table will be decoded
     * by persistence driver.
     *
     * You can define this field as associative array where "key" is used
     * as the name of persistence driver. Here is example for mysql and default:
     *
     * $table = ['user', 'mysql'=>'tbl_user'];
     *
     * @var string|array
     */
    public $table;

    /**
     * Use alias for $table.
     *
     * @var string
     */
    public $table_alias;

    /**
     * Sequence name. Some DB engines use sequence for generating auto_increment IDs.
     *
     * @var string
     */
    public $sequence;

    /**
     * Persistence driver inherited from atk4\data\Persistence.
     *
     * @var Persistence|Persistence\Sql
     */
    public $persistence;

    /**
     * Persistence store some custom information in here that may be useful
     * for them. The key is the name of persistence driver.
     *
     * @var array
     */
    public $persistence_data = [];

    /** @var Model\Scope\RootScope */
    protected $scope;

    /**
     * Array of limit set.
     *
     * @var array
     */
    public $limit = [];

    /**
     * Array of set order by.
     *
     * @var array
     */
    public $order = [];

    /**
     * Array of WITH cursors set.
     *
     * @var array
     */
    public $with = [];

    /**
     * Currently loaded record data. This record is associative array
     * that contain field=>data pairs. It may contain data for un-defined
     * fields only if $onlyFields mode is false.
     *
     * Avoid accessing $data directly, use set() / get() instead.
     *
     * @var array
     */
    public $data = [];

    /**
     * After loading an active record from DataSet it will be stored in
     * $data property and you can access it using get(). If you use
     * set() to change any of the data, the original value will be copied
     * here.
     *
     * If the value you set equal to the original value, then the key
     * in this array will be removed.
     *
     * The $dirty data will be reset after you save() the data but it is
     * still available to all before/after save handlers.
     *
     * @var array
     */
    public $dirty = [];

    /**
     * Setting model as read_only will protect you from accidentally
     * updating the model. This property is intended for UI and other code
     * detecting read-only models and acting accordingly.
     *
     * SECURITY WARNING: If you are looking for a RELIABLE way to restrict access
     * to model data, please check Secure Enclave extension.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Once set, Model behaves like an entity and loading a different ID
     * will result in an error.
     *
     * @var mixed
     */
    private $entityId;

    /**
     * While in most cases your id field will be called 'id', sometimes
     * you would want to use a different one or maybe don't create field
     * at all.
     *
     * @var string
     */
    public $id_field = 'id';

    /**
     * Title field is used typically by UI components for a simple human
     * readable row title/description.
     *
     * @var string
     */
    public $title_field = 'name';

    /**
     * Caption of the model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string
     */
    public $caption;

    /**
     * When using onlyFields() this property will contain list of desired
     * fields.
     *
     * If you set onlyFields() before loading the data for this model, then
     * only that set of fields will be available. Attempt to access any other
     * field will result in exception. This is to ensure that you do not
     * accidentally access field that you have explicitly excluded.
     *
     * The default behavior is to return NULL and allow you to set new
     * fields even if addField() was not used to set the field.
     *
     * onlyFields() always allows to access fields with system = true.
     *
     * @var false|array
     */
    public $only_fields = false;

    /**
     * When set to true, all the field types will be enforced and
     * normalized when setting.
     *
     * @var bool
     */
    public $strict_types = true;

    /**
     * When set to true, loading model from database will also
     * perform value normalization. Use this if you think that
     * persistence may contain badly formatted data that may
     * impact your business logic.
     *
     * @var bool
     */
    public $load_normalization = false;

    /**
     * Models that contain expressions will automatically reload after save.
     * This is to ensure that any SQL-based calculation are executed and
     * updated correctly after you have performed any modifications to
     * the fields.
     *
     * You can set this property to "true" or "false" if you want to explicitly
     * enable or disable reloading.
     *
     * @var bool|null
     */
    public $reload_after_save;

    /**
     * If this model is "contained into" another model by using containsOne
     * or containsMany reference, then this property will contain reference
     * to top most parent model.
     *
     * @var Model|null
     */
    public $contained_in_root_model;

    /** @var Reference Only for Reference class */
    public $ownerReference;

    // }}}

    // {{{ Basic Functionality, field definition, set() and get()

    /**
     * Creation of the new model can be done in two ways:.
     *
     * $m = $db->add(new Model());
     *
     * or
     *
     * $m = new Model($db);
     *
     * The second use actually calls add() but is preferred usage because:
     *  - it's shorter
     *  - type hinting will work;
     *  - you can specify string for a table
     *
     * @param Persistence|array $persistence
     * @param string|array      $defaults
     */
    public function __construct($persistence = null, $defaults = [])
    {
        $this->scope = \Closure::bind(function () {
            return new Model\Scope\RootScope();
        }, null, Model\Scope\RootScope::class)();

        if ((is_string($persistence) || is_array($persistence)) && func_num_args() === 1) {
            $defaults = $persistence;
            $persistence = null;
        }

        if (is_string($defaults) || $defaults === false) {
            $defaults = ['table' => $defaults];
        }

        if (isset($defaults[0])) {
            $defaults['table'] = $defaults[0];
            unset($defaults[0]);
        }

        $this->setDefaults($defaults);

        if ($persistence) {
            $persistence->add($this);
        }
    }

    /**
     * Clones model object.
     */
    public function __clone()
    {
        $this->scope = (clone $this->scope)->setModel($this);
        $this->_cloneCollection('elements');
        $this->_cloneCollection('fields');
        $this->_cloneCollection('userActions');

        // check for clone errors immediately, otherwise not strictly needed
        $this->_rebindHooksIfCloned();
    }

    /**
     * Extend this method to define fields of your choice.
     */
    protected function init(): void
    {
        $this->_init();

        if ($this->id_field) {
            $this->addField($this->id_field, ['system' => true]);
        } else {
            return; // don't declare actions for model without id_field
        }

        $this->initEntityHooks();

        if ($this->read_only) {
            return; // don't declare action for read-only model
        }

        // Declare our basic Crud actions for the model.
        $this->addUserAction('add', [
            'fields' => true,
            'modifier' => Model\UserAction::MODIFIER_CREATE,
            'appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS,
            'callback' => 'save',
            'description' => 'Add ' . $this->getModelCaption(),
            'ui' => ['icon' => 'plus'],
        ]);

        $this->addUserAction('edit', [
            'fields' => true,
            'modifier' => Model\UserAction::MODIFIER_UPDATE,
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'callback' => 'save',
            'ui' => ['icon' => 'edit', 'button' => [null, 'icon' => [\atk4\ui\Icon::class, 'edit']], 'execButton' => [\atk4\ui\Button::class, 'Save', 'blue']],
        ]);

        $this->addUserAction('delete', [
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => Model\UserAction::MODIFIER_DELETE,
            'ui' => ['icon' => 'trash', 'button' => [null, 'icon' => [\atk4\ui\Icon::class, 'red trash']], 'confirm' => 'Are you sure?'],
            'callback' => function ($model) {
                return $model->delete();
            },
        ]);

        $this->addUserAction('validate', [
            //'appliesTo'=> any!
            'description' => 'Provided with modified values will validate them but will not save',
            'modifier' => Model\UserAction::MODIFIER_READ,
            'fields' => true,
            'system' => true, // don't show by default
            'args' => ['intent' => 'string'],
        ]);
    }

    private function initEntityHooks(): void
    {
        $checkFx = function () {
            if ($this->getId() === null) { // allow unload
                return;
            }

            if ($this->entityId === null) {
                $this->entityId = $this->getId();
            } else {
                if (!$this->compare($this->id_field, $this->entityId)) {
                    $newId = $this->getId();
                    $this->unload(); // data for different ID were loaded, make sure to discard them

                    throw (new Exception('Model is loaded as an entity, ID can not be changed to a different one'))
                        ->addMoreInfo('entityId', $this->entityId)
                        ->addMoreInfo('newId', $newId);
                }
            }
        };

        $this->onHookShort(self::HOOK_BEFORE_LOAD, $checkFx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_LOAD, $checkFx, [], -10);
        $this->onHookShort(self::HOOK_BEFORE_INSERT, $checkFx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_INSERT, $checkFx, [], -10);
        $this->onHookShort(self::HOOK_BEFORE_UPDATE, $checkFx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_UPDATE, $checkFx, [], -10);
        $this->onHookShort(self::HOOK_BEFORE_DELETE, $checkFx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_DELETE, $checkFx, [], -10);
        $this->onHookShort(self::HOOK_BEFORE_SAVE, $checkFx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_SAVE, $checkFx, [], -10);
    }

    /**
     * Perform validation on a currently loaded values, must return Array in format:
     *  ['field'=>'must be 4 digits exactly'] or empty array if no errors were present.
     *
     * You may also use format:
     *  ['field'=>['must not have character [ch]', 'ch'=>$bad_character']] for better localization of error message.
     *
     * Always use
     *   return array_merge(parent::validate($intent), $errors);
     *
     * @param string $intent by default only 'save' is used (from beforeSave) but you can use other intents yourself
     *
     * @return array [field => err_spec]
     */
    public function validate(string $intent = null): array
    {
        $errors = [];
        foreach ($this->hook(self::HOOK_VALIDATE, [$intent]) as $handler_error) {
            if ($handler_error) {
                $errors = array_merge($errors, $handler_error);
            }
        }

        return $errors;
    }

    /**
     * TEMPORARY to spot any use of $model->add(new Field(), ['bleh']); form.
     */
    public function add(object $obj, array $defaults = []): object
    {
        if ($obj instanceof Field) {
            throw new Exception('You should always use addField() for adding fields, not add()');
        }

        return $this->_add($obj, $defaults);
    }

    /**
     * Adds new field into model.
     *
     * @param array|object $seed
     */
    public function addField(string $name, $seed = []): Field
    {
        if (is_object($seed)) {
            $field = $seed;
        } else {
            $field = $this->fieldFactory($seed);
        }

        return $this->_addIntoCollection($name, $field, 'fields');
    }

    /**
     * Given a field seed, return a field object.
     */
    public function fieldFactory(array $seed = null): Field
    {
        $seed = $this->mergeSeeds(
            $seed,
            isset($seed['type']) ? ($this->typeToFieldSeed[$seed['type']] ?? null) : null,
            $this->_default_seed_addField
        );

        return Field::fromSeed($seed);
    }

    protected $typeToFieldSeed = [
        'boolean' => [Field\Boolean::class],
    ];

    /**
     * Adds multiple fields into model.
     *
     * @return $this
     */
    public function addFields(array $fields, array $defaults = [])
    {
        foreach ($fields as $key => $field) {
            if (!is_int($key)) {
                // field name can be passed as array key
                $name = $key;
            } elseif (is_string($field)) {
                // or it can be simple string = field name
                $name = $field;
                $field = [];
            } elseif (is_array($field) && is_string($field[0] ?? null)) {
                // or field name can be passed as first element of seed array (old behaviour)
                $name = array_shift($field);
            } else {
                // some unsupported format, maybe throw exception here?
                continue;
            }

            $seed = is_object($field) ? $field : array_merge($defaults, (array) $field);

            $this->addField($name, $seed);
        }

        return $this;
    }

    /**
     * Remove field that was added previously.
     *
     * @return $this
     */
    public function removeField(string $name)
    {
        $this->getField($name); // better exception if field does not exist

        $this->_removeFromCollection($name, 'fields');

        return $this;
    }

    public function hasField(string $name): bool
    {
        return $this->_hasInCollection($name, 'fields');
    }

    public function getField(string $name): Field
    {
        try {
            return $this->_getFromCollection($name, 'fields');
        } catch (\atk4\core\Exception $e) {
            throw (new Exception('Field is not defined in model', 0, $e))
                ->addMoreInfo('model', $this)
                ->addMoreInfo('field', $name);
        }
    }

    /**
     * Sets which fields we will select.
     *
     * @return $this
     */
    public function onlyFields(array $fields = [])
    {
        $this->hook(self::HOOK_ONLY_FIELDS, [&$fields]);
        $this->only_fields = $fields;

        return $this;
    }

    /**
     * Sets that we should select all available fields.
     *
     * @return $this
     */
    public function allFields()
    {
        $this->only_fields = false;

        return $this;
    }

    private function checkOnlyFieldsField(string $field)
    {
        $this->getField($field); // test if field exists

        if ($this->only_fields) {
            if (!in_array($field, $this->only_fields, true) && !$this->getField($field)->system) {
                throw (new Exception('Attempt to use field outside of those set by onlyFields'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('only_fields', $this->only_fields);
            }
        }
    }

    /**
     * Will return true if specified field is dirty.
     */
    public function isDirty(string $field): bool
    {
        $this->checkOnlyFieldsField($field);

        if (array_key_exists($field, $this->dirty)) {
            return true;
        }

        return false;
    }

    /**
     * @param string|array|null $filter
     *
     * @return Field[]
     */
    public function getFields($filter = null): array
    {
        if ($filter === null) {
            return $this->fields;
        } elseif (is_string($filter)) {
            $filter = [$filter];
        }

        return array_filter($this->fields, function (Field $field, $name) use ($filter) {
            // do not return fields outside of "only_fields" scope
            if ($this->only_fields && !in_array($name, $this->only_fields, true)) {
                return false;
            }
            foreach ($filter as $f) {
                if (
                    ($f === 'system' && $field->system)
                    || ($f === 'not system' && !$field->system)
                    || ($f === 'editable' && $field->isEditable())
                    || ($f === 'visible' && $field->isVisible())
                ) {
                    return true;
                } elseif (!in_array($f, ['system', 'not system', 'editable', 'visible'], true)) {
                    throw (new Exception('Filter is not supported'))
                        ->addMoreInfo('filter', $f);
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Set field value.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function set(string $field, $value)
    {
        $this->checkOnlyFieldsField($field);

        $f = $this->getField($field);

        try {
            $value = $f->normalize($value);
        } catch (Exception $e) {
            $e->addMoreInfo('field', $field);
            $e->addMoreInfo('value', $value);
            $e->addMoreInfo('f', $f);

            throw $e;
        }

        // do nothing when value has not changed
        $currentValue = array_key_exists($field, $this->data)
            ? $this->data[$field]
            : (array_key_exists($field, $this->dirty) ? $this->dirty[$field] : $f->default);
        if (!$value instanceof \atk4\dsql\Expression && $f->compare($value, $currentValue)) {
            return $this;
        }

        // perform bunch of standard validation here. This can be re-factored in the future.
        if ($f->read_only) {
            throw (new Exception('Attempting to change read-only field'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('model', $this);
        }

        // enum property support
        if (isset($f->enum) && $f->enum && $f->type !== 'boolean') {
            if ($value === '') {
                $value = null;
            }
            if ($value !== null && !in_array($value, $f->enum, true)) {
                throw (new Exception('This is not one of the allowed values for the field'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $this)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('enum', $f->enum);
            }
        }

        // values property support
        if ($f->values) {
            if ($value === '') {
                $value = null;
            } elseif ($value === null) {
                // all is good
            } elseif (!is_string($value) && !is_int($value)) {
                throw (new Exception('Field can be only one of pre-defined value, so only "string" and "int" keys are supported'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $this)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('values', $f->values);
            } elseif (!array_key_exists($value, $f->values)) {
                throw (new Exception('This is not one of the allowed values for the field'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $this)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('values', $f->values);
            }
        }

        if (array_key_exists($field, $this->dirty) && $f->compare($this->dirty[$field], $value)) {
            unset($this->dirty[$field]);
        } elseif (!array_key_exists($field, $this->dirty)) {
            $this->dirty[$field] = array_key_exists($field, $this->data) ? $this->data[$field] : $f->default;
        }
        $this->data[$field] = $value;

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     *
     * @return $this
     */
    public function setNull(string $field)
    {
        // set temporary hook to disable any normalization (null validation)
        $hookIndex = $this->onHookShort(self::HOOK_NORMALIZE, static function () {
            throw new \atk4\core\HookBreaker(false);
        }, [], PHP_INT_MIN);
        try {
            return $this->set($field, null);
        } finally {
            $this->removeHook(self::HOOK_NORMALIZE, $hookIndex, true);
        }
    }

    /**
     * Helper method to call self::set() for each input array element.
     *
     * This method does not revert the data when an exception is thrown.
     *
     * @return $this
     */
    public function setMulti(array $fields)
    {
        foreach ($fields as $field => $value) {
            $this->set($field, $value);
        }

        return $this;
    }

    /**
     * Returns field value.
     * If no field is passed, then returns array of all field values.
     *
     * @return mixed
     */
    public function get(string $field = null)
    {
        if ($field === null) {
            // Collect list of eligible fields
            $data = [];
            foreach ($this->only_fields ?: array_keys($this->getFields()) as $field) {
                $data[$field] = $this->get($field);
            }

            return $data;
        }

        $this->checkOnlyFieldsField($field);

        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        return $this->getField($field)->default;
    }

    private function assertHasIdField(): void
    {
        if (!is_string($this->id_field) || !$this->hasField($this->id_field)) {
            throw new Exception('ID field is not defined');
        }
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        $this->assertHasIdField();

        return $this->get($this->id_field);
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setId($value)
    {
        $this->assertHasIdField();

        if ($value === null) {
            $this->setNull($this->id_field);
        } else {
            $this->set($this->id_field, $value);
        }

        // set entity ID to the first set ID
        if ($this->entityId === null) {
            $this->entityId = $this->getId();
        }

        return $this;
    }

    /**
     * Return (possibly localized) $model->caption.
     * If caption is not set, then generate it from model class name.
     */
    public function getModelCaption(): string
    {
        return $this->caption ?: $this->readableCaption(
            strpos(static::class, 'class@anonymous') === 0 ? get_parent_class(static::class) : static::class
        );
    }

    /**
     * Return value of $model->get($model->title_field). If not set, returns id value.
     *
     * @return mixed
     */
    public function getTitle()
    {
        if ($this->title_field && $this->hasField($this->title_field)) {
            return $this->getField($this->title_field)->get();
        }

        return $this->getId();
    }

    /**
     * Returns array of model record titles [id => title].
     */
    public function getTitles(): array
    {
        $field = $this->title_field && $this->hasField($this->title_field) ? $this->title_field : $this->id_field;

        return array_map(function ($row) use ($field) {
            return $row[$field];
        }, $this->export([$field], $this->id_field));
    }

    /**
     * @param mixed $value
     */
    public function compare(string $name, $value): bool
    {
        return $this->getField($name)->compare($value);
    }

    /**
     * Does field exist?
     */
    public function _isset(string $name): bool
    {
        $this->checkOnlyFieldsField($name);

        return array_key_exists($name, $this->dirty);
    }

    /**
     * Remove current field value and use default.
     *
     * @return $this
     */
    public function _unset(string $name)
    {
        $this->checkOnlyFieldsField($name);

        if (array_key_exists($name, $this->dirty)) {
            $this->data[$name] = $this->dirty[$name];
            unset($this->dirty[$name]);
        }

        return $this;
    }

    // }}}

    // {{{ DataSet logic

    /**
     * Narrow down data-set of the current model by applying
     * additional condition. There is no way to remove
     * condition once added, so if you need - clone model.
     *
     * This is the most basic for defining condition:
     *  ->addCondition('my_field', $value);
     *
     * This condition will work across all persistence drivers universally.
     *
     * In some cases a more complex logic can be used:
     *  ->addCondition('my_field', '>', $value);
     *  ->addCondition('my_field', '!=', $value);
     *  ->addCondition('my_field', 'in', [$value1, $value2]);
     *
     * Second argument could be '=', '>', '<', '>=', '<=', '!=', 'in', 'like' or 'regexp'.
     * Those conditions are still supported by most of persistence drivers.
     *
     * There are also vendor-specific expression support:
     *  ->addCondition('my_field', $expr);
     *  ->addCondition($expr);
     *
     * Conditions on referenced models are also supported:
     *  $contact->addCondition('company/country', 'US');
     * where 'company' is the name of the reference
     * This will limit scope of $contact model to contacts whose company country is set to 'US'
     *
     * Using # in conditions on referenced model will apply the condition on the number of records:
     * $contact->addCondition('tickets/#', '>', 5);
     * This will limit scope of $contact model to contacts that have more than 5 tickets
     *
     * To use those, you should consult with documentation of your
     * persistence driver.
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        $this->scope()->addCondition(...func_get_args());

        return $this;
    }

    /**
     * Get the scope object of the Model.
     */
    public function scope(): Model\Scope\RootScope
    {
        return $this->scope->setModel($this);
    }

    /**
     * Shortcut for using addCondition(id_field, $id).
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function withId($id)
    {
        return $this->addCondition($this->id_field, $id);
    }

    /**
     * Adds WITH cursor.
     *
     * @param Model $model
     *
     * @return $this
     */
    public function addWith(self $model, string $alias, array $mapping = [], bool $recursive = false)
    {
        if (isset($this->with[$alias])) {
            throw (new Exception('With cursor already set with this alias'))
                ->addMoreInfo('alias', $alias);
        }

        $this->with[$alias] = [
            'model' => $model,
            'mapping' => $mapping,
            'recursive' => $recursive,
        ];

        return $this;
    }

    /**
     * Set order for model records. Multiple calls.
     *
     * @param string|array $field
     * @param string       $direction "asc" or "desc"
     *
     * @return $this
     */
    public function setOrder($field, string $direction = 'asc')
    {
        // fields passed as array
        if (is_array($field)) {
            if (func_num_args() > 1) {
                throw (new Exception('If first argument is array, second argument must not be used'))
                    ->addMoreInfo('arg1', $field)
                    ->addMoreInfo('arg2', $direction);
            }

            foreach (array_reverse($field) as $key => $direction) {
                if (is_int($key)) {
                    if (is_array($direction)) {
                        // format [field, direction]
                        $this->setOrder(...$direction);
                    } else {
                        // format "field"
                        $this->setOrder($direction);
                    }
                } else {
                    // format "field" => direction
                    $this->setOrder($key, $direction);
                }
            }

            return $this;
        }

        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw (new Exception('Invalid order direction, direction can be only "asc" or "desc"'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('direction', $direction);
        }

        // finally set order
        $this->order[] = [$field, $direction];

        return $this;
    }

    /**
     * Set limit of DataSet.
     *
     * @return $this
     */
    public function setLimit(int $count = null, int $offset = 0)
    {
        $this->limit = [$count, $offset];

        return $this;
    }

    // }}}

    // {{{ Persistence-related logic

    /**
     * Is model loaded?
     */
    public function loaded(): bool
    {
        return $this->id_field && $this->getId() !== null && $this->entityId !== null;
    }

    /**
     * Unload model.
     *
     * @return $this
     */
    public function unload()
    {
        $this->hook(self::HOOK_BEFORE_UNLOAD);
        $this->data = [];
        if ($this->id_field) {
            $this->setId(null);
        }
        $this->dirty = [];
        $this->hook(self::HOOK_AFTER_UNLOAD);

        return $this;
    }

    /**
     * Load model.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function load($id)
    {
        // deprecated, remove on 2021-03
        if (func_num_args() > 1) {
            throw new Exception('Model::load() with 2nd param $from_persistence is no longer supported');
        }

        if (!$this->persistence) {
            throw new Exception('Model is not associated with any database');
        }

        if ($this->loaded()) {
            $this->unload();
        }

        if ($this->hook(self::HOOK_BEFORE_LOAD, [$id]) === false) {
            return $this;
        }

        $this->data = $this->persistence->load($this, $id);
        if ($this->getId() === null) { // TODO what is the usecase?
            $this->setId($id);
        }

        $ret = $this->hook(self::HOOK_AFTER_LOAD);
        if ($ret === false) {
            return $this->unload();
        } elseif (is_object($ret)) {
            return $ret;
        }

        return $this;
    }

    /**
     * Reload model by taking its current ID.
     *
     * @return $this
     */
    public function reload()
    {
        $id = $this->getId();
        $this->unload();

        return $this->load($id);
    }

    /**
     * Keeps the model data, but wipes out the ID so
     * when you save it next time, it ends up as a new
     * record in the database.
     *
     * @param mixed|null $new_id
     *
     * @return $this
     */
    public function duplicate($new_id = null)
    {
        $this->setId(null);

        if ($this->id_field) {
            $this->setId($new_id);
        }

        return $this;
    }

    /**
     * Saves the current record by using a different
     * model class. This is similar to:.
     *
     * $m2 = $m->newInstance($class);
     * $m2->load($m->getId());
     * $m2->set($m->get());
     * $m2->save();
     *
     * but will assume that both models are compatible,
     * therefore will not perform any loading.
     */
    public function saveAs(string $class, array $options = []): self
    {
        return $this->asModel($class, $options)->save();
    }

    /**
     * Store the data into database, but will never attempt to
     * reload the data. Additionally any data will be unloaded.
     * Use this instead of save() if you want to squeeze a
     * little more performance out.
     *
     * @return $this
     */
    public function saveAndUnload(array $data = [])
    {
        $reloadAfterSaveBackup = $this->reload_after_save;
        try {
            $this->reload_after_save = false;
            $this->save($data);
        } finally {
            $this->reload_after_save = $reloadAfterSaveBackup;
        }

        $this->unload();

        return $this;
    }

    /**
     * This will cast Model into another class without
     * loosing state of your active record.
     */
    public function asModel(string $class, array $options = []): self
    {
        $m = $this->newInstance($class, $options);

        foreach ($this->data as $field => $value) {
            if ($value !== null && $value !== $this->getField($field)->default && $field !== $this->id_field) {
                // Copying only non-default value
                $m->set($field, $value);
            }
        }

        // next we need to go over fields to see if any system
        // values have changed and mark them as dirty

        return $m;
    }

    /**
     * Create new model from the same base class
     * as $this.
     *
     * @return static
     */
    public function newInstance(string $class = null, array $options = [])
    {
        $model = (self::class)::fromSeed([$class ?? static::class], $options);

        if ($this->persistence) {
            return $this->persistence->add($model);
        }

        return $model;
    }

    /**
     * Create new model from the same base class
     * as $this. If you omit $id,then when saving
     * a new record will be created with default ID.
     * If you specify $id then it will be used
     * to save/update your record. If set $id
     * to `true` then model will assume that there
     * is already record like that in the destination
     * persistence.
     *
     * See https://github.com/atk4/data/issues/111 for use-case examples.
     *
     * @param mixed $id
     *
     * @return static
     */
    public function withPersistence(Persistence $persistence, $id = null, string $class = null)
    {
        $class = $class ?? static::class;

        $model = new $class($persistence, $this->table);

        if ($this->id_field) {
            $model->setId($id === true ? $this->get($this->id_field) : $id);
        }

        // include any fields defined inline
        foreach ($this->fields as $fieldName => $field) {
            if (!$model->hasField($fieldName)) {
                $model->addField($fieldName, clone $field);
            }
        }

        $model->data = $this->data;
        $model->dirty = $this->dirty;
        $model->limit = $this->limit;
        $model->order = $this->order;
        $model->scope = (clone $this->scope)->setModel($model);

        return $model;
    }

    /**
     * Try to load record.
     * Will not throw exception if record doesn't exist.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function tryLoad($id)
    {
        $this->checkPersistence('tryLoad');

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->tryLoad($this, $id);
        if ($this->data) {
            $this->setId($id);

            $ret = $this->hook(self::HOOK_AFTER_LOAD);
            if ($ret === false) {
                return $this->unload();
            } elseif (is_object($ret)) {
                return $ret;
            }
        } else {
            $this->unload();
        }

        return $this;
    }

    /**
     * Load any record.
     *
     * @return $this
     */
    public function loadAny()
    {
        $this->checkPersistence('loadAny');

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->loadAny($this);

        if ($this->id_field) {
            $this->setId($this->data[$this->id_field]);
        }

        $ret = $this->hook(self::HOOK_AFTER_LOAD);
        if ($ret === false) {
            return $this->unload();
        } elseif (is_object($ret)) {
            return $ret;
        }

        return $this;
    }

    /**
     * Try to load any record.
     * Will not throw exception if record doesn't exist.
     *
     * @return $this
     */
    public function tryLoadAny()
    {
        $this->checkPersistence('tryLoadAny');

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->tryLoadAny($this);
        if ($this->data) {
            if ($this->id_field) {
                if (isset($this->data[$this->id_field])) {
                    $this->setId($this->data[$this->id_field]);
                }
            }

            $ret = $this->hook(self::HOOK_AFTER_LOAD);
            if ($ret === false) {
                return $this->unload();
            } elseif (is_object($ret)) {
                return $ret;
            }
        } else {
            $this->unload();
        }

        return $this;
    }

    /**
     * Load record by condition.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function loadBy(string $fieldName, $value)
    {
        $field = $this->getField($fieldName);

        $scopeBak = $this->scope;
        $systemBak = $field->system;
        $defaultBak = $field->default;
        try {
            // add condition to cloned scope and try to load record
            $this->scope = clone $this->scope;
            $this->addCondition($field, $value);

            return $this->loadAny();
        } finally {
            $this->scope = $scopeBak;
            $field->system = $systemBak;
            $field->default = $defaultBak;
        }
    }

    /**
     * Try to load record by condition.
     * Will not throw exception if record doesn't exist.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function tryLoadBy(string $fieldName, $value)
    {
        $field = $this->getField($fieldName);

        $scopeBak = $this->scope;
        $systemBak = $field->system;
        $defaultBak = $field->default;
        try {
            // add condition to cloned scope and try to load record
            $this->scope = clone $this->scope;
            $this->addCondition($field, $value);

            return $this->tryLoadAny();
        } finally {
            $this->scope = $scopeBak;
            $field->system = $systemBak;
            $field->default = $defaultBak;
        }
    }

    /**
     * Check if model has persistence with specified method.
     *
     * @param string $method
     */
    public function checkPersistence(string $method = null)
    {
        if (!$this->persistence) {
            throw new Exception('Model is not associated with any persistence');
        }

        if ($method && !$this->persistence->hasMethod($method)) {
            throw new Exception("Persistence does not support {$method} method");
        }
    }

    /**
     * Save record.
     *
     * @return $this
     */
    public $_dirty_after_reload = [];

    public function save(array $data = [])
    {
        // deprecated, remove on 2021-03
        if (func_num_args() > 1) {
            throw new Exception('Model::save() with 2nd param $to_persistence is no longer supported');
        }

        if (!$this->persistence) {
            throw new Exception('Model is not associated with any persistence');
        }

        if ($this->read_only) {
            throw new Exception('Model is read-only and cannot be saved');
        }

        $this->setMulti($data);

        return $this->atomic(function () {
            if (($errors = $this->validate('save')) !== []) {
                throw new ValidationException($errors, $this);
            }
            $is_update = $this->loaded();
            if ($this->hook(self::HOOK_BEFORE_SAVE, [$is_update]) === false) {
                return $this;
            }

            if ($is_update) {
                $data = [];
                $dirty_join = false;
                foreach ($this->dirty as $name => $ignore) {
                    if (!$this->hasField($name)) {
                        continue;
                    }

                    $field = $this->getField($name);
                    if ($field->read_only || $field->never_persist || $field->never_save) {
                        continue;
                    }

                    // get the value of the field
                    $value = $this->get($name);

                    if ($field->hasJoin()) {
                        $dirty_join = true;
                        // storing into a different table join
                        $field->getJoin()->set($name, $value);
                    } else {
                        $data[$name] = $value;
                    }
                }

                // No save needed, nothing was changed
                if (!$data && !$dirty_join) {
                    return $this;
                }

                if ($this->hook(self::HOOK_BEFORE_UPDATE, [&$data]) === false) {
                    return $this;
                }

                $this->persistence->update($this, $this->getId(), $data);

                $this->hook(self::HOOK_AFTER_UPDATE, [&$data]);
            } else {
                $data = [];
                foreach ($this->get() as $name => $value) {
                    if (!$this->hasField($name)) {
                        continue;
                    }

                    $field = $this->getField($name);
                    if ($field->read_only || $field->never_persist || $field->never_save) {
                        continue;
                    }

                    if ($field->hasJoin()) {
                        // storing into a different table join
                        $field->getJoin()->set($name, $value);
                    } else {
                        $data[$name] = $value;
                    }
                }

                if ($this->hook(self::HOOK_BEFORE_INSERT, [&$data]) === false) {
                    return $this;
                }

                // Collect all data of a new record
                $id = $this->persistence->insert($this, $data);

                if (!$this->id_field) {
                    $this->hook(self::HOOK_AFTER_INSERT, [null]);

                    $this->dirty = [];
                } else {
                    $this->setId($id);
                    $this->hook(self::HOOK_AFTER_INSERT, [$this->getId()]);

                    if ($this->reload_after_save !== false) {
                        $d = $this->dirty;
                        $this->dirty = [];
                        $this->reload();
                        $this->_dirty_after_reload = $this->dirty;
                        $this->dirty = $d;
                    }
                }
            }

            if ($this->loaded()) {
                $this->dirty = $this->_dirty_after_reload;
            }

            $this->hook(self::HOOK_AFTER_SAVE, [$is_update]);

            return $this;
        });
    }

    /**
     * This is a temporary method to avoid code duplication, but insert / import should
     * be implemented differently.
     *
     * @param Model $m Model where to insert
     */
    protected function _rawInsert(self $m, array $row)
    {
        $m->unload();

        // Find any row values that do not correspond to fields, and they may correspond to
        // references instead
        $refs = [];
        foreach ($row as $key => $value) {
            // and we only support array values
            if (!is_array($value)) {
                continue;
            }

            // and reference must exist with same name
            if (!$this->hasRef($key)) {
                continue;
            }

            // Then we move value for later
            $refs[$key] = $value;
            unset($row[$key]);
        }

        // save data fields
        $reloadAfterSaveBackup = $m->reload_after_save;
        try {
            $m->reload_after_save = false;
            $m->save($row);
        } finally {
            $m->reload_after_save = $reloadAfterSaveBackup;
        }

        // store id value
        if ($this->id_field) {
            $m->data[$m->id_field] = $m->getId();
        }

        // if there was referenced data, then import it
        foreach ($refs as $key => $value) {
            $m->ref($key)->import($value);
        }
    }

    /**
     * Faster method to add data, that does not modify active record.
     *
     * Will be further optimized in the future.
     *
     * @return mixed
     */
    public function insert(array $row)
    {
        $model = clone $this;
        $model->entityId = null;
        $this->_rawInsert($model, $row);

        return $this->id_field ? $model->getId() : null;
    }

    /**
     * Even more faster method to add data, does not modify your
     * current record and will not return anything.
     *
     * Will be further optimized in the future.
     *
     * @return $this
     */
    public function import(array $rows)
    {
        $this->atomic(function () use ($rows) {
            foreach ($rows as $row) {
                $this->insert($row);
            }
        });

        return $this;
    }

    /**
     * Export DataSet as array of hashes.
     *
     * @param array|null $fields        Names of fields to export
     * @param string     $key_field     Optional name of field which value we will use as array key
     * @param bool       $typecast_data Should we typecast exported data
     */
    public function export(array $fields = null, $key_field = null, $typecast_data = true): array
    {
        $this->checkPersistence('export');

        // no key field - then just do export
        if ($key_field === null) {
            return $this->persistence->export($this, $fields, $typecast_data);
        }

        // do we have added key field in fields list?
        // if so, then will have to remove it afterwards
        $key_field_added = false;

        // prepare array with field names
        if ($fields === null) {
            $fields = [];

            if ($this->only_fields) {
                // Add requested fields first
                foreach ($this->only_fields as $field) {
                    $f_object = $this->getField($field);
                    if ($f_object->never_persist) {
                        continue;
                    }
                    $fields[$field] = true;
                }

                // now add system fields, if they were not added
                foreach ($this->getFields() as $field => $f_object) {
                    if ($f_object->never_persist) {
                        continue;
                    }
                    if ($f_object->system && !isset($fields[$field])) {
                        $fields[$field] = true;
                    }
                }

                $fields = array_keys($fields);
            } else {
                // Add all model fields
                foreach ($this->getFields() as $field => $f_object) {
                    if ($f_object->never_persist) {
                        continue;
                    }
                    $fields[] = $field;
                }
            }
        }

        // add key_field to array if it's not there
        if (!in_array($key_field, $fields, true)) {
            $fields[] = $key_field;
            $key_field_added = true;
        }

        // export
        $data = $this->persistence->export($this, $fields, $typecast_data);

        // prepare resulting array
        $res = [];
        foreach ($data as $row) {
            $key = $row[$key_field];
            if ($key_field_added) {
                unset($row[$key_field]);
            }
            $res[$key] = $row;
        }

        return $res;
    }

    /**
     * Returns iterator (yield values).
     *
     * @return mixed
     */
    public function getIterator(): iterable
    {
        foreach ($this->rawIterator() as $data) {
            $thisCloned = clone $this;

            $thisCloned->data = $this->persistence->typecastLoadRow($this, $data);
            if ($this->id_field) {
                $thisCloned->setId($data[$this->id_field] ?? null);
            }

            // you can return false in afterLoad hook to prevent to yield this data row
            // use it like this:
            // $model->onHook(self::HOOK_AFTER_LOAD, static function ($m) {
            //     if ($m->get('date') < $m->date_from) $m->breakHook(false);
            // })

            // you can also use breakHook() with specific object which will then be returned
            // as a next iterator value

            $ret = $thisCloned->hook(self::HOOK_AFTER_LOAD);

            if ($ret === false) {
                continue;
            }

            if (is_object($ret)) {
                if ($ret->id_field) {
                    yield $ret->getId() => $ret;
                } else {
                    yield $ret;
                }
            } else {
                if ($this->id_field) {
                    yield $thisCloned->getId() => $thisCloned;
                } else {
                    yield $thisCloned;
                }
            }
        }

        $this->unload();
    }

    /**
     * Returns iterator.
     */
    public function rawIterator(): iterable
    {
        return $this->persistence->prepareIterator($this);
    }

    /**
     * Executes specified callback for each record in DataSet.
     *
     * @return $this
     */
    public function each(\Closure $fx)
    {
        foreach ($this as $record) {
            $fx($record);
        }

        return $this;
    }

    /**
     * Delete record with a specified id. If no ID is specified
     * then current record is deleted.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function delete($id = null)
    {
        if ($this->read_only) {
            throw new Exception('Model is read-only and cannot be deleted');
        }

        if ($this->compare($this->id_field, $id)) {
            $id = null;
        }

        return $this->atomic(function () use ($id) {
            if ($id) {
                $c = clone $this;
                $c->load($id)->delete();

                return $this;
            } elseif ($this->loaded()) {
                if ($this->hook(self::HOOK_BEFORE_DELETE, [$this->getId()]) === false) {
                    return $this;
                }
                $this->persistence->delete($this, $this->getId());
                $this->hook(self::HOOK_AFTER_DELETE, [$this->getId()]);
                $this->unload();

                return $this;
            }

            throw new Exception('No active record is set, unable to delete.');
        });
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        // deprecated, remove on 2021-03
        if (func_num_args() > 1) {
            throw new Exception('Model::atomic() with 2nd param $persistence is no longer supported');
        }

        try {
            return $this->persistence->atomic($fx);
        } catch (\Exception $e) {
            if ($this->hook(self::HOOK_ROLLBACK, [$this, $e]) !== false) {
                throw $e;
            }
        }
    }

    // }}}

    // {{{ Support for actions

    /**
     * Execute action.
     *
     * @param string $mode
     * @param array  $args
     *
     * @return Query
     */
    public function action($mode, $args = [])
    {
        $this->checkPersistence('action');

        return $this->persistence->action($this, $mode, $args);
    }

    // }}}

    // {{{ Expressions

    /**
     * Add expression field.
     *
     * @param string|array|\Closure $expression
     *
     * @return Field\Callback
     */
    public function addExpression(string $name, $expression)
    {
        if (!is_array($expression)) {
            $expression = ['expr' => $expression];
        } elseif (isset($expression[0])) {
            $expression['expr'] = $expression[0];
            unset($expression[0]);
        }

        $field = Field::fromSeed($this->_default_seed_addExpression, $expression);

        $this->addField($name, $field);

        return $field;
    }

    /**
     * Add expression field which will calculate its value by using callback.
     *
     * @param string|array|\Closure $expression
     *
     * @return Field\Callback
     */
    public function addCalculatedField(string $name, $expression)
    {
        if (!is_array($expression)) {
            $expression = ['expr' => $expression];
        } elseif (isset($expression[0])) {
            $expression['expr'] = $expression[0];
            unset($expression[0]);
        }

        return $this->addField($name, new Field\Callback($expression));
    }

    // }}}

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id_field && $this->hasField('id') ? $this->getId() : 'no id field',
            'scope' => $this->scope()->toWords(),
        ];
    }

    // }}}
}
