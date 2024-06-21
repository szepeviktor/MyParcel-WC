<?php

declare(strict_types=1);

namespace MyParcelNL\WooCommerce\Pdk;

use MyParcelNL\Pdk\Base\PdkBootstrapper;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\CheckoutSettings;
use function DI\factory;
use function DI\value;

/**
 * @see /config/pdk.php for services and values not based on plugin data
 */
class WcPdkBootstrapper extends PdkBootstrapper
{
    /**
     * @var array
     */
    private static $config = [];

    /**
     * @param  string $name
     * @param  string $title
     * @param  string $version
     * @param  string $path
     * @param  string $url
     *
     * @return array
     */
    protected function getAdditionalConfig(
        string $name,
        string $title,
        string $version,
        string $path,
        string $url
    ): array {
        return array_replace(self::$config, [
            ###
            # General
            ###

            'pluginBasename' => factory(function (): string {
                return plugin_basename(Pdk::getAppInfo()->path);
            }),

            'urlDocumentation' => value('https://developer.myparcel.nl/nl/documentatie/10.woocommerce.html'),
            'urlReleaseNotes'  => value('https://github.com/myparcelnl/woocommerce/releases'),

            'defaultWeightUnit' => value('kg'),

            'wcAddressTypeBilling'  => value('billing'),
            'wcAddressTypeShipping' => value('shipping'),

            'wcAddressTypes' => factory(static function (): array {
                return [
                    Pdk::get('wcAddressTypeBilling'),
                    Pdk::get('wcAddressTypeShipping'),
                ];
            }),

            'fieldAddress1'   => value('address_1'),
            'fieldAddress2'   => value('address_2'),
            'fieldCity'       => value('city'),
            'fieldCompany'    => value('company'),
            'fieldCountry'    => value('country'),
            'fieldEmail'      => value('email'),
            'fieldFirstName'  => value('first_name'),
            'fieldLastName'   => value('last_name'),
            'fieldPhone'      => value('phone'),
            'fieldPostalCode' => value('postcode'),
            'fieldRegion'     => value('state'),

            'fieldNumber'       => value('house_number'),
            'fieldNumberSuffix' => value('house_number_suffix'),
            'fieldStreet'       => value('street_name'),

            ###
            # Meta Keys
            ###

            /**
             * The meta key a PdkOrder's data is saved in.
             *
             * @see \MyParcelNL\Pdk\App\Order\Model\PdkOrder
             */

            'metaKeyOrderData' => value("_{$name}_order_data"),

            /**
             * The meta key a PdkOrder's shipments are saved in.
             *
             * @see \MyParcelNL\Pdk\Shipment\Model\Shipment
             * @see \MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection
             */

            'metaKeyOrderShipments'        => value("_{$name}_order_shipments"),

            /**
             * The meta key legacy delivery options are saved in, for compatibility with external systems.
             */
            'metaKeyLegacyDeliveryOptions' => value('_myparcel_delivery_options'),

            /**
             * The meta key a PdkOrder's notes are saved in.
             */
            'metaKeyOrderNotes'            => value("_{$name}_order_notes"),

            /**
             * The meta key a product's MyParcel settings are saved in.
             *
             * @see \MyParcelNL\WooCommerce\Pdk\Product\Repository\WcPdkProductRepository
             */

            'metaKeyProductSettings' => value("_{$name}_product_settings"),

            /**
             * The database table audits are saved in.
             *
             * @see \MyParcelNL\WooCommerce\Pdk\Audit\Repository\WcPdkAuditRepository
             */

            'tableNameAudits' => value("{$name}_audits"),

            /**
             * The meta key that stores the version of the plugin the resource was last saved with.
             */

            'metaKeyVersion' => value("_{$name}_version"),

            /**
             * Meta keys for the shipping address. These are generated by WooCommerce and don't have the name prefix.
             */

            'metaKeyFieldShippingStreet' => value('_shipping_street_name'),

            'metaKeyFieldShippingNumber' => value('house__shipping_number'),

            'metaKeyFieldShippingNumberSuffix' => value('_shipping_house_number_suffix'),

            ###
            # Order list page
            ###

            'orderListColumnName'     => value($name),
            'orderListColumnTitle'    => value($title),
            'orderListPreviousColumn' => value('shipping_address'),

            /**
             * Bulk order actions.
             *
             * @example Pdk::get('bulkActions') // gets the bulk actions for the current order mode.
             */

            'allBulkActions' => value([
                'default'   => [
                    'action_print',
                    'action_export_print',
                    'action_export',
                    'action_edit',
                ],
                'orderMode' => [
                    'action_edit',
                    'action_export',
                ],
            ]),

            ###
            # Single order page
            ###

            'orderMetaBoxId'    => value("{$name}_woocommerce_order_data"),
            'orderMetaBoxTitle' => value($title),

            ###
            # Settings
            ###

            'settingsMenuSlug'      => value("woocommerce_page_$name-settings"),
            'settingsMenuSlugShort' => value("$name-settings"),
            'settingsMenuTitle'     => value($title),
            'settingsPageTitle'     => value("$title WooCommerce"),

            /**
             * Prefix of each setting saved to the database. Prefixed with an underscore to prevent it from being shown
             * and edited in ACF.
             */

            'settingKeyPrefix' => value("_{$name}_"),

            /**
             * Settings defaults
             */

            'defaultSettings'          => value([
                CheckoutSettings::ID => [
                    CheckoutSettings::ALLOWED_SHIPPING_METHODS  => ['flat_rate:0', 'free_shipping:0'],
                    CheckoutSettings::DELIVERY_OPTIONS_POSITION => 'woocommerce_after_checkout_billing_form',
                ],
            ]),

            /**
             * @see https://www.businessbloomer.com/woocommerce-visual-hook-guide-checkout-page/
             */
            'deliveryOptionsPositions' => value([
                'woocommerce_checkout_before_customer_details',
                'woocommerce_before_checkout_billing_form',
                'woocommerce_after_checkout_billing_form',
                'woocommerce_before_checkout_shipping_form',
                'woocommerce_after_checkout_shipping_form',
                'woocommerce_before_order_notes',
                'woocommerce_after_order_notes',
                'woocommerce_checkout_after_customer_details',
                'woocommerce_review_order_before_shipping',
                'woocommerce_review_order_after_shipping',
                'woocommerce_review_order_before_order_total',
                'woocommerce_review_order_after_order_total',
            ]),

            ###
            # Routes
            ###

            'routeBackend'                   => value("$name/backend/v1"),
            'routeBackendPdk'                => value('pdk'),
            'routeBackendWebhook'            => value('webhook/(?P<hash>.+)'),
            'routeBackendPermissionCallback' => factory(static function (): string {
                if (! is_user_logged_in()) {
                    return '__return_false';
                }

                foreach (wp_get_current_user()->roles as $role) {
                    if (in_array($role, ['shop_manager', 'administrator'])) {
                        return '__return_true';
                    }
                }

                return '__return_false';
            }),

            'routeFrontend'         => value("$name/frontend/v1"),
            'routeFrontendMyParcel' => value($name),

            ###
            # Filters
            ###

            'filters' => value([
                'separateAddressFieldsPriority'    => 'mpwc_checkout_separate_address_fields_priority',
                'taxFieldsPriority'                => 'mpwc_checkout_tax_fields_priority',
                'trackTraceInMyAccountPriority'    => 'mpwc_track_trace_in_my_account_priority',
                'trackTraceInMyEmailPriority'      => 'mpwc_track_trace_in_email_priority',
                'trackTraceInOrderDetailsPriority' => 'mpwc_track_trace_in_order_details_priority',

                /**
                 * Field classes
                 */
                'fieldEoriNumberClass'             => 'mpwc_checkout_field_eori_number_class',
                'fieldVatNumberClass'              => 'mpwc_checkout_field_vat_number_class',
                'fieldStreetClass'                 => 'mpwc_checkout_field_street_class',
                'fieldNumberClass'                 => 'mpwc_checkout_field_number_class',
                'fieldNumberSuffixClass'           => 'mpwc_checkout_field_number_suffix_class',

                /**
                 * Field priorities
                 */
                'fieldEoriNumberPriority'          => 'mpwc_checkout_field_eori_number_priority',
                'fieldVatNumberPriority'           => 'mpwc_checkout_field_vat_number_priority',
                'fieldStreetPriority'              => 'mpwc_checkout_field_street_priority',
                'fieldNumberPriority'              => 'mpwc_checkout_field_number_priority',
                'fieldNumberSuffixPriority'        => 'mpwc_checkout_field_number_suffix_priority',

                /**
                 * Checkout
                 */
                'deliveryOptionsPosition'          => 'mpwc_checkout_delivery_options_position',
                'deliveryOptionsPositions'         => 'mpwc_checkout_delivery_options_positions',
                'orderDeliveryOptions'             => 'mpwc_checkout_order_delivery_options',
                'showDeliveryOptions'              => 'mpwc_checkout_show_delivery_options',

                /**
                 * Account page
                 */
                'trackTraceLabel'                  => 'mpwc_track_trace_label',
            ]),

            'filterDefaults' => value([
                'separateAddressFieldsPriority'    => 10,
                'taxFieldsPriority'                => 10,
                'trackTraceInEmailPriority'        => 10,
                'trackTraceInMyAccountPriority'    => 10,
                'trackTraceInOrderDetailsPriority' => 10,

                'fieldStreetClass'       => ['form-row-third', 'first'],
                'fieldNumberClass'       => ['form-row-third'],
                'fieldNumberSuffixClass' => ['form-row-third', 'last'],

                'fieldEoriNumberClass' => ['form-row'],
                'fieldVatNumberClass'  => ['form-row'],

                'fieldStreetPriority'       => 60,
                'fieldNumberPriority'       => 61,
                'fieldNumberSuffixPriority' => 62,

                'fieldEoriNumberPriority' => 900,
                'fieldVatNumberPriority'  => 901,
            ]),

            ###
            # Migrations
            ###

            'metaKeyMigrated' => value("_{$name}_migrated"),

            # Migration actions

            'migrateAction_5_0_0_Orders'          => value("{$name}_migrate_5_0_0_orders"),
            'migrateAction_5_0_0_ProductSettings' => value("{$name}_migrate_5_0_0_product_settings"),

            # WP Cron actions

            'webhookAddActions' => value("{$name}_all_actions"),
            'webhookActionName' => value("{$name}_hook_"),
        ]);
    }
}
