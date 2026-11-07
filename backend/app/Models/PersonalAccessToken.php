<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/** Sanctum tokens in ms_personal_access_tokens: every table of this app has the ms_ prefix. */
class PersonalAccessToken extends SanctumToken
{
    protected $table = 'ms_personal_access_tokens';
}
