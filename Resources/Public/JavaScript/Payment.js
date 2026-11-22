/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/**
 * Shows the button of the PayPal JavaScript SDK v6 on the payment page: the
 * PayPal button, or the card button for a debit or credit card without a
 * PayPal account.
 *
 * The order is created and captured by the server, the browser only passes
 * the id of the PayPal order. The amount is never sent by the browser.
 * If the SDK can not be used, a link sends the customer to PayPal instead.
 */
(() => {
  'use strict';

  const container = document.querySelector('[data-cart-paypal-payment]');
  if (!container) {
    return;
  }

  const config = container.dataset;
  const message = container.querySelector('[data-cart-paypal-message]');
  const fallback = container.querySelector('[data-cart-paypal-fallback]');

  const paymentMethods = {
    paypal: {
      component: 'paypal-payments',
      eligibility: 'paypal',
      button: 'paypal-button',
      createSession: (sdkInstance, callbacks) => sdkInstance.createPayPalOneTimePaymentSession(callbacks),
    },
    card: {
      component: 'paypal-guest-payments',
      eligibility: 'card',
      button: 'paypal-basic-card-button',
      createSession: (sdkInstance, callbacks) => sdkInstance.createPayPalGuestOneTimePaymentSession({
        ...callbacks,
        // a declined card is reported in the overlay, the customer may correct it there
        onWarn: () => {},
        onComplete: () => {},
      }),
    },
  };
  const paymentMethod = paymentMethods[config.paymentMethod] || paymentMethods.paypal;
  const button = container.querySelector(paymentMethod.button);

  const showMessage = (text) => {
    message.textContent = text || config.messageError;
    message.hidden = false;
  };

  const hideMessage = () => {
    message.hidden = true;
  };

  const showFallback = () => {
    if (button) {
      button.hidden = true;
    }
    fallback.hidden = false;
  };

  const loadSdk = () => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = config.sdkUrl;
    script.async = true;
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });

  const postJson = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body || {}),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(data.message || response.statusText);
      error.userMessage = data.message;
      throw error;
    }

    return data;
  };

  const createOrder = async () => {
    const data = await postJson(config.createOrderUrl);

    return { orderId: data.orderId };
  };

  const onApprove = async ({ orderId }) => {
    try {
      const data = await postJson(config.captureOrderUrl, { orderId });
      if (data.redirectUrl) {
        window.location.assign(data.redirectUrl);
        return;
      }
      showMessage(data.message);
    } catch (error) {
      showMessage(error.userMessage);
    }
  };

  const init = async () => {
    if (!config.clientId || !button) {
      showFallback();
      return;
    }

    await loadSdk();

    const options = {
      clientId: config.clientId,
      components: [paymentMethod.component],
      pageType: 'checkout',
    };
    if (config.locale) {
      options.locale = config.locale;
    }
    const sdkInstance = await window.paypal.createInstance(options);

    const eligibility = await sdkInstance.findEligibleMethods({
      currencyCode: config.currencyCode,
      amount: config.amount,
    });
    if (!eligibility.isEligible(paymentMethod.eligibility)) {
      showFallback();
      return;
    }

    const session = await paymentMethod.createSession(sdkInstance, {
      onApprove,
      onCancel: () => showMessage(config.messageCanceled),
      onError: () => showMessage(config.messageError),
    });

    button.addEventListener('click', async () => {
      hideMessage();
      try {
        // not awaited before start(), the window needs the click of the customer
        const orderPromise = createOrder();
        await session.start({ presentationMode: 'auto' }, orderPromise);
      } catch (error) {
        showMessage(error.userMessage);
      }
    });

    button.hidden = false;
  };

  init().catch(() => showFallback());
})();
