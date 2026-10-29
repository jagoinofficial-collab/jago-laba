<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_foundation_read_page_is_available_without_database_access(): void
    {
        $response = $this->get('/foundation');

        $response->assertStatus(200);
        $response->assertSee('Jago Laba Laravel Foundation');
    }
}
