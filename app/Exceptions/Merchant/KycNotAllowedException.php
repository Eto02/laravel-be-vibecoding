<?php

namespace App\Exceptions\Merchant;

use RuntimeException;

class KycNotAllowedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('KYC documents cannot be uploaded at this time.');
    }
}
