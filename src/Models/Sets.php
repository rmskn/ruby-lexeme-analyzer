<?php

declare(strict_types=1);

namespace App\Models;

class Sets
{
    /**
     * @param array<int, string> $L
     * @param array<int, string> $D
     * @param array<int, string> $P
     * @param array<int, string> $E
     */
    public function __construct(
        public array $L,
        public array $D,
        public array $P,
        public array $E
    ) {
    }
}
