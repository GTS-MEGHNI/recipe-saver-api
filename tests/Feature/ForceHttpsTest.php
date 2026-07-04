<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class ForceHttpsTest extends TestCase
{
    public function test_generated_urls_use_https_when_force_https_is_enabled(): void
    {
        config(['recipes.force_https' => true]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/api/ping'));
    }

    public function test_generated_urls_stay_http_when_force_https_is_disabled(): void
    {
        config(['recipes.force_https' => false]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', url('/api/ping'));
    }
}
