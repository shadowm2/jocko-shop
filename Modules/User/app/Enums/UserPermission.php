<?php

namespace Modules\User\Enums;

/**
 * What a role can be allowed to do. Everything else (orders, payments,
 * customers, the daily close) is open to all staff.
 */
enum UserPermission: string
{
    case ViewReports = 'reports.view';
    case ManagePurchasing = 'purchasing.manage';
    case ManageItems = 'items.manage';
    case ManageStock = 'stock.manage';
    case EditPayments = 'payments.edit';
    case ManageBackups = 'backups.manage';
    case ManageSettings = 'settings.manage';
    case ManageRoles = 'roles.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
