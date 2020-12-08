<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

use Atk4\Data\Model;

class Payment extends Document
{
    /** @var Model\Join */
    public $j_payment;

    protected function init(): void
    {
        parent::init();

        $this->addCondition('doc_type', 'payment');

        $this->j_payment = $this->join('payment.document_id');

        $this->j_payment->addField('cheque_no');
        $this->j_payment->hasOne('account_id', new Account());

        $this->j_payment->addField('misc_payment', ['type' => 'bool']);
    }
}
