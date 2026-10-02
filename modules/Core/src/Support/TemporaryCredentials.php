<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Models\User;

/**
 * A user and the temporary password generated for them. The password is
 * shown once to the administrator and never stored in clear text.
 */
final readonly class TemporaryCredentials
{
    public function __construct(
        public User $user,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
