<?php

declare(strict_types=1);

return [
    'groups' => [
        'General' => 'General',
        'Sales' => 'Sales',
        'Customers' => 'Customers',
        'Inventory' => 'Inventory',
        'Expenses' => 'Expenses',
        'Payroll' => 'Payroll',
        'Reports' => 'Reports',
        'Administration' => 'Administration',
    ],
    'resources' => [
        'sale' => ['singular' => 'Sale', 'plural' => 'Sales'],
        'usd_purchase' => ['singular' => 'USD Purchase', 'plural' => 'USD Purchases'],
        'invoice' => ['singular' => 'Invoice', 'plural' => 'Invoices'],
        'inventory_transaction' => ['singular' => 'Inventory Transaction', 'plural' => 'Inventory Transactions'],
        'customer' => ['singular' => 'Customer', 'plural' => 'Customers'],
        'platform' => ['singular' => 'Platform', 'plural' => 'Platforms'],
        'expense' => ['singular' => 'Expense', 'plural' => 'Expenses'],
        'expense_category' => ['singular' => 'Expense Category', 'plural' => 'Expense Categories'],
        'employee' => ['singular' => 'Employee', 'plural' => 'Employees'],
        'payroll' => ['singular' => 'Payroll', 'plural' => 'Payrolls'],
        'user' => ['singular' => 'User', 'plural' => 'Users'],
        'audit_log' => ['singular' => 'Audit Log', 'plural' => 'Audit Logs'],
    ],
    'pages' => [
        'dashboard' => 'Dashboard',
        'pending_approvals' => 'Pending Approvals',
        'inventory_adjustment' => 'Inventory Adjustment',
        'reports' => 'Reports',
        'business_analytics' => 'Business Analytics',
        'statements' => 'Statements',
        'profit_predictor' => 'Profit Predictor',
        'settings' => 'Settings',
    ],
    'language' => [
        'label' => 'Language',
        'switch' => 'Change language',
    ],
];
