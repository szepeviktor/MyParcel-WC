import '../assets/scss/index.scss';
import {PdkEvent, initializeCheckoutSeparateAddressFields, useEvent, usePdkCheckout} from '@myparcel-pdk/checkout/src';
import {EVENT_WOOCOMMERCE_COUNTRY_TO_STATE_CHANGED} from '@myparcel-woocommerce/frontend-common/src';

usePdkCheckout().onInitialize(() => {
  // @ts-expect-error this is a valid event
  jQuery(document.body).on(EVENT_WOOCOMMERCE_COUNTRY_TO_STATE_CHANGED, (event: Event, newCountry: string) => {
    document.dispatchEvent(new CustomEvent(useEvent(PdkEvent.CheckoutUpdate), {detail: newCountry}));
  });

  initializeCheckoutSeparateAddressFields();
});