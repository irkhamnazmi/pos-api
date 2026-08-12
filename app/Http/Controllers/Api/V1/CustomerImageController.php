<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProductImageRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CustomerImageController extends Controller
{
    public function store(UploadProductImageRequest $request, string $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return ApiResponse::error(
                'Customer not found',
                Response::HTTP_NOT_FOUND
            );
        }


        if ($customer->image) {
            Storage::disk('public')->delete($customer->image);
        }

        $path = $request->file('image')->store('customers', 'public');
        $customer->update(['image' => $path]);

        return ApiResponse::success(
            new CustomerResource($customer),
            'Customer image uploaded'
        );
    }
}