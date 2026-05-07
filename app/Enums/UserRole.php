<?php

namespace App\Enums;

enum UserRole: string
{
    case Buyer    = 'buyer';
    case Merchant = 'merchant';
    case Admin    = 'admin';
}
