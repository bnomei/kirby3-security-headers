# Kirby 3 Content Security Policy Header

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-security-headers?color=ae81ff)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-security-headers?color=272822)
[![Build Status](https://flat.badgen.net/travis/bnomei/kirby3-security-headers)](https://travis-ci.com/bnomei/kirby3-security-headers)
[![Coverage Status](https://flat.badgen.net/coveralls/c/github/bnomei/kirby3-security-headers)](https://coveralls.io/github/bnomei/kirby3-security-headers) 
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-security-headers)](https://codeclimate.com/github/bnomei/kirby3-security-headers)  
[![Twitter](https://flat.badgen.net/badge/twitter/bnomei?color=66d9ef)](https://twitter.com/bnomei)

Kirby 3 Plugin for easier Security Headers setup.

> 🔐 Why should you use this plugin? Because security matters. Protecting your own or your clients websites and their customers data is important.

1. [Automatic Setup](https://github.com/bnomei/kirby3-security-headers#automatic)
1. [Setup: Headers](https://github.com/bnomei/kirby3-security-headers#headers)
1. [Setup: Loader](https://github.com/bnomei/kirby3-security-headers#loader)
1. [Setup: Setter](https://github.com/bnomei/kirby3-security-headers#setter)
1. [Frontend Nonce](https://github.com/bnomei/kirby3-security-headers#frontend-nonce)
1. [Settings](https://github.com/bnomei/kirby3-security-headers#settings)

## Commerical Usage

> <br>
> <b>Support open source!</b><br><br>
> This plugin is free but if you use it in a commercial project please consider to sponsor me or make a donation.<br>
> If my work helped you to make some cash it seems fair to me that I might get a little reward as well, right?<br><br>
> Be kind. Share a little. Thanks.<br><br>
> &dash; Bruno<br>
> &nbsp; 

| M | O | N | E | Y |
|---|----|---|---|---|
| [Github sponsor](https://github.com/sponsors/bnomei) | [Patreon](https://patreon.com/bnomei) | [Buy Me a Coffee](https://buymeacoff.ee/bnomei) | [Paypal dontation](https://www.paypal.me/bnomei/15) | [Buy a Kirby license using this affiliate link](https://a.paddle.com/v2/click/1129/35731?link=1170) |

## Installation

- unzip [master.zip](https://github.com/bnomei/kirby3-security-headers/archive/master.zip) as folder `site/plugins/kirby3-security-headers` or
- `git submodule add https://github.com/bnomei/kirby3-security-headers.git site/plugins/kirby3-security-headers` or
- `composer require bnomei/kirby3-security-headers`

## Setup

### Automatic

A `route:before`-hook takes care of setting the headers automatically unless one of the following conditions applies:

- Kirbys **global** debug mode is `true`
- Kirby determins it is a [local setup](https://github.com/getkirby/kirby/blob/03d6e96aa27f631e5311cb6c2109e1510505cab7/src/Cms/System.php#L190)
- the plugins setting `enabled` is set to `false`

### Header

The following headers will be applied by default. You can override them in the config file.

**/site/config/config.php**
```php
<?php
return [
    'bnomei.securityheaders.headers' => [
        "X-Powered-By" => "", // unset
        "X-Frame-Options" => "SAMEORIGIN",
        "X-XSS-Protection" => "1; mode=block",
        "X-Content-Type-Options" => "nosniff",
        "strict-transport-security" => "max-age=31536000; includeSubdomains",
        "Referrer-Policy" => "no-referrer-when-downgrade",
        "Permissions-Policy" => 'interest-cohort=()', // flock-off,
        // ... FEATURE POLICIES
    // other options...
];
```

### Loader

The Loader is used to initally create the CSPBuilder object with a given set of data. You skip that, forward a file to load, provide an array or [use the default loader file](https://github.com/bnomei/kirby3-security-headers/blob/master/loader.json). Using a custom file is recommended when for example adding additional font-src for google web fonts. 

**/site/config/config.php**
```php
<?php
return [
    'bnomei.securityheaders.loader' => function () {
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
    // other options...
];
```

### Setter

The Setter is applied after the Loader. Use it to add dynamic stuff like hashes and nonces. 

**/site/config/config.php**
```php
<?php
return [
    'bnomei.securityheaders.setter' => function (\Bnomei\SecurityHeaders $instance) {
        // https://github.com/paragonie/csp-builder#build-a-content-security-policy-programmatically
        /** @var ParagonIE\CSPBuilder\CSPBuilder $csp */
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
    // other options...
];
```

> TIP: nonces are set in the `setter` and later retrieved using `$page->nonce(...)` or `$page->nonceAttr(...)`.

## Panel and Frontend Nonces

This plugin automatically registers Kirbys nonce for the panel. For convenience it also provides you with a single *frontend nonce* to use as attribute in `<link>`, `<style>` and `<script>` elements. You can retrieve the nonce with `site()->nonce()` and the full attribute with `site()->nonceAttr()`.

```php
<?php ?>

<script nonce="<?= site()->nonce() ?>">
// ...
</script>

<style <?= site()->nonceAttr() ?>>

</style>
```

> TIP: The [srcset plugin](https://github.com/bnomei/kirby3-srcset/) uses that *frontend nonce* as well.

## Settings

| bnomei.securityheaders.   | Default        | Description               |            
|---------------------------|----------------|---------------------------|
| enabled | `true or false or 'force'` | will set headers |
| seed | `callback` | returns a seed for frontend nonce |
| headers | `array` | of sensible default values. modify as needed. |
| loader | `callback` | returning filepath or array |
| setter | `callback` |  instance which allows customizing the CSPBuilder |

## Dependencies
 
 - [paragonie/csp-builder](https://github.com/paragonie/csp-builder)

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-security-headers/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.
