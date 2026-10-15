<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Organisation;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Catalogue des produits vendus par l'organisation.
 */
class ProductController extends ApiController
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $this->membership($request);

        $query = Product::query()
            ->where('organisation_id', $organisation->id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('since')) {
            $query->where('updated_at', '>=', Carbon::parse($request->string('since')->toString()));
        }

        return ProductResource::collection($query->get());
    }
}
