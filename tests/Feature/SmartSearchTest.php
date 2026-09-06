<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmartSearchTest extends TestCase
{
    public function test_smart_search_extracts_price_and_rating_filters(): void
    {
        $response = $this->postJson('/smart-search', ['q' => '4k monitor under 0.01 BTC rated 4.5']);
        $response->assertOk()
            ->assertJsonPath('filters.max_price', '0.01')
            ->assertJsonPath('filters.min_rating', '4.5');
    }

    public function test_smart_search_rejects_too_short_queries(): void
    {
        $this->postJson('/smart-search', ['q' => 'x'])->assertStatus(422);
    }
}
