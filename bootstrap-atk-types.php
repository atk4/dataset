<?php

declare(strict_types=1);

namespace Atk4\Data;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

// TODO for types to DBAL migration, might be removed later

class AtkTypeMoney extends Type
{
    public function getName(): string
    {
        return 'money';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType('float' /* Types::FLOAT supported from DBAL 2.10.x */)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

class AtkTypePassword extends Type
{
    public function getName(): string
    {
        return 'password';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType('string' /* Types::STRING supported from DBAL 2.10.x */)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

Type::addType('money', AtkTypeMoney::class);
Type::addType('password', AtkTypePassword::class);
