<?php

namespace App\Exceptions;

use RuntimeException;

class AstAccountForbiddenException extends RuntimeException
{
    public function __construct(public readonly string $accountNumber)
    {
        parent::__construct('That account number is not linked to your profile.');
    }
}
