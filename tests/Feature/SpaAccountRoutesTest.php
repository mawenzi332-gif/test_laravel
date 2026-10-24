<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaAccountRoutesTest extends TestCase
{
    public function test_account_routes_and_password_reset_links_serve_the_spa_shell(): void
    {
        $this->withoutVite();

        foreach ([
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password/example-token',
            '/profile',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('id="app"', false);
        }
    }
}
