<?php

namespace App\Exceptions;

use RuntimeException;

/** Pesanan tidak bisa dibuat (stok habis, jadwal basi, dll). Pesannya aman ditampilkan ke pembeli. */
class OrderException extends RuntimeException
{
    public function __construct(string $message, public string $target = 'cart')
    {
        parent::__construct($message);
    }
}