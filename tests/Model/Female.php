<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

class Female extends Person
{
    protected function init(): void
    {
        parent::init();
        $this->addCondition('gender', 'F');
    }
}
