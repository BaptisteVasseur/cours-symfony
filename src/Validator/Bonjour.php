<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Bonjour extends Constraint
{
    public string $message = 'Pas de bonjour dans le titre !';
}
