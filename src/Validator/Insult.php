<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Insult extends Constraint
{
    public string $message = 'The value "{{ value }}" contains an insult.';
}
