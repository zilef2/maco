<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_locale_to_english_in_session(): void
    {
        $response = $this->post(route('locale.update', 'en'));

        $response->assertSessionHas('locale', 'en');
        $response->assertRedirect();
    }

    public function test_it_updates_locale_to_spanish_in_session(): void
    {
        $response = $this->post(route('locale.update', 'es'));

        $response->assertSessionHas('locale', 'es');
        $response->assertRedirect();
    }
}
