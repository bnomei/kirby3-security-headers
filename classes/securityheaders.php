<?php

namespace Bnomei;

class SecurityHeaders
{
    private static function enabled()
    {
        return option('bnomei.securityheaders.enabled') && !static::isWebpack() && !static::isLocalhost();
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
}
