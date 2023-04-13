<?php

declare(strict_types=1);

namespace MyParcelNL\WooCommerce\Pdk\Plugin\Repository;

use MyParcelNL\Pdk\Base\Service\CountryService;
use MyParcelNL\Pdk\Base\Support\Collection;
use MyParcelNL\Pdk\Facade\DefaultLogger;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Facade\Settings;
use MyParcelNL\Pdk\Plugin\Model\PdkOrder;
use MyParcelNL\Pdk\Plugin\Model\PdkOrderLine;
use MyParcelNL\Pdk\Plugin\Repository\AbstractPdkOrderRepository;
use MyParcelNL\Pdk\Product\Contract\ProductRepositoryInterface;
use MyParcelNL\Pdk\Settings\Model\GeneralSettings;
use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Shipment\Model\CustomsDeclaration;
use MyParcelNL\Pdk\Shipment\Model\CustomsDeclarationItem;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;
use MyParcelNL\Sdk\src\Support\Arr;
use MyParcelNL\WooCommerce\Service\WcRecipientService;
use Throwable;
use WC_Order;
use WC_Order_Item_Product;
use function apply_filters;

class PdkOrderRepository extends AbstractPdkOrderRepository
{
    /**
     * @var \MyParcelNL\Pdk\Base\Service\CountryService
     */
    private $countryService;

    /**
     * @var \MyParcelNL\Pdk\Product\Contract\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \MyParcelNL\WooCommerce\Service\WcRecipientService
     */
    private $recipientService;

    /**
     * @param  \MyParcelNL\Pdk\Storage\Contract\StorageInterface           $storage
     * @param  \MyParcelNL\Pdk\Product\Contract\ProductRepositoryInterface $productRepository
     * @param  \MyParcelNL\WooCommerce\Service\WcRecipientService          $recipientService
     * @param  \MyParcelNL\Pdk\Base\Service\CountryService                 $countryService
     */
    public function __construct(
        StorageInterface           $storage,
        ProductRepositoryInterface $productRepository,
        WcRecipientService         $recipientService,
        CountryService             $countryService
    ) {
        parent::__construct($storage);
        $this->productRepository = $productRepository;
        $this->recipientService  = $recipientService;
        $this->countryService    = $countryService;
    }

    /**
     * @param  int|string|WC_Order $input
     *
     * @return \MyParcelNL\Pdk\Plugin\Model\PdkOrder
     * @throws \Exception
     */
    public function get($input): PdkOrder
    {
        $order = $this->getWcOrder($input);

        return $this->retrieve((string) $order->get_id(), function () use ($order) {
            try {
                return $this->getDataFromOrder($order);
            } catch (Throwable $exception) {
                DefaultLogger::error(
                    'Could not retrieve order data from WooCommerce order',
                    [
                        'order_id' => $order->get_id(),
                        'error'    => $exception->getMessage(),
                    ]
                );

                return new PdkOrder();
            }
        });
    }

    /**
     * @param  \MyParcelNL\Pdk\Plugin\Model\PdkOrder $order
     *
     * @return \MyParcelNL\Pdk\Plugin\Model\PdkOrder
     * @throws \MyParcelNL\Pdk\Base\Exception\InvalidCastException
     * @throws \Exception
     */
    public function update(PdkOrder $order): PdkOrder
    {
        $wcOrder       = $this->getWcOrder($order->externalIdentifier);
        $existingOrder = $this->get($order->externalIdentifier);

        if (serialize($order) !== serialize($existingOrder)) {
            update_post_meta($wcOrder->get_id(), Pdk::get('metaKeyOrderData'), $order->toStorableArray());
        }

        $this->saveShipments($wcOrder, $order);

        return $this->save($order->externalIdentifier, $order);
    }

    /**
     * @param  \WC_Order                               $order
     * @param  \MyParcelNL\Pdk\Base\Support\Collection $items
     *
     * @return array
     */
    private function createCustomsDeclaration(WC_Order $order, Collection $items): array
    {
        return [
            'contents' => CustomsDeclaration::CONTENTS_COMMERCIAL_GOODS,
            'invoice'  => $order->get_id(),
            'items'    => array_values(
                $items->filter(function ($item) {
                    return $item['product'] && ! $item['product']->is_virtual();
                })
                    ->map(function ($item) {
                        return CustomsDeclarationItem::fromProduct($item['pdkProduct']);
                    })
                    ->toArray()
            ),
        ];
    }

    /**
     * @param  \WC_Order $order
     *
     * @return \MyParcelNL\Pdk\Plugin\Model\PdkOrder
     * @throws \ErrorException
     * @throws \JsonException
     * @throws \MyParcelNL\Pdk\Base\Exception\InvalidCastException
     */
    private function getDataFromOrder(WC_Order $order): PdkOrder
    {
        $savedOrderData  = $order->get_meta(Pdk::get('metaKeyOrderData')) ?: [];
        $deliveryOptions = apply_filters(
            'wc_myparcel_order_delivery_options',
            $savedOrderData['deliveryOptions'] ?? [],
            $order
        );

        $items     = $this->getWcOrderItems($order);
        $recipient = $this->recipientService->createAddress($order, WcRecipientService::SHIPPING);

        $orderData = [
            'externalIdentifier'    => $order->get_id(),
            'recipient'             => $recipient,
            'billingAddress'        => $this->recipientService->createAddress($order, WcRecipientService::BILLING),
            'deliveryOptions'       => $deliveryOptions,
            'lines'                 => $items
                ->map(function (array $item) {
                    return new PdkOrderLine([
                        'quantity' => $item['item']->get_quantity(),
                        'price'    => (int) ((float) $item['item']->get_total() * 100),
                        'product'  => $item['pdkProduct'],
                    ]);
                })
                ->toArray(),
            'customsDeclaration'    => $this->countryService->isRow($recipient['cc'])
                ? $this->createCustomsDeclaration($order, $items)
                : null,
            'physicalProperties'    => $this->getPhysicalProperties($items),
            'orderPrice'            => $order->get_total(),
            'orderPriceAfterVat'    => ((float) $order->get_total()) + ((float) $order->get_cart_tax()),
            'orderVat'              => $order->get_total_tax(),
            'shipmentPrice'         => (float) $order->get_shipping_total(),
            'shipmentPriceAfterVat' => (float) $order->get_shipping_total(),
            'shipments'             => $this->getShipments($order),
            'shipmentVat'           => (float) $order->get_shipping_tax(),
            'orderDate'             => $this->getOrderDate($order),
        ];

        return new PdkOrder($orderData + $savedOrderData);
    }

    /**
     * @param  \WC_Order $order
     *
     * @return null|string
     */
    private function getOrderDate(WC_Order $order): ?string
    {
        $wcOrderCreated = $order->get_date_created();

        return $wcOrderCreated ? $wcOrderCreated->format('Y-m-d H:i:s') : null;
    }

    private function getPhysicalProperties(Collection $items): array
    {
        $totalWeight = $items->reduce(static function (float $acc, $item) {
            if (is_numeric($item['item']->get_quantity()) && is_numeric($item['product']->get_weight())) {
                $acc += $item['item']->get_quantity() * $item['product']->get_weight();
            }
            return $acc;
        }, 0);

        return [
            'weight' => $totalWeight
        ];
    }

    /**
     * @param  \WC_Order $order
     *
     * @return null|\MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection
     */
    private function getShipments(WC_Order $order): ?ShipmentCollection
    {
        $shipments = $order->get_meta(Pdk::get('metaKeyShipments')) ?: null;

        return new ShipmentCollection($shipments);
    }

    /**
     * @param  int|string|WC_Order $input
     *
     * @return \WC_Order
     */
    private function getWcOrder($input): WC_Order
    {
        $order = $input;

        if (! is_a($input, WC_Order::class)) {
            return $this->retrieve("wc_order_$input", function () use ($input) {
                return new WC_Order($input);
            });
        }

        return $this->save("wc_order_{$order->get_id()}", $order);
    }

    /**
     * @param  \WC_Order $order
     *
     * @return \MyParcelNL\Pdk\Base\Support\Collection
     */
    private function getWcOrderItems(WC_Order $order): Collection
    {
        return new Collection(
            array_map(function ($item) {
                $product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;

                return [
                    'item'       => $item,
                    'product'    => $product,
                    'pdkProduct' => $product ? $this->productRepository->getProduct($product) : null,
                ];
            }, array_values($order->get_items()))
        );
    }

    /**
     * @param  \WC_Order                             $wcOrder
     * @param  \MyParcelNL\Pdk\Plugin\Model\PdkOrder $order
     *
     * @return void
     */
    private function saveShipments(WC_Order $wcOrder, PdkOrder $order): void
    {
        $existingShipments = get_post_meta($wcOrder->get_id(), Pdk::get('metaKeyShipments'), true) ?: [];

        $order->shipments = (new ShipmentCollection($existingShipments))->mergeByKey($order->shipments, 'id');

        $shipmentsArray = $order->shipments->toStorableArray();

        update_post_meta($wcOrder->get_id(), Pdk::get('metaKeyShipments'), $shipmentsArray);

        $barcodes = array_filter(
            Arr::pluck($shipmentsArray, 'barcode'),
            static function ($item) { return null !== $item; }
        );

        if ($barcodes) {
            $prefix = Settings::get(GeneralSettings::BARCODE_IN_NOTE_TITLE, GeneralSettings::ID);

            $wcOrder->add_order_note($prefix . implode(', ', $barcodes));
        }
    }
}