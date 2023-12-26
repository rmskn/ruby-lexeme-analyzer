<?php

declare(strict_types=1);

namespace App\Models;

class TokenAddress
{
    public function __construct(
        public int $table,
        public int $number
    ) {
    }
}
