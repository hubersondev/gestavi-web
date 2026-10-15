<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Clients de l'organisation : ceux rattachés aux fermes du membre et ceux communs à l'organisation.
 */
class CustomerController extends ApiController
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);

        $query = Customer::query()
            ->where('customers.organisation_id', $organisation->id)
            ->where('customers.is_active', true)
            ->orderBy('customers.name');

        if ($request->filled('since')) {
            $query->where('customers.updated_at', '>=', Carbon::parse($request->string('since')->toString()));
        }

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(customers.name) LIKE ?', ['%'.mb_strtolower($request->string('search')->toString()).'%']);
        }

        $this->scopeToFarms($query, $membership, 'customers.farm_id');

        return CustomerResource::collection($query->paginate($this->perPage($request, 100, 500)));
    }
}
