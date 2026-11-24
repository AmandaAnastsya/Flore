<?php

return [
    'payment' => [
        'bank' => env('PAYMENT_BANK', 'BCA'),
        'account_number' => env('PAYMENT_ACCOUNT_NUMBER', '1234567890'),
        'account_name' => env('PAYMENT_ACCOUNT_NAME', 'Floré Florist'),
    ],

    // Akun admin awal (database/seeders/AdminSeeder). Password WAJIB diisi di .env untuk production.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin Floré'),
        'email' => env('ADMIN_EMAIL', 'admin@flore.test'),
        'phone' => env('ADMIN_PHONE', '081234567890'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
