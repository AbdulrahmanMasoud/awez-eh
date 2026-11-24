<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches a real service by mistake fails instead of sending a message or a push.
        Http::preventStrayRequests();

        // Symfony's test requests claim `Accept-Language: en-us` unless told otherwise. Send none,
        // as a client that sets no language, so the API's default locale is what tests see.
        $this->withHeader('Accept-Language', '');
    }
}
