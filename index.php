<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('bnomei/securityheaders', [
    'options' => [
        'enabled' => true,
        'seed' => function () {
            return Url::stripPath(site()->url());
        },
        'headers' => [
            "X-Powered-By" => "", // unset
            "X-Frame-Options" => "SAMEORIGIN",
            "X-XSS-Protection" => "1; mode=block",
            "X-Content-Type-Options" => "nosniff",
            "strict-transport-security" => "max-age=31536000; includeSubdomains",
            "Referrer-Policy" => "no-referrer-when-downgrade",
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
        'setter' => function (\Bnomei\SecurityHeaders $instance): void {
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
            if (option('bnomei.securityheaders.enabled')) {
                \Bnomei\SecurityHeaders::singleton()->sendHeaders();
            }
        },
    ],
    'pageMethods' => [
        'nonce' => function (string $key): string {
            return \Bnomei\SecurityHeaders::singleton()->getNonce($key);
        },
        'nonceAttr' => function (string $key): string {
            return implode(
                [
                    'nonce="',
                    \Bnomei\SecurityHeaders::singleton()->getNonce($key),
                    '"',
                ]
            );
        },
    ],
    'siteMethods' => [
        'nonce' => function (): ?string {
            return \Bnomei\SecurityHeaders::singleton()->getNonce(Url::stripPath(site()->url()));
        },
        'nonceAttr' => function (): string {
            return implode(
                [
                    'nonce="',
                    \Bnomei\SecurityHeaders::singleton()->getNonce(Url::stripPath(site()->url())),
                    '"',
                ]
            );
        },
    ],
]);
