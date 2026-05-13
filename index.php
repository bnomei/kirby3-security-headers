<?php

use Bnomei\SecurityHeaders;
use Kirby\Http\Url;

@include_once __DIR__.'/vendor/autoload.php';

Kirby::plugin('bnomei/securityheaders', [
    'options' => [
        'enabled' => null, // null => auto-detection: disable in debug-mode, panel and api
        'legacy' => false, // true -> disable deprecated legacy header generation
        'seed' => function () {
            return Url::stripPath(site()->url());
        },
        'headers' => function () {
            return SecurityHeaders::HEADERS_DEFAULT;
        },
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

            return SecurityHeaders::LOADER_DEFAULT;
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
                    htmlspecialchars((string) SecurityHeaders::singleton()->getNonce($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
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
                    htmlspecialchars((string) SecurityHeaders::singleton()->getNonce(Url::stripPath(site()->url())), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    '"',
                ]
            );
        },
    ],
]);
