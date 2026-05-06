<?php

namespace App\Exceptions\Merchant;

use RuntimeException;

class AlreadyFollowingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You are already following this store.');
    }
}
