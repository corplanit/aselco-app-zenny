<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientAstBalanceException extends RuntimeException
{
    public function __construct(
        public readonly string $balance,
        public readonly string $requested,
    ) {
        parent::__construct('Not enough AST in your wallet for this amount.');
    }
}
