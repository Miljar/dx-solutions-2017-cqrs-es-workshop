<?php

declare(strict_types=1);

namespace Building\Domain\Constraint;

interface UserIsAllowed
{
    public function __invoke(string $username) : bool;
}
