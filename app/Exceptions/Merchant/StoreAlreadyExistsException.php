<?php

namespace App\Exceptions\Merchant;

use RuntimeException;

class StoreAlreadyExistsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You already have a store.');
    }
}
