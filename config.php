<?php

Kirby::plugin('bnomei/securityheaders', [
    'options' => [
        'enabled' => true,
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
    'pageMethods' => [
        'nonce' => function($string, $plain = false) {
            $n = \Bnomei\SecurityHeaders::nonce($string);
            if($plain && $n) {
                return $n;
            }
            else if(!$plain && $n) {
                $n = 'nonce="'.$n.'"';
            }
            return $n;
        }
    ]
]);
