# Kirby Content Security Policy Header

[![Kirby 5](https://flat.badgen.net/badge/Kirby/5?color=ECC748)](https://getkirby.com)
![PHP 8.2](https://flat.badgen.net/badge/PHP/8.2?color=4E5B93&icon=php&label)
![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-security-headers?color=ae81ff&icon=github&label)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-security-headers?color=272822&icon=github&label)
[![Coverage](https://flat.badgen.net/codeclimate/coverage/bnomei/kirby3-security-headers?icon=codeclimate&label)](https://codeclimate.com/github/bnomei/kirby3-security-headers)
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-security-headers?icon=codeclimate&label)](https://codeclimate.com/github/bnomei/kirby3-security-headers/issues)
[![Discord](https://flat.badgen.net/badge/discord/bnomei?color=7289da&icon=discord&label)](https://discordapp.com/users/bnomei)
[![Buymecoffee](https://flat.badgen.net/badge/icon/donate?icon=buymeacoffee&color=FF813F&label)](https://www.buymeacoffee.com/bnomei)

Kirby Plugin for easier Content Security Policy (CSP) Headers setup.

## Installation

- unzip [master.zip](https://github.com/bnomei/kirby3-security-headers/archive/master.zip) as folder
  `site/plugins/kirby3-security-headers` or
- `git submodule add https://github.com/bnomei/kirby3-security-headers.git site/plugins/kirby3-security-headers` or
- `composer require bnomei/kirby3-security-headers`

## Default CSP Headers

The following headers will be applied by default, you do not need to set them explicitly. They provide a good starting
point for most websites and ensure a sane level of security.

```yaml
X-Powered-By:                 "" # unset
X-Frame-Options:              "SAMEORIGIN"
X-XSS-Protection:             "1; mode=block"
X-Content-Type-Options:       "nosniff"
Strict-Transport-Security:    "max-age=31536000; includeSubdomains"
Referrer-Policy:              "no-referrer-when-downgrade"
Permissions-Policy:           "interest-cohort=()" # flock-off
# + various Feature-Policies...
```

> [!TIP]
> See `\Bnomei\SecurityHeaders::HEADERS_DEFAULT` for more details.

## Zero Configuration? Almost.

Installing the plugin is enough to protect your website. A `route:before`-hook takes care of sending the CSP headers
automatically. But you will most likely need to customize the CSP headers when using third-party services like

- Content Delivery Networks (CDN),
- analytic scripts like Google-Tag-Manager/Fathom/Matomo/Piwik/Plausible/Umami,
- embedding external media like from Youtube/Vimeo/Instagram/X,
- external newsletter sign-up forms from Brevo/Mailchimp/Mailjet/Mailcoach,
- any other third-party service not hosted on your domain or subdomain or
- when using inline `<script>` and/or `<style>`.

> [!TIP]
> The plugin will automatically disable itself on local setups to not get in your way while developing. To test the CSP headers locally, you can use the `'bnomei.securityheaders.enabled' => true,` option to enforce sending the headers.

## Customizing CSP Headers & Nonces

You can customize the CSP headers by providing a custom **Loader** and/or **Setter** via the Kirby config.

### Loader

The Loader is used to initially create the CSP-Builder object with a given set of mostly static data. You can provide a
path to a file, return an array or `null` to create blank CSP-Builder object.

> [!TIP]
> See `\Bnomei\SecurityHeaders::LOADER_DEFAULT` for more details.

> [!WARNING]
> Consider using a custom loader ONLY if you find yourself adding a lot of configurations in the Setter. The default
> loader is already quite extensive and should cover most use-cases.

### Setter

The **Setter** is applied after the **Loader**. Use it to add dynamic stuff like rules for external services, hashes and
nonces.

**/site/config/config.php**

```php
<?php
return [
    'bnomei.securityheaders.setter' => function ($instance) {
        // https://github.com/paragonie/csp-builder
        // #build-a-content-security-policy-programmatically
        /** @var ParagonIE\CSPBuilder\CSPBuilder $csp */
        $csp = $instance->csp();
        
        // allowing all inline scripts and styles is
        // not recommended, try using nonces instead
        // $csp->setAllowUnsafeEval('script-src', true);
        // $csp->setAllowUnsafeInline('script-src', true);
        // $csp->setAllowUnsafeInline('style-src', true);
        
        // youtube
        $csp->addSource('frame', 'https://www.youtube.com');
        $csp->addSource('frame', 'https://youtube.com');
        $csp->addSource('image', 'https://ggpht.com');
        $csp->addSource('image', 'https://youtube.com');
        $csp->addSource('image', 'https://ytimg.com');
        $csp->addSource('script', 'https://google.com');
        $csp->addSource('script', 'https://youtube.com');

        // vimeo
        $csp->addSource('frame', 'player.vimeo.com');
        $csp->addSource('image', 'i.vimeocdn.com');
        $csp->addSource('script', 'f.vimeocdn.com');
        $csp->addSource('source', 'player.vimeo.com');
        $csp->addSource('style', 'f.vimeocdn.com');
    },
    // other options...
];
```

> [!TIP]
> You can define nonces in the `Setter`-option and later retrieved using `$page->nonce(...)` or `$page->nonceAttr(...)`.
> But the plugin also provides a single nonce for frontend use out of the box.

## Nonces

For convenience the plugin also provides you with a single
*frontend nonce* to use as attribute in `<link>`, `<style>` and `<script>` elements. You can retrieve the nonce with
`site()->nonce()`.

```php
<script nonce="<?= site()->nonce() ?>">
/* ... */
</script>
```

> [!NOTE]
> This plugin automatically registers the nonce that Kirby creates for its panel (in case that ever might be needed).

## Disabling the plugin

The CSP headers will be sent before Kirby renders HTML using a `route:before` hook but the plugin will be automatically
disabled if one the following conditions apply:

- Kirby determines it is
  a [local setup](https://github.com/getkirby/kirby/blob/03d6e96aa27f631e5311cb6c2109e1510505cab7/src/Cms/System.php#L190)
  or
- The plugins setting `bnomei.securityheaders.enabled` is set to `false`.

> [!WARNING]
> By default, CSP headers are never sent for any Kirby Panel, API and Media routes.

## Legacy headers

It is known that having both Content-Security-Policy and X-Content-Security-Policy or X-Webkit-CSP causes unexpected behaviors on certain versions of browsers. Please avoid using deprecated X-* headers [[1]](https://content-security-policy.com/).
[[2]](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html#warning)

It is also recommended that you use Content-Security-Policy instead of XSS filtering.
[[3]](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-XSS-Protection)
[[4]](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html#x-xss-protection)

## Settings

| bnomei.securityheaders. | Default           | Description                                                     |            
|-------------------------|-------------------|-----------------------------------------------------------------|
| enabled                 | `null/true/false` | will set headers                                                |
| legacy                  | `false`           | disables setting deprecated legacy headers (see Legacy headers) |
| seed                    | `callback`        | returns a unique seed for frontend nonces on every request      |
| headers                 | `callback`        | array of sensible default values                                |
| loader                  | `callback`        | returning filepath or array                                     |
| setter                  | `callback`        | instance which allows customizing the CSPBuilder                |

## Dependencies

- [paragonie/csp-builder](https://github.com/paragonie/csp-builder)

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it
in a production environment. If you find any issues,
please [create a new issue](https://github.com/bnomei/kirby3-security-headers/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or
any other form of hate speech.
