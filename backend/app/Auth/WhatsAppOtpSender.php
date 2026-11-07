<?php

namespace App\Auth;

use RuntimeException;

/** WhatsApp OTP: channel (GHL or WhatsApp Business API) still to be decided by Mostafa. Fails loudly until then. */
class WhatsAppOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        throw new RuntimeException('OTP_DRIVER=whatsapp is not wired yet. Use OTP_DRIVER=log.');
    }
}
