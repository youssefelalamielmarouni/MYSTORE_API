<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::paginate(15));
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'images.*' => 'sometimes|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::create($validator->validated());

        // handle images (multipart form-data)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $idx => $file) {
                $path = $file->store('products', 'public');
                $product->images()->create(['path' => $path, 'position' => $idx]);
            }
            $product->load('images');
        }

        return response()->json($product->load('images'), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'images.*' => 'sometimes|image|max:5120',
            'remove_image_ids' => 'sometimes|array',
            'remove_image_ids.*' => 'integer|exists:product_images,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product->update($validator->validated());

        // remove images if requested
        if ($request->filled('remove_image_ids')) {
            $ids = $request->input('remove_image_ids', []);
            $images = ProductImage::whereIn('id', $ids)->where('product_id', $product->id)->get();
            foreach ($images as $img) {
                Storage::disk('public')->delete($img->path);
                $img->delete();
            }
        }

        // add new images if provided
        if ($request->hasFile('images')) {
            $currentMax = $product->images()->max('position');
            $start = is_null($currentMax) ? 0 : $currentMax + 1;
            foreach ($request->file('images') as $idx => $file) {
                $path = $file->store('products', 'public');
                $product->images()->create(['path' => $path, 'position' => $start + $idx]);
            }
        }

        return response()->json($product->load('images'), 200);
    }

    public function destroy(Product $product)
    {
        // delete images from storage
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->path);
        }
        $product->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }

    // Upload images to existing product (admin)
    public function uploadImages(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'images.*' => 'required|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $currentMax = $product->images()->max('position');
        $start = is_null($currentMax) ? 0 : $currentMax + 1;

        foreach ($request->file('images') as $idx => $file) {
            $path = $file->store('products', 'public');
            $product->images()->create(['path' => $path, 'position' => $start + $idx]);
        }

        return response()->json($product->load('images'), 201);
    }

    // Delete a specific product image
    public function destroyImage(Product $product, ProductImage $image)
    {
        if ($image->product_id !== $product->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Image deleted']);
    }

    // Get all products with pagination
    public function allProducts(Request $request)
    {
        return response()->json(Product::paginate(15));
    }

    // Get newest products (use `?limit=` query param)
    public function newestProducts(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $products = Product::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
        return response()->json($products);
    }

    // Get most sold products (use `?limit=` query param)
    public function mostSoldProducts(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $products = Product::withSum('orderItems', 'quantity')
            ->orderBy('order_items_sum_quantity', 'desc')
            ->limit($limit)
            ->get();
        return response()->json($products);
    }
}
