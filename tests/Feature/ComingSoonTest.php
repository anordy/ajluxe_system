<?php

namespace Tests\Feature;

use App\Support\Launch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComingSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_coming_soon_until_launch_then_the_shop(): void
    {
        $this->seed();
        config(['ajluxe.launch_at' => '2026-11-27 17:00']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-27 16:59:00', 'Africa/Dar_es_Salaam'));

        $this->get('/')->assertOk()->assertSee('exciting new things')->assertSee('saa 11 jioni')->assertDontSee('Shop by category');
        $this->get(route('shop.catalog'))->assertSee('exciting new things');

        // staff preview link opens the real site in that browser
        $this->get('/?preview=wrong')->assertSee('exciting new things');
        $r = $this->get('/?preview='.Launch::previewKey())->assertRedirect();
        $this->withCookie('ajl_preview', Launch::previewKey())->get('/')->assertSee('Shop by category');

        // 17:00: the website opens by itself
        $this->travelTo(\Carbon\Carbon::parse('2026-11-27 17:00:01', 'Africa/Dar_es_Salaam'));
        $this->get('/')->assertOk()->assertSee('Shop by category')->assertDontSee('exciting new things');
    }
}
