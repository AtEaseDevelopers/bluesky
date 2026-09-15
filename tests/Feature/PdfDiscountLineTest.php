<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The invoice (INV) and delivery order (DO) PDFs both render their totals
 * through the shared pdf.partials.document-items partial. When an order carries
 * a discount (the whole-ringgit rounding remainder stored on orders.discount),
 * that discount must show as its own line so the printed subtotal, discount and
 * grand total reconcile with the order's actual balance due.
 */
class PdfDiscountLineTest extends TestCase
{
    private function renderItems(array $overrides = []): string
    {
        $order = (object) array_merge([
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'discount' => 0.45,
        ], $overrides['order'] ?? []);

        $items = $overrides['order_items'] ?? [
            (object) [
                'sku' => 'A1', 'name' => 'Prawn', 'remark' => null,
                'show_qty' => true, 'quantity' => 1, 'show_weight' => false,
                'unit_price' => 61.73, 'price' => 61.73,
            ],
            (object) [
                'sku' => 'A2', 'name' => 'Crab', 'remark' => null,
                'show_qty' => true, 'quantity' => 1, 'show_weight' => false,
                'unit_price' => 61.72, 'price' => 61.72,
            ],
        ];

        return View::make('pdf.partials.document-items', [
            'order' => $order,
            'order_items' => $items,
            'show_price_columns' => true,
            'has_price_permission' => true,
            'footer_mode' => 'full',
            'currency' => 'MYR',
        ])->render();
    }

    /** @test */
    public function a_discount_shows_as_its_own_line_and_the_grand_total_reconciles(): void
    {
        // subtotal 123.45, discount 0.45 -> grand total 123.00
        $html = $this->renderItems();

        $this->assertStringContainsString('折扣', $html, 'discount label should be printed');
        $this->assertStringContainsString('MYR 0.45', $html, 'discount amount should be printed');
        $this->assertStringContainsString('MYR 123.45', $html, 'subtotal should be printed');
        $this->assertStringContainsString('MYR 123.00', $html, 'grand total should be the flattened total');
    }

    /** @test */
    public function no_discount_line_is_shown_when_the_discount_is_zero(): void
    {
        $html = $this->renderItems([
            'order' => ['discount' => 0],
            'order_items' => [
                (object) [
                    'sku' => 'A1', 'name' => 'Prawn', 'remark' => null,
                    'show_qty' => true, 'quantity' => 1, 'show_weight' => false,
                    'unit_price' => 100.00, 'price' => 100.00,
                ],
            ],
        ]);

        $this->assertStringNotContainsString('折扣', $html, 'no discount line when discount is zero');
        $this->assertStringContainsString('MYR 100.00', $html);
    }
}
