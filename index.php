<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('bnomei/securityheaders', [
    'options' => [
        'enabled' => true,
        'enabled.panel' => false,
        'route.before' => true,
        'headers' => [
            "X-Powered-By"              => "", // unset
            "X-Frame-Options"           => "SAMEORIGIN",
            "X-XSS-Protection"          => "1; mode=block",
            "X-Content-Type-Options"    => "nosniff",
            "strict-transport-security" => "max-age=31536000; includeSubdomains",
            "Referrer-Policy"           => "no-referrer-when-downgrade",
        ],
        'nonces' => [],
        'hashes' => [],
        'csp' => null, // callback
    ],
    'snippets' => [
        'plugin-securityheaders' => __DIR__ . '/snippets/securityheaders.php',
    ],
    'hooks' => [
        'route:before' => function () {
            if (option('bnomei.securityheaders.route.before')) {
                \Bnomei\SecurityHeaders::apply();
            }
        },
    ],
    'pageMethods' => [
        'nonce' => function ($string, $plain = false) {
            $n = \Bnomei\SecurityHeaders::nonce($string);
            if ($plain && $n) {
                return $n;
            } elseif (!$plain && $n) {
                $n = 'nonce="'.$n.'"';
            }
            return $n;
        }
    ]
]);
