<?php

declare(strict_types=1);

namespace Bnomei;

use Closure;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Filesystem\F;
use Kirby\Filesystem\Mime;
use Kirby\Toolkit\A;
use ParagonIE\CSPBuilder\CSPBuilder;

use function header;

class SecurityHeaders
{
    const HEADERS_DEFAULT = [
        'X-Powered-By' => '', // unset
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'strict-transport-security' => 'max-age=31536000; includeSubdomains; preload',
        'Referrer-Policy' => 'no-referrer-when-downgrade',
        'Permissions-Policy' => 'interest-cohort=()', // flock-off
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Feature-Policy
        'Feature-Policy' => [
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
    ];

    const LOADER_DEFAULT = [
        'report-only' => false,
        'base-uri' => [
            'self' => true,
        ],
        'default-src' => [
            'self' => true,
        ],
        'connect-src' => [
            'self' => true,
        ],
        'font-src' => [
            'self' => true,
        ],
        'form-action' => [
            'allow' => [],
            'self' => true,
        ],
        'frame-ancestors' => [],
        'frame-src' => [
            'allow' => [],
            'self' => false,
        ],
        'img-src' => [
            'self' => true,
            'data' => true,
        ],
        'media-src' => [],
        'object-src' => [],
        'plugin-types' => [],
        'script-src' => [
            'allow' => [],
            'hashes' => [],
            'self' => true,
            'unsafe-inline' => false,
            'unsafe-eval' => false,
        ],
        'style-src' => [
            'self' => true,
        ],
        'upgrade-insecure-requests' => true,
        'worker-src' => [
            'allow' => [],
            'self' => false,
        ],
    ];

    private array $options;

    private CSPBuilder $cspBuilder;

    private array $nonces = [];

    public function __construct(array $options = [])
    {
        $url = kirby()->request()->url()->toString();
        $isPanel = str_contains($url, kirby()->urls()->panel());
        $isApi = str_contains($url, kirby()->urls()->api());
        $enabled = ! kirby()->system()->isLocal() && ! $isPanel && ! $isApi;

        $this->options = array_merge([
            'debug' => option('debug'),
            'enabled' => option('bnomei.securityheaders.enabled', $enabled),
            'panel' => $isPanel,
            'panelnonces' => ['panel' => kirby()->nonce()],
            'seed' => option('bnomei.securityheaders.seed'),
            'headers' => option('bnomei.securityheaders.headers'),
            'loader' => option('bnomei.securityheaders.loader'),
            'setter' => option('bnomei.securityheaders.setter'),
            'legacy' => option('bnomei.securityheaders.legacy'),
        ], $options);

        foreach ($this->options as $key => $call) {
            if ($call instanceof Closure && in_array($key, ['loader', 'enabled', 'headers', 'seed'])) {
                $this->options[$key] = $call();
            }
        }

        $this->load();
        $this->applySetter();
    }

    public function option(?string $key = null): mixed
    {
        if ($key) {
            return A::get($this->options, $key);
        }

        return $this->options;
    }

    public function getNonce(string $key): ?string
    {
        return A::get($this->nonces, $key);
    }

    public function setNonce(string $key): string
    {
        $nonceArr = [$key, time(), filemtime(__FILE__), (string) kirby()->roots()->index()];
        shuffle($nonceArr);
        $nonce = base64_encode(sha1(implode('', $nonceArr)));

        $this->nonces[$key] = $nonce;

        return $nonce;
    }

    public function csp(): CSPBuilder
    {
        return $this->cspBuilder;
    }

    public function load(array|string|null $data = null): CSPBuilder
    {
        // load default if is null
        if (is_null($data)) {
            $data = $this->option('loader');
        }

        if (is_string($data)) {
            $data = $this->loadFromFile($data);
        }

        $this->cspBuilder = is_array($data) ? CSPBuilder::fromArray($data) : new CSPBuilder;

        $this->addNonceForSelf();
        $this->addPanelNonces();

        return $this->cspBuilder;
    }

    public function loadFromFile(string $data): mixed
    {
        if (! F::exists($data)) {
            return null;
        }

        $mime = F::mime($data);
        $data = F::read($data);

        if (in_array($mime, A::get(Mime::types(), 'json'))) {
            $data = Json::decode($data);
        } elseif (A::get(Mime::types(), 'yaml') && in_array($mime, A::get(Mime::types(), 'yaml'))) {
            $data = Yaml::decode($data);
        }

        return $data;
    }

    public function addNonceForSelf(): void
    {
        $seed = $this->option('seed');
        if (! $seed || ! is_string($seed)) {
            return;
        }
        $seed = trim($seed);
        if (! empty($seed)) {
            $nonce = $this->setNonce($seed);
            $this->cspBuilder->nonce('script-src', $nonce);
            $this->cspBuilder->nonce('style-src', $nonce);
        }
    }

    public function addPanelNonces(): void
    {
        $seed = $this->option('seed');
        if (! $seed || ! is_string($seed)) {
            return;
        }

        if (! $this->option('panel')) {
            $panelnonces = (array) $this->option('panelnonces');
            foreach ($panelnonces as $nonce) {
                if (! is_string($nonce)) {
                    continue;
                }
                $this->cspBuilder->nonce('img-src', $nonce);
                $this->cspBuilder->nonce('script-src', $nonce);
                $this->cspBuilder->nonce('style-src', $nonce);
            }
        }
    }

    public function applySetter(): void
    {
        $csp = $this->option('setter');
        if ($csp instanceof Closure) {
            $csp($this);
        }
    }

    public function sendHeaders(): bool
    {
        if ($this->option('enabled') === false) {
            return false;
        }

        // from config
        $headers = (array) $this->option('headers');
        foreach ($headers as $key => $value) {
            $value = is_array($value) ? implode('; ', $value) : $value;
            header(strval($key).': '.strval($value));
        }

        return $this->cspBuilder->sendCSPHeader($this->option('legacy'));
    }

    public function saveApache(string $filepath): bool
    {
        $this->cspBuilder->saveSnippet($filepath, CSPBuilder::FORMAT_APACHE);

        return F::exists($filepath);
    }

    public function saveNginx(string $filepath): bool
    {
        $this->cspBuilder->saveSnippet($filepath, CSPBuilder::FORMAT_NGINX);

        return F::exists($filepath);
    }

    private static ?SecurityHeaders $singleton = null;

    public static function singleton(array $options = []): SecurityHeaders
    {
        if (self::$singleton !== null) {
            return self::$singleton;
        }

        $sec = new SecurityHeaders($options);
        self::$singleton = $sec;

        return self::$singleton;
    }
}
