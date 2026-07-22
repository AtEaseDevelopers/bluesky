<?php

return [
    'api_token' => env('AUTOCOUNT_API_TOKEN', ''),
    'branch_email' => env('AUTOCOUNT_BRANCH_EMAIL', env('PORTAL_COMPANY_EMAIL', '')),
    // AutoCount debtor AccNo for orders without a registered customer account.
    'walk_in_debtor_code' => env('AUTOCOUNT_WALK_IN_DEBTOR_CODE', ''),
    'walk_in_debtor_codes' => [
        'walk_in' => env('AUTOCOUNT_WALK_IN_DEBTOR_CODE', ''),
        'public' => env('AUTOCOUNT_PUBLIC_ORDER_DEBTOR_CODE', env('AUTOCOUNT_WALK_IN_DEBTOR_CODE', '')),
        'pos' => env('AUTOCOUNT_POS_GUEST_DEBTOR_CODE', env('AUTOCOUNT_WALK_IN_DEBTOR_CODE', '')),
    ],
];
