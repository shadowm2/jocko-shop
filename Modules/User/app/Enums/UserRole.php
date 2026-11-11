<?php

namespace Modules\User\Enums;

/**
 * What a staff member may do. A manager can do everything; a cashier works the
 * counter (orders, payments, the day's close) but not the shop's money
 * reports, purchasing, prices or settings.
 */
enum UserRole: string
{
    case Manager = 'manager';
    case Cashier = 'cashier';
}
