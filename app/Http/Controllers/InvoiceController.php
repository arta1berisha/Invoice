<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreInvoiceRequest;
use Illuminate\Validation\Rules\Exists;

class InvoiceController extends Controller
{
    public function generate(Request $request, Product $product)
    {
        //Collects $products by Id to an array
        $productIds = collect($request->input('products'))->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->get()->sortByDesc('prices');

        $invoices = [];

        //foreach loops through each product
        foreach ($products as $product) {
            // $quantity collects the requested input 
            $quantity = collect($request->input('products'))->firstWhere('product_id', $product->id)['quantity'] ?? 0;
            // Calculate the product's price, VAT, and total
            $price = $product->prices;
            $vatAmount = $price * $product->vat / 100;
            $discount = $product->discount;
            $remainingQuantity = $quantity;
            //while goes through products that remaining quantity is greater than 0
            while ($remainingQuantity > 0) {
               // $splitQuantity splits the quantity greater than 50, to 50 how many times its possible and then to the last one remaining quantity
                $splitQuantity = min(50, $remainingQuantity);
                // calculates the price with the split quantity
                $splitTotalPerProduct = ($price + $vatAmount - $discount) * $splitQuantity;
                // if the total price is greater than 500 while the quantity is 1, it will return an invoice with only that product or if a product id is greater than 500 alone, but more than 1 products are requested, return invoice for each quantity required
                if ($splitTotalPerProduct > 500 && $splitQuantity === 1) {
                    for ($i = 0; $i < $quantity; $i++) {
                        $invoices[] = [
                            'items' => [[
                                'product_id' => $product->id,
                                'name' => $product->name,
                                'quantity' => 1,
                                'price' => $price,
                                'vat' => $vatAmount,
                                'discount' => $discount,
                                'total' => $splitTotalPerProduct / $splitQuantity,
                            ]],
                            'total' => $splitTotalPerProduct / $splitQuantity,
                        ];
                    }
                    break;
                } else if($splitTotalPerProduct > 500 && $splitQuantity > 1) {
                    $currentInvoice = [
                        'items' => [[
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $splitQuantity,
                        'price' => $price,
                        'vat' => $vatAmount,
                        'discount' => $discount,
                        'total' => $splitTotalPerProduct,
                    ]],
                        'total' => $splitTotalPerProduct,
                    ]; 
                } else {
                    // else $currentInvoice will be created 
                    $currentInvoice = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $splitQuantity,
                        'price' => $price,
                        'vat' => $vatAmount,
                        'discount' => $discount,
                        'total' => $splitTotalPerProduct,
                    ];
                    //$addedToExistingInvoice is set to false and will come in handy later on to check if the invoice has already that same product 
                    $addedToExistingInvoice = false;
                    // foreach loop for $invoice 
                    foreach ($invoices as &$invoice) {
                        // two variables will be used to keep track of whether the current invoice contains an existing item that matches the product being added to the invoice, and if so, how many of that item are already in the invoice.
                        $existingItemIndex = null;
                        $existingItemCount = 0;
                        // another foreach loop that iterates over each item in the current invoice's 'items' array.
                        foreach ($invoice['items'] as $index => $item) {
                            //checks if the current item in the loop matches the product being added to the invoice by comparing their 'product_id' values. If they match, the code inside the if statement is executed.
                            if ($item['product_id'] == $product->id) {
                                // updates the variables initialized earlier to reflect that an existing item has been found and to track the quantity of that item that is already in the invoice.
                                $existingItemIndex = $index;
                                $existingItemCount += $item['quantity'];                          
                         }
                        }
                        // if an existing item has been found ($existingItemIndex is not null), the total quantity of the item in the invoice plus the quantity being added is less than or equal to 50
                        // and the total cost of the invoice plus the cost of the item being added is less than or equal to 500.
                        // If all of these conditions are true, the code inside the if statement is executed.
                        if ($existingItemIndex !== null && $existingItemCount + $splitQuantity <= 50 && $invoice['total'] + $splitTotalPerProduct <= 500) {
                            // updates the total cost of the invoice and the quantity of the existing item in the invoice to reflect the addition of the new item. 
                            //$addedToExistingInvoice is set to true to indicate that the new item was added to an existing invoice, and the loop is exited using the break statement.
                            $invoice['total'] += $splitTotalPerProduct;
                            $invoice['items'][$existingItemIndex]['quantity'] += $splitQuantity;
                            $addedToExistingInvoice = true;
                            break;
                            // If the conditions in the previous if statement are not true, 
                            // the elseif statement checks if the total cost of the invoice plus the cost of the item being added is less than or equal to 500.
                            // If this is true, the code inside the elseif statement is executed.
                        } elseif ($existingItemIndex === null && $invoice['total'] + $splitTotalPerProduct <= 500) {
                            //updates the total cost of the invoice to reflect the addition of the new item and add the new item to the invoice's 'items' array.
                            // $addedToExistingInvoice is set to true to indicate that the new item was added to an existing invoice, and the loop is exited using the break statement.
                            $invoice['total'] += $splitTotalPerProduct;
                            $invoice['items'][] = $currentInvoice;
                            $addedToExistingInvoice = true;
                            break;
                        }
                    }
                    // checks if the new item was not added to an existing invoice, if this is true, the code inside the if statement is executed.
                    if (!$addedToExistingInvoice) {
                        // adds a new element to the $invoices array that represents a new invoice containing only the new item being added.
                        //'items' array contains only the new item ($currentInvoice), and the 'total' value is set to the cost of the new item ($splitTotalPerProduct).
                        $invoices[] = [
                            'items' => [$currentInvoice],
                            'total' => $splitTotalPerProduct,
                        ];
                    }
                }
                // This subtracts the quantity of the new item that was just added from the total quantity being split across multiple invoices.
                $remainingQuantity -= $splitQuantity;
            }
        }
        // uala Altin I did it !!!!!
        return $invoices;
    }
}
