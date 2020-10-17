<?php

declare(strict_types=1);

namespace atk4\data\tests\Model\Smbo;

class Payment extends Document
{
    protected function init(): void
    {
        parent::init();

        $this->addCondition('doc_type', 'payment');

        $j_p = $this->j_payment = $this->join('payment.document_id');

        $j_p->addField('cheque_no');
        $j_p->hasOne('account_id', new Account());

        $j_p->addField('misc_payment', ['type' => 'boolean']);
    }
}
