<?php

namespace App\Models\Concerns;

use LogicException;

trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (): never {
            throw new LogicException('This record is append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('This record is append-only and cannot be deleted.');
        });
    }
}
