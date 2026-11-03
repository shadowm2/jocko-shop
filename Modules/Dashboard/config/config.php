<?php

return [
    'name' => 'Dashboard',

    /*
     | Editable system settings (Dashboard > General > System Settings).
     | Add a group or a field here and it appears on the page.
     |
     | Stored under "group.field". `secret` fields are masked and never sent
     | back to the browser. `config` is the config() key the value overrides;
     | when nothing is stored, the .env value behind that key still applies.
     | Labels are translation keys.
     */
    'settings' => [
        'shop' => [
            'label' => 'dashboard::strings.Shop Details',
            'fields' => [
                'name' => [
                    'label' => 'dashboard::strings.Shop Name',
                    'config' => 'app.name',
                    'dir' => 'rtl',
                ],
                'address' => [
                    'label' => 'dashboard::strings.Shop Address',
                    'config' => 'shop.address',
                    'dir' => 'rtl',
                ],
                'phone' => [
                    'label' => 'dashboard::strings.Shop Phone',
                    'config' => 'shop.phone',
                    'rules' => 'nullable|string|max:50',
                ],
                'alert_mobile' => [
                    'label' => 'dashboard::strings.Low Stock Alert Mobile',
                    'config' => 'shop.alert_mobile',
                    'rules' => 'nullable|string|max:20',
                ],
            ],
        ],
        'kavenegar' => [
            'label' => 'dashboard::strings.Kavenegar SMS',
            'fields' => [
                'api_key' => [
                    'label' => 'dashboard::strings.Kavenegar API Key',
                    'secret' => true,
                    'config' => 'services.kavenegar.key',
                ],
                'sender' => [
                    'label' => 'dashboard::strings.Kavenegar Sender Number',
                    'config' => 'services.kavenegar.sender',
                    'rules' => 'nullable|string|max:20',
                ],
                'otp_template' => [
                    'label' => 'dashboard::strings.Kavenegar OTP Template',
                    'config' => 'services.kavenegar.otp_template',
                ],
            ],
        ],
        'payments' => [
            'label' => 'dashboard::strings.Online Payments',
            'fields' => [
                'currency' => [
                    'label' => 'dashboard::strings.Payments Currency',
                    'config' => 'payments.currency',
                    'rules' => 'nullable|in:IRT,IRR',
                ],
                'callback_base_url' => [
                    'label' => 'dashboard::strings.Payments Callback Address',
                    'config' => 'payments.callback_base_url',
                    'rules' => 'nullable|url|max:255',
                ],
            ],
        ],
        'zarinpal' => [
            'label' => 'dashboard::strings.Zarinpal',
            'fields' => [
                'merchant_id' => [
                    'label' => 'dashboard::strings.Zarinpal Merchant ID',
                    'secret' => true,
                    'config' => 'payments.zarinpal.merchant_id',
                ],
                'sandbox' => [
                    'label' => 'dashboard::strings.Gateway Sandbox',
                    'config' => 'payments.zarinpal.sandbox',
                    'rules' => 'nullable|in:0,1',
                ],
            ],
        ],
        'nextpay' => [
            'label' => 'dashboard::strings.NextPay',
            'fields' => [
                'api_key' => [
                    'label' => 'dashboard::strings.NextPay API Key',
                    'secret' => true,
                    'config' => 'payments.nextpay.api_key',
                ],
            ],
        ],
        'zibal' => [
            'label' => 'dashboard::strings.Zibal',
            'fields' => [
                'merchant' => [
                    'label' => 'dashboard::strings.Zibal Merchant',
                    'secret' => true,
                    'config' => 'payments.zibal.merchant',
                ],
            ],
        ],
        'jocko_control' => [
            'label' => 'dashboard::strings.Jocko Control',
            'fields' => [
                'url' => [
                    'label' => 'dashboard::strings.Jocko Control URL',
                    'config' => 'control.url',
                    'rules' => 'nullable|url|max:255',
                ],
                'token' => [
                    'label' => 'dashboard::strings.Jocko Control Token',
                    'secret' => true,
                    'config' => 'control.token',
                ],
            ],
        ],
    ],
];
