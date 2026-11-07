<?php

use App\Auth\OtpSender;
use App\Models\OtpCode;
use App\Models\Supplier;
use App\Models\User;
use App\Support\JoinCode;

beforeEach(function () {
    $this->otp = new class implements OtpSender
    {
        public array $sent = [];

        public function send(string $phone, string $code): void
        {
            $this->sent[$phone] = $code;
        }
    };
    app()->instance(OtpSender::class, $this->otp);
});

it('logs a team member in with a hashed 6-digit code (FR-01)', function () {
    $u = User::factory()->create(['phone' => '+971501234567', 'name' => 'Sara']);
    $this->postJson('/api/auth/otp', ['phone' => '050 123 4567', 'role' => 'field_sales'])->assertOk();
    $code = $this->otp->sent['+971501234567'];
    expect($code)->toMatch('/^\d{6}$/')->and(OtpCode::first()->code_hash)->not->toBe($code);

    $token = $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => $code])->assertOk()->assertJsonPath('user.id', $u->id)->json('token');
    $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('name', 'Sara');
});

it('lets admins log in through the sales team option', function () {
    User::factory()->admin()->create(['phone' => '+971500000001']);
    $this->postJson('/api/auth/otp', ['phone' => '0500000001', 'role' => 'field_sales'])->assertOk();
});

it('refuses sales login for numbers not on the team', function () {
    $this->postJson('/api/auth/otp', ['phone' => '0509999999', 'role' => 'field_sales'])->assertStatus(422);
});

it('rate limits to 3 codes per phone per 10 minutes', function () {
    User::factory()->create(['phone' => '+971501234567']);
    foreach (range(1, 3) as $_) {
        $this->postJson('/api/auth/otp', ['phone' => '0501234567', 'role' => 'field_sales'])->assertOk();
    }
    $this->postJson('/api/auth/otp', ['phone' => '0501234567', 'role' => 'field_sales'])->assertStatus(429);
    $this->travel(11)->minutes();
    $this->postJson('/api/auth/otp', ['phone' => '0501234567', 'role' => 'field_sales'])->assertOk();
});

it('allows 5 wrong tries, rejects expired and used codes', function () {
    User::factory()->create(['phone' => '+971501234567']);
    $this->postJson('/api/auth/otp', ['phone' => '0501234567', 'role' => 'field_sales']);
    $right = $this->otp->sent['+971501234567'];
    $wrong = $right === '000000' ? '111111' : '000000';
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => $wrong])->assertStatus(422);
    }
    $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => $right])->assertStatus(422);

    $this->postJson('/api/auth/otp', ['phone' => '0501234567', 'role' => 'field_sales']);
    $code = $this->otp->sent['+971501234567'];
    $this->travel(6)->minutes();
    $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => $code])->assertStatus(422);
    $this->travelBack();
    $this->postJson('/api/auth/otp', ['phone' => '0501234567', 'role' => 'field_sales']);
    $code = $this->otp->sent['+971501234567'];
    $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => $code])->assertOk();
    $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => $code])->assertStatus(422);
});

it('creates an owner on first verify and links the invited shop', function () {
    $shop = Supplier::factory()->create();
    $this->postJson('/api/auth/otp', ['phone' => '0551112233', 'role' => 'owner', 'invite' => JoinCode::for($shop->id)])->assertOk();
    expect(User::count())->toBe(0);
    $this->postJson('/api/auth/verify', ['phone' => '0551112233', 'code' => $this->otp->sent['+971551112233']])
        ->assertOk()->assertJsonPath('user.role', 'owner')->assertJsonPath('user.supplier_id', $shop->id);
});

it('ignores a forged invite', function () {
    $shop = Supplier::factory()->create();
    $this->postJson('/api/auth/otp', ['phone' => '0551112233', 'role' => 'owner', 'invite' => base_convert((string) $shop->id, 10, 36).'-AAAAAA']);
    $this->postJson('/api/auth/verify', ['phone' => '0551112233', 'code' => $this->otp->sent['+971551112233']])->assertJsonPath('user.supplier_id', null);
});

it('accepts 123456 only when APP_ENV=local', function () {
    User::factory()->create(['phone' => '+971501234567']);
    $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => '123456'])->assertStatus(422);
    app()->detectEnvironment(fn () => 'local');
    $this->postJson('/api/auth/verify', ['phone' => '0501234567', 'code' => '123456'])->assertOk();
});

it('answers 401 JSON without a token', function () {
    $this->get('/api/me')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
});
