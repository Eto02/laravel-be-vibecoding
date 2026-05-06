<?php

namespace App\Enums;

enum MerchantStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Suspended = 'suspended';
    case Banned    = 'banned';
}
