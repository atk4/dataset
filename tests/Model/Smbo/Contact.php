<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

class Contact extends \Atk4\Data\Model
{
    public $table = 'contact';

    protected function init(): void
    {
        parent::init();

        $this->addField('type', ['enum' => ['client', 'supplier']]);

        $this->addField('name');
    }
}
