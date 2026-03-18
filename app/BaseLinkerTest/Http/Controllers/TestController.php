<?php

namespace App\BaseLinkerTest\Http\Controllers;

use App\BaseLinkerTest\Models\BlCategory;
use App\BaseLinkerTest\Models\BlProduct;
use Illuminate\Http\Request;

final class TestController
{
    public function index(Request $request)
    {
        $inventoryId = is_numeric($request->query('inventory_id')) ? (int)$request->query('inventory_id') : null;
        $categoryId = is_numeric($request->query('category_id')) ? (int)$request->query('category_id') : null;
        $view = in_array($request->query('view'), ['table', 'cards'], true) ? (string)$request->query('view') : 'cards';

        $categoriesQuery = BlCategory::query()->orderBy('parent_id')->orderBy('name');
        if ($inventoryId !== null) {
            $categoriesQuery->where('inventory_id', $inventoryId);
        }
        $categories = $categoriesQuery->get();

        $productsQuery = BlProduct::query()->orderBy('name');
        if ($inventoryId !== null) {
            $productsQuery->where('inventory_id', $inventoryId);
        }
        if ($categoryId !== null) {
            $productsQuery->where('category_id', $categoryId);
        }
        $products = $productsQuery->paginate(60)->withQueryString();

        return view('base_linker_test.test', [
            'inventory_id' => $inventoryId,
            'category_id' => $categoryId,
            'view_mode' => $view,
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    public function product(Request $request, int $inventoryId, int $productId)
    {
        $product = BlProduct::query()
            ->where('inventory_id', $inventoryId)
            ->where('id', $productId)
            ->firstOrFail();

        $category = null;
        if (!empty($product->category_id)) {
            $category = BlCategory::query()
                ->where('inventory_id', $inventoryId)
                ->where('id', (int)$product->category_id)
                ->first();
        }

        $tab = in_array($request->query('tab'), ['info', 'media', 'stock', 'prices', 'raw'], true)
            ? (string)$request->query('tab')
            : 'info';

        return view('base_linker_test.product', [
            'product' => $product,
            'category' => $category,
            'tab' => $tab,
        ]);
    }
}

