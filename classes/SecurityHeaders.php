<?php

namespace Bnomei;

use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

class SecurityHeaders
{
    private static function enabled()
    {
        $inPanel = static::isPanel() ? option('bnomei.securityheaders.enabled.panel') : true;
        return option('bnomei.securityheaders.enabled') && $inPanel && !static::isWebpack() && !static::isLocalhost();
    }

    private static function isPanel()
    {
        return strpos(
            kirby()->request()->url()->toString(),
            kirby()->urls()->panel
          ) !== false;
    }

    public static function headers($headers)
    {
        if (!static::enabled()) {
            return;
        }
        $options = option('bnomei.securityheaders.headers', []);
        $optionsKV = array_map(function ($k, $v) {
            return [
                'name'  => $k,
                'value' => $v,
            ];
        }, array_keys($options), $options);
        $headers = array_merge($headers, $optionsKV);

        foreach ($headers as $h) {
            header(sprintf('%s: %s', $h['name'], $h['value']));
        }
    }

    private static $nonces = null;
    public static function nonce($string, $value = null)
    {
        if (!static::$nonces) {
            static::$nonces = [];
        }
        if ($value && is_string($value)) {
            static::$nonces[$string] = $value;
        }
        return \Kirby\Toolkit\A::get(static::$nonces, $string);
    }

    private static function isWebpack()
    {
        return !!(
            isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && $_SERVER['HTTP_X_FORWARDED_FOR'] == 'webpack'
        );
    }

    private static function isLocalhost()
    {
        return in_array($_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ));
    }

    public static function apply()
    {
        if (!static::enabled()) {
            return;
        }

        // https://github.com/Martijnc/php-csp
        $policy = new ContentSecurityPolicyHeaderBuilder();

        $csp = option('bnomei.securityheaders.csp', []);
        if (!$csp) {
            $sourcesetID = kirby()->site()->title()->value();
            $policy->defineSourceSet($sourcesetID, [kirby()->site()->url()]);

            $directives = [
                ContentSecurityPolicyHeaderBuilder::DIRECTIVE_DEFAULT_SRC,
                ContentSecurityPolicyHeaderBuilder::DIRECTIVE_STYLE_SRC,
                ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC,
                ContentSecurityPolicyHeaderBuilder::DIRECTIVE_IMG_SRC,
                ContentSecurityPolicyHeaderBuilder::DIRECTIVE_FONT_SRC,
                ContentSecurityPolicyHeaderBuilder::DIRECTIVE_CONNECT_SRC,
            ];
            foreach ($directives as $d) {
                $policy->addSourceSet($d, $sourcesetID);
            }
        } elseif (is_callable($csp)) {
            $policy = $csp($policy);
        }

        $nc = ['loadjs.min.js', 'loadjs.min.js-fn', 'webfontloader.js']; // https://github.com/bnomei/kirby3-htmlhead
        $nc = array_merge($nc, option('bnomei.securityheaders.nonces', []));
        foreach ($nc as $id) {
            $nonceArr = [$id, time(), \filemtime(__FILE__), kirby()->roots()->assets()];
            shuffle($nonceArr);
            $nonce = 'nonce-'.base64_encode(sha1(implode('', $nonceArr)));
            static::nonce($id, $nonce);
            $policy->addNonce(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, $nonce);
        }
        foreach (option('bnomei.securityheaders.hashes', []) as $h) {
            $policy->addHash(ContentSecurityPolicyHeaderBuilder::HASH_SHA_256, $h);
            // hash(ContentSecurityPolicyHeaderBuilder::HASH_SHA_256, $script, true)
        }

        static::headers($policy->getHeaders(true));
    }
}
