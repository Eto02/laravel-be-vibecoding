<?php

namespace App\Exceptions\User;

use RuntimeException;

class PhoneAlreadyTakenException extends RuntimeException
{
    public function __construct(string $phone)
    {
        parent::__construct("Phone number [{$phone}] is already registered to another account.");
    }
}
