<?php

declare(strict_types=1);

namespace MyParcelNL\WooCommerce\Tests\Mock;

use MyParcelNL\Pdk\Base\Support\Arr;

/**
 * @extends \WC_Order
 */
class MockWcOrder extends MockWcClass
{
    /**
     * @param  string $note
     * @param  int    $is_customer_note
     * @param  bool   $added_by_user
     *
     * @return int
     * @see \WC_Order::add_order_note()
     */
    public function add_order_note(string $note, int $is_customer_note = 0, bool $added_by_user = false): int
    {
        if ($is_customer_note) {
            $this->attributes['customer_note'] = $note;

            return 0;
        }

        $notes = Arr::wrap($this->attributes['order_notes'] ?? []);

        $notes[] = $note;

        $this->attributes['order_notes'] = $notes;

        return count($notes) - 1;
    }
}
