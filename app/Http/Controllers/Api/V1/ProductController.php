<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'minPrice' => 'nullable|numeric|min:0',
            'maxPrice' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:in_stock,out_of_stock,low_on_stock',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            return response()->json([
                'type' => 'Validation Error',
                'title' => 'Invalid parameters provided',
                'status' => 400,
                'detail' => 'There were validation errors with the request parameters.',
                'errors' => $validator->errors(),
            ], 400);
        }

        $query = Product::query();

        $query->where('name', 'LIKE', '%' . $request->product_name . '%');

        // Add category filter if provided
        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('name', $request->category);
            });
        }

        if ($request->filled('minPrice')) {
            $query->where('price', '>=', $request->minPrice);
        }

        if ($request->filled('maxPrice')) {
            $query->where('price', '<=', $request->maxPrice);
        }

        if ($request->filled('status')) {
            $query->whereHas('productsVariant', function ($q) use ($request) {
                $q->where('stock_status', $request->status);
            });
        }


        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $products = $query->with(['categories'])
            ->paginate($limit, ['*'], 'page', $page);

        // If no products are found, return a 404 response
        if ($products->isEmpty()) {
            return response()->json([
                'type' => 'Not Found',
                'title' => 'No products found',
                'status' => 404,
                'detail' => 'No products match the search criteria.',
            ], 404);
        }

        // Map the products to the desired response format
        $transformedProducts = $products->map(function ($product) {
            return [
                'id' => $product->product_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'category' => $product->categories->isNotEmpty() ? $product->categories->map->name : [],
                'created_at' => $product->created_at->toIso8601String(),
                'updated_at' => $product->updated_at->toIso8601String(),
            ];
        });

        return response()->json($transformedProducts, 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Validate pagination parameters
            $request->validate([
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1',
            ]);

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            // Calculate offset
            $offset = ($page - 1) * $limit;

            $products = Product::select('name', 'price')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Get total product count
            $totalItems = Product::count();
            $totalPages = ceil($totalItems / $limit);

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'products' => $products,
                'pagination' => [
                    'totalItems' => $totalItems,
                    'totalPages' => $totalPages,
                    'currentPage' => $page,
                ],
                'status_code' => 200,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'bad request',
                'message' => 'Invalid query params passed',
                'status_code' => 400,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductRequest $request)
    {
        $request->validated();

        $user = auth()->user();
        $request['slug'] = Str::slug($request['name']);
        $request['tags'] = " ";
        $request['imageUrl'] = " ";
        $product = $user->products()->create($request->all());

        return response()->json([
            'message' => 'Product created successfully',
            'status_code' => 201,
            'data' => [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'description' => $product->description,
            ]
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($productId)
    {
        if (!Auth::check()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You must be authenticated to delete a product.'
            ], 401);
        }

        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
                'message' => "The product with ID $productId does not exist."
            ], 404);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.'
        ], 200);
    }
}
