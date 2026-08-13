<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(GetTransactionsRequest $request)
    {
        $transactions = Transaction::with(['customer', 'items'])
            ->search($request->search)
            ->latest()
            ->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($transactions, TransactionResource::class),
            'Transactions list'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $items = $validated['items'];

            $subtotal = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new Exception("Product with ID {$item['product_id']} not found");
                }

                if (!$product || $product->stock < $item['quantity']) {
                    throw new Exception("Insufficient stock for product: {$product->name}");
                }

                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;



                $itemsData [] = [
                    'product_id' => $product->id,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemSubtotal,
                    'model' => $product
                ];
            }

            $tax = $subtotal * 0.11; // 11% tax
            $total = $subtotal + $tax;

            $code = 'TRX-' . time() . '-' . rand(1000, 9999);

            $transaction = Transaction::create([
                'code' => $code,
                'customer_id' => $request->customer_id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);

            foreach ($itemsData as $data) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $data['product_id'],
                    'price' => $data['price'],
                    'quantity' => $data['quantity'],
                    'subtotal' => $data['subtotal'],
                ]);

                $data['model']->decrement('stock', $data['quantity']);
            }

            DB::commit();

            return ApiResponse::success(
                new TransactionResource($transaction->load(['customer', 'items.product'])),
                'Transaction created successfully',
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponse::error(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = Transaction::with(['customer', 'items.product'])->find($id);

        if (!$transaction) {
            return ApiResponse::error(
                'Transaction not found',
                Response::HTTP_NOT_FOUND
            );
        }

        return ApiResponse::success(
            new TransactionResource($transaction),
            'Transaction details'
        );
    }
}
