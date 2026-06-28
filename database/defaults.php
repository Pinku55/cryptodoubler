<?php
/**
 * MTASK - Default seed data
 * --------------------------------------------------------------------------
 * Returns the default settings, daily-bonus ladder, payment methods and
 * sample tasks inserted by the installer on a fresh installation.
 *
 * @package MTASK
 */

declare(strict_types=1);

return [
    // -----------------------------------------------------------------
    // Default application settings (admin editable later)
    // -----------------------------------------------------------------
    'settings' => [
        'site_name'            => 'MTASK',
        'currency_symbol'      => 'MT',
        'logo'                 => '',
        'favicon'              => '',
        'theme_color'          => '#7c3aed',
        'maintenance_mode'     => '0',
        'announcement'         => '',

        // Economy
        'mt_per_usd'           => '10000',   // 10,000 MT = 1 USD
        'min_withdraw'         => '20000',   // 20,000 MT
        'usd_per_min_withdraw' => '2',       // display helper

        // Rewarded ads (Monetag)
        'monetag_zone_id'      => '9660124',
        'ad_reward'            => '50',
        'ad_cooldown'          => '30',      // seconds between ads
        'ad_daily_limit'       => '50',      // ads per day per user
        'ad_max_earnings'      => '0',       // 0 = unlimited per day
        'ads_enabled'          => '1',

        // Daily bonus
        'daily_bonus_enabled'  => '1',

        // Referrals
        'referral_reward'      => '1000',
        'referral_max'         => '0',       // 0 = unlimited
        'referral_min_activity'=> '0',

        // Telegram
        'telegram_bot_token'   => '',
        'telegram_bot_username'=> '',
        'webhook_url'          => '',
        'webhook_secret'       => '',

        // Misc
        'support_username'     => '',
        'privacy_url'          => '',
        'terms_url'            => '',
    ],

    // -----------------------------------------------------------------
    // 7-day daily bonus ladder (day => reward MT)
    // -----------------------------------------------------------------
    'daily_bonus' => [
        1 => 100,
        2 => 200,
        3 => 300,
        4 => 500,
        5 => 800,
        6 => 1200,
        7 => 2000,
    ],

    // -----------------------------------------------------------------
    // Default payment methods with dynamic fields
    // -----------------------------------------------------------------
    'payment_methods' => [
        [
            'name' => 'UPI', 'code' => 'upi', 'icon' => 'bi-bank', 'min_amount' => 20000, 'sort_order' => 1,
            'fields' => [['name' => 'upi_id', 'label' => 'UPI ID', 'type' => 'text', 'required' => true]],
        ],
        [
            'name' => 'Paytm', 'code' => 'paytm', 'icon' => 'bi-wallet2', 'min_amount' => 20000, 'sort_order' => 2,
            'fields' => [['name' => 'paytm_number', 'label' => 'Paytm Number', 'type' => 'text', 'required' => true]],
        ],
        [
            'name' => 'PayPal', 'code' => 'paypal', 'icon' => 'bi-paypal', 'min_amount' => 50000, 'sort_order' => 3,
            'fields' => [['name' => 'paypal_email', 'label' => 'PayPal Email', 'type' => 'email', 'required' => true]],
        ],
        [
            'name' => 'USDT TRC20', 'code' => 'usdt_trc20', 'icon' => 'bi-currency-bitcoin', 'min_amount' => 50000, 'sort_order' => 4,
            'fields' => [['name' => 'wallet', 'label' => 'TRC20 Wallet Address', 'type' => 'text', 'required' => true]],
        ],
        [
            'name' => 'Binance Pay', 'code' => 'binance', 'icon' => 'bi-coin', 'min_amount' => 50000, 'sort_order' => 5,
            'fields' => [['name' => 'binance_id', 'label' => 'Binance Pay ID', 'type' => 'text', 'required' => true]],
        ],
        [
            'name' => 'Bank Transfer', 'code' => 'bank', 'icon' => 'bi-bank2', 'min_amount' => 100000, 'sort_order' => 6,
            'fields' => [
                ['name' => 'account_name', 'label' => 'Account Holder Name', 'type' => 'text', 'required' => true],
                ['name' => 'account_number', 'label' => 'Account Number', 'type' => 'text', 'required' => true],
                ['name' => 'ifsc', 'label' => 'IFSC / SWIFT', 'type' => 'text', 'required' => true],
            ],
        ],
    ],

    // -----------------------------------------------------------------
    // Sample tasks (admin can edit/delete)
    // -----------------------------------------------------------------
    'tasks' => [
        [
            'title' => 'Visit our sponsor website', 'category' => 'website',
            'url' => 'https://example.com', 'reward' => 200, 'wait_time' => 15,
            'verify_type' => 'timer', 'description' => 'Open the website and stay for 15 seconds.',
        ],
        [
            'title' => 'Join our Telegram channel', 'category' => 'telegram_channel',
            'url' => 'https://t.me/telegram', 'reward' => 500, 'wait_time' => 10,
            'verify_type' => 'timer', 'description' => 'Join the channel to earn your reward.',
        ],
        [
            'title' => 'Subscribe on YouTube', 'category' => 'youtube',
            'url' => 'https://youtube.com', 'reward' => 600, 'wait_time' => 20,
            'verify_type' => 'timer', 'description' => 'Subscribe and watch for 20 seconds.',
        ],
    ],
];
