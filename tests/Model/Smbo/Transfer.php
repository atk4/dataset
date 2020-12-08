<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

class Transfer extends Payment
{
    public $detached = false;

    public $other_leg_creation;

    protected function init(): void
    {
        parent::init();

        $this->j_payment->hasOne('transfer_document_id', new self());

        // only used to create / destroy trasfer legs
        if (!$this->detached) {
            $this->addCondition('transfer_document_id', 'not', null);
        }

        $this->addField('destination_account_id', ['never_persist' => true]);

        $this->onHookShort(self::HOOK_BEFORE_SAVE, function () {
            // only for new records and when destination_account_id is set
            if ($this->get('destination_account_id') && !$this->getId()) {
                // In this section we test if "clone" works ok

                $this->other_leg_creation = $m2 = clone $this;
                $m2->set('account_id', $m2->get('destination_account_id'));
                $m2->set('amount', -$m2->get('amount'));

                $m2->_unset('destination_account_id');

                /*/

                // If clone is not working, then this is a current work-around

                $this->other_leg_creation = $m2 = new Transfer($this->persistence);
                $m2->set($this->get());
                $m2->unset('destination_account_id');
                $m2->set('account_id', $this->get('destination_account_id'));
                $m2->set('amount', -$m2->get('amount')); // neagtive amount

                // */

                $m2->reload_after_save = false; // avoid check

                $this->set('transfer_document_id', $m2->save()->getId());
            }
        });

        $this->onHookShort(self::HOOK_AFTER_SAVE, function () {
            if ($this->other_leg_creation) {
                $this->other_leg_creation->set('transfer_document_id', $this->getId())->save();
            }
            $this->other_leg_creation = null;
        });
    }
}
