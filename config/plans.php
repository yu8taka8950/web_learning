<?php

return [
    'free' => ['web' => 100, 'screenshot' => 100],
    'plus' => ['web' => 1000, 'screenshot' => 1000],
    'prices' => ['jpy' => env('STRIPE_PRICE_PLUS_JPY'), 'usd' => env('STRIPE_PRICE_PLUS_USD')],
];
