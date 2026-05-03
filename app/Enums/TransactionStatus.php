<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Paid    = 'paid';
    case Expired = 'expired';
}
