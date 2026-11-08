<?php

return [
    'payment' => [
        'bank' => env('PAYMENT_BANK', 'BCA'),
        'account_number' => env('PAYMENT_ACCOUNT_NUMBER', '1234567890'),
        'account_name' => env('PAYMENT_ACCOUNT_NAME', 'Floré Florist'),
    ],
];