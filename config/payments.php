<?php

return [
    /*
    | The unit amounts are kept in: IRT (toman) or IRR (rial). It is only used to
    | tell a gateway what it is being charged; it does not change what the shop shows.
    */
    'currency' => env('PAYMENTS_CURRENCY', 'IRT'),

    /*
    | The address a customer's browser is sent back to after paying online. A
    | gateway only accepts the domain registered with it, so set the address (no
    | trailing path) the shop can be reached on from the customer's phone. Blank
    | uses APP_URL. If the shop cannot be reached from outside, payments are still
    | found: the shop asks the gateway about every open payment (payments:reconcile).
    */
    'callback_base_url' => env('PAYMENTS_CALLBACK_BASE_URL'),

    // How long an unpaid online payment stays open before it is given up.
    'attempt_ttl_minutes' => (int) env('PAYMENTS_ATTEMPT_TTL_MINUTES', 30),

    // A customer who owes is texted a reminder at most once in this many days (a manual one can override it).
    'reminder_min_days' => (int) env('PAYMENTS_REMINDER_MIN_DAYS', 7),

    // Paper width of the payment receipt, in millimetres: 58 or 80 (a thermal printer's roll).
    'receipt_width' => in_array((int) env('PAYMENTS_RECEIPT_WIDTH', 80), [58, 80], true) ? (int) env('PAYMENTS_RECEIPT_WIDTH', 80) : 80,

    'timeout' => 15,

    // Gateway credentials: set them in Settings (stored encrypted), or here. Never commit them.
    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        // A sandbox merchant id is any UUID; Zarinpal serves it from another host.
        'sandbox' => env('ZARINPAL_SANDBOX', false),
    ],

    'nextpay' => [
        'api_key' => env('NEXTPAY_API_KEY'),
    ],

    // `zibal` is Zibal's own test merchant: payments with it are simulated.
    'zibal' => [
        'merchant' => env('ZIBAL_MERCHANT'),
    ],
];
