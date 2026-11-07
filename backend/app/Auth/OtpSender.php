<?php

namespace App\Auth;

interface OtpSender
{
    public function send(string $phone, string $code): void;
}
