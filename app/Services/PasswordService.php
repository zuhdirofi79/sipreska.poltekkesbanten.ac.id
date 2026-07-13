<?php

namespace App\Services;

use Illuminate\Support\Str;

class PasswordService
{
    public function generateTemporaryPassword(int $length = 16): string
    {
        return Str::password($length, symbols: true);
    }
}
