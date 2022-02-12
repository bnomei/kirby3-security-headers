<?php

use Bnomei\SecurityHeaders;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('bnomei/securityheaders', [
    'options' => [
        'enabled' => null, // null => disable in panel and api
        'seed' => function () {
            return Url::stripPath(site()->url());
        },
        'headers' => [
            "X-Powered-By" => "", // unset
            "X-Frame-Options" => "DENY",
            "X-XSS-Protection" => "1; mode=block",
            "X-Content-Type-Options" => "nosniff",
            "strict-transport-security" => "max-age=31536000; includeSubdomains; preload",
            "Referrer-Policy" => "no-referrer-when-downgrade",
            "Permissions-Policy" => 'interest-cohort=()', // flock-off
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Feature-Policy
            "Feature-Policy" => [
                "accelerometer 'none'",
                "ambient-light-sensor 'none'",
                "autoplay 'none'",
                "battery 'none'",
                "camera 'none'",
                "display-capture 'none'",
                "document-domain 'none'",
                "encrypted-media 'none'",
                "execution-while-not-rendered 'none'",
                "execution-while-out-of-viewport 'none'",
                "fullscreen 'none'",
                "geolocation 'none'",
                "gyroscope 'none'",
                "layout-animations 'none'",
                "legacy-image-formats 'none'",
                "magnetometer 'none'",
                "microphone 'none'",
                "midi 'none'",
                "navigation-override 'none'",
                "oversized-images 'none'",
                "payment 'none'",
                "picture-in-picture 'none'",
                "publickey-credentials 'none'",
                "sync-xhr 'none'",
                "usb 'none'",
                "wake-lock 'none'",
                "xr-spatial-tracking 'none'",
            ],
        ],
        'loader' => function () {
            // https://github.com/paragonie/csp-builder#example
            // null if you do NOT want to use default and/or just the setter
            /*
                return null;
             */
            // return path of file (json or yaml)
            // or an array of options for the cspbuilder
            /*
                return [...];
                return kirby()->roots()->site() . '/your-csp.json';
                return kirby()->roots()->site() . '/your-csp.yml';
            */
            // otherwise forward the default file from this plugin
            return __DIR__ . '/loader.json';
        },
        'setter' => function (SecurityHeaders $instance): void {
            // https://github.com/paragonie/csp-builder#build-a-content-security-policy-programmatically
            /*
                $csp = $instance->csp();
                $nonce = $instance->setNonce('my-inline-script');
                $csp->nonce('script-src', $nonce);
            */
            // in your template retrieve it again with
            /*
                $nonce = $page->nonce('my-inline-script');
                => `THIS-IS-THE-NONCE`
                $attr = $page->nonceAttr('my-inline-script');
                => `nonce="THIS-IS-THE-NONCE"`
            */
        },
    ],
    'hooks' => [
        'route:before' => function (): void {
            SecurityHeaders::singleton()->sendHeaders();
        },
    ],
    'pageMethods' => [
        'nonce' => function (string $key): ?string {
            return SecurityHeaders::singleton()->getNonce($key);
        },
        'nonceAttr' => function (string $key): string {
            return implode(
                [
                    'nonce="',
                    SecurityHeaders::singleton()->getNonce($key),
                    '"',
                ]
            );
        },
    ],
    'siteMethods' => [
        'nonce' => function (): ?string {
            return SecurityHeaders::singleton()->getNonce(Url::stripPath(site()->url()));
        },
        'nonceAttr' => function (): string {
            return implode(
                [
                    'nonce="',
                    SecurityHeaders::singleton()->getNonce(Url::stripPath(site()->url())),
                    '"',
                ]
            );
        },
    ],
]);
