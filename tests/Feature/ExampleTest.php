<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_with_wizard(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Craft Your Moment With Floré')
            ->assertSee('The Elegance Collection')
            ->assertSee('id="wizardModal"', false);
    }

    public function test_guest_wizard_link_opens_landing_with_modal(): void
    {
        $this->get('/wizard')->assertRedirect(route('landing', ['wizard' => 1]));
        $this->get('/?wizard=1')->assertSee('data-auto-open="1"', false);
    }
}
