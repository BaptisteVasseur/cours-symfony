<?php

namespace App\Validator;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class InsultValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        /* @var Insult $constraint */

        if (null === $value || '' === $value) {
            return;
        }

        $insults = ['idiot', 'stupid', 'dumb', 'fool', 'jerk', 'fdp']; // List of insults to check against

        foreach ($insults as $insult) {
            if (str_contains(strtolower($value), $insult)) {

                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation()
                ;
                break; // Stop checking after the first insult is found
            }
        }
    }
}
