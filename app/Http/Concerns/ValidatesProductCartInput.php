<?php

namespace App\Http\Concerns;

use App\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ValidatesProductCartInput
{
    protected function validateAddToCart(Request $request, Product $product, bool $enforceStockLimit = false): array
    {
        $rules = [
            'remark' => ['nullable', 'max:200'],
        ];
        $customMessages = [];

        if ($product->sell_in === Product::SELL_IN_QTY_BILL_WEIGHT) {
            $rules['quantity'] = ['required', 'numeric', 'min:0.001'];
            $rules['weight'] = ['nullable', 'numeric', 'min:0'];
            $customMessages['quantity.required'] = 'The quantity is required';
        } elseif ($product->sell_in === Product::SELL_IN_QTY) {
            $rules['quantity'] = ['required', 'numeric', 'min:0.001'];
            $customMessages['quantity.required'] = 'The quantity is required';
        } else {
            $rules['weight'] = ['required', 'numeric', 'min:0.001'];
            $customMessages['weight.required'] = 'The weight is required';
        }

        $customAttributes = [];
        $productOption = Product::getOption($product->id, true);
        foreach ($productOption['product_option'] as $option => $optionItems) {
            $rules['product_option.'.$option] = [
                $productOption['product_option_mandatory'][$option] ? 'required' : 'nullable',
                'in:'.implode(',', $optionItems),
            ];
            $customAttributes['product_option.'.$option] = $option;
        }

        try {
            $data = $request->validate($rules, $customMessages, $customAttributes);
        } catch (ValidationException $err) {
            return [
                'error' => true,
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }

        $requested = $product->stockCheckAmount(
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            isset($data['weight']) ? (float) $data['weight'] : null
        );
        $stock = app(StockService::class)->getOrCreateStock($product->id);

        if ($requested <= 0) {
            $field = match ($product->sell_in) {
                Product::SELL_IN_QTY, Product::SELL_IN_QTY_BILL_WEIGHT => 'quantity',
                default => 'weight',
            };

            throw ValidationException::withMessages([
                $field => 'Please enter a valid amount.',
            ]);
        }

        $available = Product::availableStockAmount($product, $stock);
        if ($enforceStockLimit && $requested > $available) {
            $field = $product->requiresQuantityInput() ? 'quantity' : 'weight';
            throw ValidationException::withMessages([
                $field => 'Only '.Product::formatStorefrontStockLabel(
                    $product,
                    (float) $stock->quantity,
                    (float) ($stock->weight ?? 0),
                    $product->uom_name ?? 'KG'
                ).' available.',
            ]);
        }

        return $data;
    }
}
