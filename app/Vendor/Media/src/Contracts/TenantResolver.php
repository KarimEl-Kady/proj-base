<?php

namespace Local\Media\Contracts;

interface TenantResolver
{
    public function enabled(): bool;

    public function id(): int|string|null;
}
