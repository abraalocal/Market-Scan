<?php

namespace App\Http\Controllers\Api;

use App\Auth\OtpSender;
use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\Supplier;
use App\Models\User;
use App\Support\JoinCode;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** FR-01: mobile number + 6-digit code. Sales staff must be on the team; owners are created on first verify. */
class AuthController extends Controller
{
    public function requestOtp(Request $request, OtpSender $sender): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'role' => ['required', 'in:field_sales,owner'],
            'invite' => ['nullable', 'string', 'max:64'],
        ]);
        $phone = $this->phone($data['phone']);
        $cfg = config('marketscan.otp');

        $user = User::where('phone', $phone)->first();
        if ($data['role'] === 'field_sales' && (! $user || ! $user->isStaff())) {
            throw ValidationException::withMessages(['phone' => 'This number is not on the Abraa sales team. Ask your admin to add it.']);
        }
        if ($data['role'] === 'owner' && $user && ! $user->isOwner()) {
            throw ValidationException::withMessages(['phone' => 'This number belongs to the Abraa team. Log in as Abraa sales team.']);
        }

        $key = 'otp:'.$phone;
        if (RateLimiter::tooManyAttempts($key, $cfg['per_phone_limit'])) {
            return response()->json(['message' => 'Too many codes requested. Try again in a few minutes.', 'retry_after' => RateLimiter::availableIn($key)], 429);
        }
        RateLimiter::hit($key, $cfg['window_seconds']);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create(['phone' => $phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addSeconds($cfg['ttl_seconds'])]);
        Cache::put('otp-intent:'.$phone, ['role' => $data['role'], 'invite' => $data['invite'] ?? null], $cfg['window_seconds']);
        $sender->send($phone, $code);

        return response()->json(['sent' => true, 'expires_in' => $cfg['ttl_seconds']]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32'], 'code' => ['required', 'string']]);
        $phone = $this->phone($data['phone']);
        $cfg = config('marketscan.otp');
        $code = trim($data['code']);
        $devCode = app()->environment('local') && hash_equals($cfg['dev_code'], $code);

        $otp = OtpCode::where('phone', $phone)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $devCode) {
            if (! $otp) {
                throw ValidationException::withMessages(['code' => 'The code has expired. Ask for a new one.']);
            }
            if ($otp->attempts >= $cfg['max_attempts']) {
                throw ValidationException::withMessages(['code' => 'Too many wrong tries. Ask for a new code.']);
            }
            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');
                throw ValidationException::withMessages(['code' => 'Wrong code.']);
            }
        }

        $intent = Cache::get('otp-intent:'.$phone, []);
        $user = User::where('phone', $phone)->first();
        if (! $user) {
            if (($intent['role'] ?? null) !== 'owner') {
                throw ValidationException::withMessages(['code' => 'Ask for a code first.']);
            }
            $user = User::create(['name' => 'Shop owner', 'phone' => $phone, 'role' => 'owner']);
        }
        if ($user->isOwner() && ! $user->supplier_id && ($sid = JoinCode::supplierId($intent['invite'] ?? null)) && Supplier::whereKey($sid)->exists()) {
            $user->update(['supplier_id' => $sid]);
        }
        $otp?->update(['used_at' => now()]);
        Cache::forget('otp-intent:'.$phone);

        return response()->json(['token' => $user->createToken('capture-app')->plainTextToken, 'user' => $user->toApi()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->toApi());
    }

    private function phone(string $raw): string
    {
        return Phone::normalize($raw) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number.']);
    }
}
