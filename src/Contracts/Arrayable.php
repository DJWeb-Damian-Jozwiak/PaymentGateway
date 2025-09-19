<?php

declare(strict_types=1);

namespace DjWeb\Payments\Contracts;

interface Arrayable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
