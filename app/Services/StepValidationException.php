<?php

namespace App\Services;

use RuntimeException;

/** A section of the application form failed validation. */
class StepValidationException extends RuntimeException
{
    /** @param array<string, string[]> $errors */
    public function __construct(public readonly int $step, public readonly array $errors)
    {
        parent::__construct('Section ' . $step . ' of the application is incomplete.');
    }
}
