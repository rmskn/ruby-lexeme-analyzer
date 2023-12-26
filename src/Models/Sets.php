<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Collection;

class Sets
{
    /**
     * @param Collection<int, string> $L
     * @param Collection<int, string> $D
     * @param Collection<int, string> $P
     * @param Collection<int, string> $E
     */
    public function __construct(
        public Collection $L,
        public Collection $D,
        public Collection $P,
        public Collection $E
    ) {
    }
}
