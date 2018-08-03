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
        'nounces' => [],
        'hashes' => []
    ],
    'snippets' => [
        'plugin-securityheaders' => __DIR__ . '/snippets/securityheaders.php',
    ],
    'pageMethods' => [
        'nonce' => function($string) {
            $n = \Bnomei\SecurityHeaders::nonce($string);
            if($n) {
                $n = 'nonce="'.$n.'"';
            }
            return $n;
        }
    ]
]);
