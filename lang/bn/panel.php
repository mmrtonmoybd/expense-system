<?php

declare(strict_types=1);

return [
    'groups' => [
        'General' => 'সাধারণ',
        'Sales' => 'বিক্রয়',
        'Customers' => 'গ্রাহক',
        'Inventory' => 'মজুদ',
        'Expenses' => 'ব্যয়',
        'Payroll' => 'বেতন',
        'Reports' => 'প্রতিবেদন',
        'Administration' => 'ব্যবস্থাপনা',
    ],
    'resources' => [
        'sale' => ['singular' => 'বিক্রয়', 'plural' => 'বিক্রয়সমূহ'],
        'usd_purchase' => ['singular' => 'ডলার ক্রয়', 'plural' => 'ডলার ক্রয়সমূহ'],
        'invoice' => ['singular' => 'চালান', 'plural' => 'চালানসমূহ'],
        'inventory_transaction' => ['singular' => 'মজুদ লেনদেন', 'plural' => 'মজুদ লেনদেনসমূহ'],
        'customer' => ['singular' => 'গ্রাহক', 'plural' => 'গ্রাহকগণ'],
        'platform' => ['singular' => 'প্ল্যাটফর্ম', 'plural' => 'প্ল্যাটফর্মসমূহ'],
        'expense' => ['singular' => 'ব্যয়', 'plural' => 'ব্যয়সমূহ'],
        'expense_category' => ['singular' => 'ব্যয়ের খাত', 'plural' => 'ব্যয়ের খাতসমূহ'],
        'employee' => ['singular' => 'কর্মচারী', 'plural' => 'কর্মচারীগণ'],
        'payroll' => ['singular' => 'বেতন', 'plural' => 'বেতনসমূহ'],
        'user' => ['singular' => 'ব্যবহারকারী', 'plural' => 'ব্যবহারকারীগণ'],
        'audit_log' => ['singular' => 'নিরীক্ষা লগ', 'plural' => 'নিরীক্ষা লগসমূহ'],
    ],
    'pages' => [
        'dashboard' => 'ড্যাশবোর্ড',
        'pending_approvals' => 'অনুমোদনের অপেক্ষায়',
        'inventory_adjustment' => 'মজুদ সমন্বয়',
        'reports' => 'প্রতিবেদন',
        'business_analytics' => 'ব্যবসায়িক বিশ্লেষণ',
        'statements' => 'হিসাব বিবরণী',
        'profit_predictor' => 'মুনাফা পূর্বাভাস',
        'settings' => 'সেটিংস',
    ],
    'language' => [
        'label' => 'ভাষা',
        'switch' => 'ভাষা পরিবর্তন',
    ],
];
