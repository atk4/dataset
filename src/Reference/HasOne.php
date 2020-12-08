<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * Reference\HasOne class.
 */
class HasOne extends Reference
{
    use Model\JoinLinkTrait;

    /**
     * Field type.
     *
     * Values are: 'string', 'text', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
     * Can also be set to unspecified type for your own custom handling.
     *
     * @var string
     */
    public $type;

    /**
     * Is it system field?
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string
     */
    public $caption;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Is field mandatory? By default fields are not mandatory.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Is field required? By default fields are not required.
     *
     * @var bool|string
     */
    public $required = false;

    /**
     * Should we use typecasting when saving/loading data to/from persistence.
     *
     * Value can be array [$typecast_save_callback, $typecast_load_callback].
     *
     * @var bool|array|null
     */
    public $typecast;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array [$encode_callback, $decode_callback].
     *
     * @var bool|array|string|null
     */
    public $serialize;

    /**
     * Persisting format for type = 'date', 'datetime', 'time' fields.
     *
     * For example, for date it can be 'Y-m-d', for datetime - 'Y-m-d H:i:s.u' etc.
     *
     * @var string
     */
    public $persist_format;

    /**
     * Persisting timezone for type = 'date', 'datetime', 'time' fields.
     *
     * For example, 'IST', 'UTC', 'Europe/Riga' etc.
     *
     * @var string
     */
    public $persist_timezone = 'UTC';

    /**
     * DateTime class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTime', 'Carbon' etc.
     *
     * @var string
     */
    public $dateTimeClass = \DateTime::class;

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon' etc.
     *
     * @var string
     */
    public $dateTimeZoneClass = \DateTimeZone::class;

    /**
     * Reference\HasOne will also add a field corresponding
     * to 'our_field' unless it exists of course.
     */
    protected function init(): void
    {
        parent::init();

        if (!$this->our_field) {
            $this->our_field = $this->link;
        }

        $ourModel = $this->getOurModel();

        if (!$ourModel->hasField($this->our_field)) {
            $ourModel->addField($this->our_field, [
                'type' => $this->type,
                'reference' => $this,
                'system' => $this->system,
                'joinName' => $this->joinName,
                'default' => $this->default,
                'never_persist' => $this->never_persist,
                'read_only' => $this->read_only,
                'caption' => $this->caption,
                'ui' => $this->ui,
                'mandatory' => $this->mandatory,
                'required' => $this->required,
                'typecast' => $this->typecast,
                'serialize' => $this->serialize,
                'persist_format' => $this->persist_format,
                'persist_timezone' => $this->persist_timezone,
                'dateTimeClass' => $this->dateTimeClass,
                'dateTimeZoneClass' => $this->dateTimeZoneClass,
            ]);
        }
    }

    /**
     * Returns our field or id field.
     */
    protected function referenceOurValue(): Field
    {
        $this->getOurModel()->persistence_data['use_table_prefixes'] = true;

        return $this->getOurField();
    }

    /**
     * If our model is loaded, then return their model with respective record loaded.
     *
     * If our model is not loaded, then return their model with condition set.
     * This can happen in case of deep traversal $model->ref('Many')->ref('one_id'), for example.
     */
    public function ref(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        // add hook to set our_field = null when record of referenced model is deleted
        $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_DELETE, function ($theirModel) {
            $this->getOurField()->setNull();
        });

        if ($ourValue = $this->getOurFieldValue()) {
            // if our model is loaded, then try to load referenced model
            if ($this->their_field) {
                $theirModel->tryLoadBy($this->their_field, $ourValue);
            } else {
                $theirModel->tryLoad($ourValue);
            }
        }

        // their model will be reloaded after saving our model to reflect changes in referenced fields
        $theirModel->reload_after_save = false;

        $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_SAVE, function ($theirModel) {
            $theirValue = $this->their_field ? $theirModel->get($this->their_field) : $theirModel->getId();

            if ($this->getOurFieldValue() !== $theirValue) {
                $this->getOurField()->set($theirValue)->getOwner()->save();
            }

            $theirModel->reload();
        });

        return $theirModel;
    }
}
