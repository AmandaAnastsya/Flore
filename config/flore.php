<?php

return [
    'payment' => [
        'bank' => env('PAYMENT_BANK', 'BCA'),
        'account_number' => env('PAYMENT_ACCOUNT_NUMBER', '1234567890'),
        'account_name' => env('PAYMENT_ACCOUNT_NAME', 'Floré Florist'),
        'timeout_hours' => 24,   // pesanan belum dibayar setelah sekian jam dibatalkan otomatis (orders:cancel-unpaid)
    ],

    // Titik berangkat kurir (dipakai untuk tautan rute Google Maps di halaman Logistik)
    'studio' => [
        'address' => env('STUDIO_ADDRESS', 'Floré Studio, Jakarta Selatan'),
    ],

    // Nilai di bawah ini adalah DEFAULT; admin bisa mengubahnya di Admin → Studio Settings (App\Services\StudioSettings)
    'store' => [
        'name' => env('STORE_NAME', 'Floré'),
        'phone' => env('STORE_PHONE'),
        'email' => env('STORE_EMAIL'),
    ],

    'delivery' => [
        'lead_hours' => 3,    // slot harus mulai minimal sekian jam dari sekarang
        'max_days' => 30,     // pesan maksimal sekian hari ke depan
    ],

    'reminders' => [
        'time' => '08:00',    // jam kirim email Smart Reminder setiap hari
    ],

    'inventory' => [
        'shelf_life_days' => 7,   // umur segar bawaan bunga potong
    ],

    // Akun admin awal (database/seeders/AdminSeeder). Password WAJIB diisi di .env untuk production.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin Floré'),
        'email' => env('ADMIN_EMAIL', 'admin@flore.test'),
        'phone' => env('ADMIN_PHONE', '081234567890'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
