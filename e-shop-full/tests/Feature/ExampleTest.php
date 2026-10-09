<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The API host sends visitors to the Nuxt frontend.
     */
    public function test_root_redirects_to_the_frontend(): void
    {
        $this->get('/')->assertRedirect(config('shopai.frontend_url'));
    }
}
