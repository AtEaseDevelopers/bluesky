<?php

return [
    'api_token' => env('AUTOCOUNT_API_TOKEN', ''),
    'branch_email' => env('AUTOCOUNT_BRANCH_EMAIL', env('PORTAL_COMPANY_EMAIL', '')),
];
