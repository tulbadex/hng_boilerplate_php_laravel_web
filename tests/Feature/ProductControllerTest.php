<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_products_by_name()
    {
        $product = Product::factory()->create(['name' => 'Test Product']);

        $response = $this->get('/api/v1/products/search?product_name=Test');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Product']);
    }

    public function test_search_products_by_category()
    {
        $product = Product::factory()->create([
            'name' => 'Test Product'
        ]);

        $category = Category::factory()->create([
            'name' => 'Test Category'
        ]);

        $product->categories()->attach($category->id);

        $response = $this->get('/api/v1/products/search?product_name=Test&category=Test Category');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Product']);
    }

    public function test_search_products_by_price_range()
    {
        $product = Product::factory()->create(['name' => 'Test Product', 'price' => 150]);

        $response = $this->get('/api/v1/products/search?product_name=Test&minPrice=100&maxPrice=200');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Product']);
    }

    public function test_search_returns_empty_for_nonexistent_name()
    {
        $response = $this->get('/api/v1/products/search?product_name=Nonexistent');

        $response->assertStatus(404);
        $response->assertJson([
            'type' => "Not Found",
            'title' => "No products found",
            'status' => 404,
            "detail" => "No products match the search criteria."
        ]);
    }

    public function test_search_validation_error()
    {
        $response = $this->get('/api/v1/products/search');

        $response->assertStatus(400);
        $response->assertJson([
            'type' => "Validation Error",
            'title' => "Invalid parameters provided",
            'status' => 400,
            "detail" => "There were validation errors with the request parameters.",
            'errors' => [
                "product_name" => [
                    "The product name field is required."
                ]
            ]
        ]);
    }
}
