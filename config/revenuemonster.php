<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Revenue Monster Credentials
    |--------------------------------------------------------------------------
    |
    | Obtain the client id / client secret from the Revenue Monster Merchant
    | Portal. The private key is yours (used to sign requests); the public key
    | is Revenue Monster's (used to verify callbacks / responses).
    |
    | Keys may be supplied inline as a PEM string, or via an absolute file path.
    | The path variant takes effect only when the inline value is empty.
    |
    */
    'client_id' => env('RM_CLIENT_ID', ''),
    'client_secret' => env('RM_CLIENT_SECRET', ''),

    'private_key' => env('RM_PRIVATE_KEY', ''),
    'private_key_path' => env('RM_PRIVATE_KEY_PATH', ''),

    'public_key' => env('RM_PUBLIC_KEY', ''),
    'public_key_path' => env('RM_PUBLIC_KEY_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | When sandbox is true, requests are sent to the sb-* hosts.
    |
    */
    'sandbox' => (bool) env('RM_SANDBOX', true),

    // Optional default store id used when a payment request omits one.
    'store_id' => env('RM_STORE_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Hosted Checkout
    |--------------------------------------------------------------------------
    |
    | Payment methods offered on the hosted checkout page. Set RM_METHODS to the
    | codes you want (e.g. "DUITNOW_QRCODE,TNG_MY,BOOST_MY") to restrict to
    | DuitNow + e-wallets and exclude FPX / online banking. IMPORTANT: Revenue
    | Monster rejects the whole request if ANY listed method is not active on the
    | merchant — list ONLY active codes. Leave empty to show every active method.
    |
    */
    'methods' => array_values(array_filter(array_map('trim', explode(',', env('RM_METHODS', ''))))),

    // Where RM sends the customer after paying (defaults to the app URL).
    'redirect_url' => env('RM_REDIRECT_URL', ''),

    // Minimum balance (RM) we'll generate a checkout for. FPX has a ~RM1 floor;
    // below this the customer can't complete payment.
    'min_amount' => (float) env('RM_MIN_AMOUNT', 1),

    // Max age (seconds) accepted for a callback's X-Timestamp (replay guard).
    'callback_tolerance' => (int) env('RM_CALLBACK_TOLERANCE', 300),

    // Guzzle request timeout (seconds).
    'timeout' => (int) env('RM_HTTP_TIMEOUT', 30),
];
