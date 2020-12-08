<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Exception;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Model;

/**
 * Implements generic user action. Assigned to a model it can be invoked by a user. Model\UserAction class contains a
 * meta information about the action (arguments, permissions, appliesTo records, etc) that will help UI/API or add-ons to display
 * action trigger (button) correctly in an automated way.
 *
 * UserAction must NOT rely on any specific UI implementation.
 *
 * @method Model getOwner()
 */
class UserAction
{
    use DiContainerTrait;
    use TrackableTrait;
    use InitializerTrait {
        init as init_;
    }

    /** Defining records scope of the action */
    public const APPLIES_TO_NO_RECORDS = 'none'; // e.g. add
    public const APPLIES_TO_SINGLE_RECORD = 'single'; // e.g. archive
    public const APPLIES_TO_MULTIPLE_RECORDS = 'multiple'; // e.g. delete
    public const APPLIES_TO_ALL_RECORDS = 'all'; // e.g. truncate

    /** @var string by default - action is for a single-record */
    public $appliesTo = self::APPLIES_TO_SINGLE_RECORD;

    /** Defining action modifier */
    public const MODIFIER_CREATE = 'create'; // create new record(s).
    public const MODIFIER_UPDATE = 'update'; // update existing record(s).
    public const MODIFIER_DELETE = 'delete'; // delete record(s).
    public const MODIFIER_READ = 'read'; // just read, does not modify record(s).

    /** @var string How this action interact with record. default = 'read' */
    public $modifier = self::MODIFIER_READ;

    /** @var \Closure code to execute. By default will call method with same name */
    public $callback;

    /** @var \Closure code, identical to callback, but would generate preview of action without permanent effect */
    public $preview;

    /** @var string caption to put on the button */
    public $caption;

    /** @var string|\Closure a longer description of this action. Closure must return string. */
    public $description;

    /** @var bool Specifies that the action is dangerous. Should be displayed in red. */
    public $dangerous = false;

    /** @var bool|string|\Closure Set this to "true", string or return the value from the callback. Will ask user to confirm. */
    public $confirmation = false;

    /** @var array UI properties, e,g. 'icon'=>.. , 'warning', etc. UI implementation can interpret or extend. */
    public $ui = [];

    /** @var bool|\Closure setting this to false will disable action. Callback will be executed with ($m) and must return bool */
    public $enabled = true;

    /** @var bool system action will be hidden from UI, but can still be explicitly triggered */
    public $system = false;

    /** @var array Argument definition. */
    public $args = [];

    /** @var array|bool Specify which fields may be dirty when invoking action. APPLIES_TO_NO_RECORDS|APPLIES_TO_SINGLE_RECORD scopes for adding/modifying */
    public $fields = [];

    /** @var bool Atomic action will automatically begin transaction before and commit it after completing. */
    public $atomic = true;

    protected function init(): void
    {
        $this->init_();
    }

    /**
     * Attempt to execute callback of the action.
     *
     * @param mixed ...$args
     *
     * @return mixed
     */
    public function execute(...$args)
    {
        // todo - ACL tests must allow
        try {
            $this->validateBeforeExecute();

            $run = function () use ($args) {
                if ($this->callback === null) {
                    $fx = [$this->getOwner(), $this->short_name];
                } elseif (is_string($this->callback)) {
                    $fx = [$this->getOwner(), $this->callback];
                } else {
                    array_unshift($args, $this->getOwner());
                    $fx = $this->callback;
                }

                return $fx(...$args);
            };

            if ($this->atomic) {
                return $this->getOwner()->atomic($run);
            }

            return $run();
        } catch (Exception $e) {
            $e->addMoreInfo('action', $this);

            throw $e;
        }
    }

    protected function validateBeforeExecute(): void
    {
        if ($this->enabled === false || ($this->enabled instanceof \Closure && ($this->enabled)($this->getOwner()) === false)) {
            throw new Exception('This action is disabled');
        }

        // Verify that model fields wouldn't be too dirty
        if (is_array($this->fields)) {
            $tooDirty = array_diff(array_keys($this->getOwner()->dirty), $this->fields);

            if ($tooDirty) {
                throw (new Exception('Calling user action on a Model with dirty fields that are not allowed by this action.'))
                    ->addMoreInfo('too_dirty', $tooDirty)
                    ->addMoreInfo('dirty', array_keys($this->getOwner()->dirty))
                    ->addMoreInfo('permitted', $this->fields);
            }
        } elseif (!is_bool($this->fields)) {
            throw (new Exception('Argument `fields` for the user action must be either array or boolean.'))
                ->addMoreInfo('fields', $this->fields);
        }

        // Verify some records scope cases
        switch ($this->appliesTo) {
            case self::APPLIES_TO_NO_RECORDS:
                if ($this->getOwner()->loaded()) {
                    throw (new Exception('This user action can be executed on non-existing record only.'))
                        ->addMoreInfo('id', $this->getOwner()->getId());
                }

                break;
            case self::APPLIES_TO_SINGLE_RECORD:
                if (!$this->getOwner()->loaded()) {
                    throw new Exception('This user action requires you to load existing record first.');
                }

                break;
        }
    }

    /**
     * Identical to Execute but display a preview of what will happen.
     *
     * @param mixed ...$args
     *
     * @return mixed
     */
    public function preview(...$args)
    {
        if ($this->preview === null) {
            throw new Exception('You must specify preview callback explicitly');
        } elseif (is_string($this->preview)) {
            $fx = \Closure::fromCallable([$this->getOwner(), $this->preview]);
        } else {
            array_unshift($args, $this->getOwner());
            $fx = $this->preview;
        }

        return $fx(...$args);
    }

    /**
     * Get description of this current action in a user-understandable language.
     */
    public function getDescription(): string
    {
        if ($this->description instanceof \Closure) {
            return call_user_func($this->description, $this);
        }

        return $this->description ?? $this->getCaption() . ' ' . $this->getOwner()->getModelCaption();
    }

    /**
     * Return confirmation message for action.
     */
    public function getConfirmation()
    {
        if ($this->confirmation instanceof \Closure) {
            return call_user_func($this->confirmation, $this);
        } elseif ($this->confirmation === true) {
            $confirmation = 'Are you sure you wish to execute ';
            $confirmation .= $this->getCaption();
            $confirmation .= $this->getOwner()->getTitle() ? ' using ' . $this->getOwner()->getTitle() : '';
            $confirmation .= '?';

            return $confirmation;
        }

        return $this->confirmation;
    }

    /**
     * Return model associate with this action.
     */
    public function getModel(): Model
    {
        return $this->getOwner();
    }

    public function getCaption(): string
    {
        return $this->caption ?? ucwords(str_replace('_', ' ', $this->short_name));
    }
}
