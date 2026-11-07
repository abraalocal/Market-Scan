<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tests never reach abraa.com, GHL, Anthropic or a search API: every outside call is faked.
        Http::preventStrayRequests();
    }
}
