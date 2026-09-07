<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AST to PHP conversion
    |--------------------------------------------------------------------------
    |
    | Closed-loop internal credit. Default is 1 AST = 1 PHP.
    | Change AST_PHP_RATE only after an explicit product decision.
    |
    */
    'php_per_ast' => (float) env('AST_PHP_RATE', 1),

    /*
    |--------------------------------------------------------------------------
    | Maker-checker load threshold
    |--------------------------------------------------------------------------
    |
    | Loads at or above this AST amount require a second staff approval
    | (wallet_load_requests) before the wallet is credited. Loads below
    | the threshold complete in the same request after the maker submits.
    |
    */
    'load_approval_threshold' => (float) env('AST_LOAD_APPROVAL_THRESHOLD', 10000),

    'max_load_amount' => (float) env('AST_MAX_LOAD_AMOUNT', 100000),

    /*
    | Roles allowed to load AST and hit /api/v1/admin/wallet/*.
    | Comma-separated in AST_LOAD_WALLET_ROLES.
    */
    'load_wallet_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AST_LOAD_WALLET_ROLES', 'Administrator,administrator,Customer Service'))
    ))),
];
