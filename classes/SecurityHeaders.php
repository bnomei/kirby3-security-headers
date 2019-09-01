<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Toolkit\A;
use Kirby\Toolkit\F;
use Kirby\Toolkit\Mime;
use ParagonIE\CSPBuilder\CSPBuilder;
use function header;

final class SecurityHeaders
{
    /*
     * @var array
     */
    private $options;

    /*
     * @var ParagonIE\CSPBuilder\CSPBuilder
     */
    private $cspBuilder;

    /*
     * @var array
     */
    private $nonces;

    public function __construct(array $options = [])
    {
        $isPanel = strpos(
                kirby()->request()->url()->toString(),
                kirby()->urls()->panel
            ) !== false;
        $panelHasNonces =  method_exists(kirby()->system(), 'nonces');
        $enabled = !kirby()->system()->isLocal() && ($isPanel && $panelHasNonces);

        $defaults = [
            'debug' => option('debug'),
            'loader' => option('bnomei.securityheaders.loader'),
            'enabled' => option('enabled', $enabled),
            'headers' => option('bnomei.securityheaders.headers'),
            'panelnonces' => $panelHasNonces ? kirby()->system()->nonces() : [],
            'setter' => option('bnomei.securityheaders.setter'),
        ];
        $this->options = array_merge($defaults, $options);
        $this->nonces = [];

        foreach ($this->options as $key => $call) {
            if (is_callable($call) && in_array($key, ['loader', 'enabled', 'headers'])) {
                $this->options[$key] = $call();
            }
        }
    }

    /**
     * @return array|misc
     */
    public function option(?string $key = null)
    {
        if ($key) {
            return A::get($this->options, $key);
        }
        return $this->options;
    }

    /**
     * @return string|null
     */
    public function getNonce(string $key): ?string
    {
        return A::get($this->nonces, $key);
    }

    /**
     * @param string
     */
    public function setNonce(string $key): string
    {
        $nonceArr = [$key, time(), filemtime(__FILE__), kirby()->roots()->assets()];
        shuffle($nonceArr);
        $nonce = 'nonce-' . base64_encode(sha1(implode('', $nonceArr)));

        $this->nonces[$key] = $nonce;
        return $nonce;
    }

    /**
     * @return mixed
     */
    public function csp()
    {
        return $this->cspBuilder;
    }

    /**
     * @param null $data
     * @return CSPBuilder
     */
    public function load($data = null): CSPBuilder
    {
        if (is_null($data)) {
            $data = $this->option('loader');
        }

        if (is_string($data) && F::exists($data)) {
            $mime = F::mime($data);
            $data = F::read($data);
            if (in_array($mime, A::get(Mime::types(), 'json'))) {
                $data = Json::decode($data);
            } elseif (A::get(Mime::types(), 'yaml') && in_array($mime, A::get(Mime::types(), 'yaml'))) {
                // TODO: kirby has no mime yaml yet. pending issue.
                $data = Yaml::decode($data);
            }
        }
        if (is_array($data)) {
            $this->cspBuilder = CSPBuilder::fromArray($data);
        } else {
            $this->cspBuilder = new CSPBuilder();
        }

        // add panel nonces
        $panelnonces = $this->option('panelnonces');
        foreach ($panelnonces as $nonce) {
            // TODO: kirby has no panel nonces yet. pending issue.
            $this->cspBuilder->nonce('script-src', $nonce);
        }

        return $this->cspBuilder;
    }

    /**
     *
     */
    public function applySetter()
    {
        // additional setters
        $csp = $this->option('setter');
        if (is_callable($csp)) {
            $csp($this);
        }
    }

    /**
     * @return bool
     */
    public function sendHeaders(): bool
    {
        if ($this->option('debug') || $this->option('enabled') !== true) {
            return false;
        }

        // from config
        $headers = $this->option('headers');
        foreach ($headers as $key => $value) {
            header($key . ': ' . $value);
        }

        // from cspbuilder
        if ($this->cspBuilder) {
            $this->cspBuilder->sendCSPHeader();
        }
        return true;
    }

    /**
     * @param string $filepath
     * @return bool
     */
    public function saveApache(string $filepath): bool
    {
        $this->cspBuilder->saveSnippet($filepath, CSPBuilder::FORMAT_APACHE);
        return F::exists($filepath);
    }

    /**
     * @param string $filepath
     * @return bool
     */
    public function saveNginx(string $filepath): bool
    {
        $this->cspBuilder->saveSnippet($filepath, CSPBuilder::FORMAT_NGINX);
        return F::exists($filepath);
    }

    /*
     * @var SecurityHeaders
     */
    private static $singleton;

    /**
     * @param array $options
     * @return SecurityHeaders
     * @codeCoverageIgnore
     */
    public static function singleton(array $options = []): SecurityHeaders
    {
        if (self::$singleton) {
            return self::$singleton;
        }

        $sec = new SecurityHeaders($options);
        $sec->load();
        $sec->applySetter();
        self::$singleton = $sec;

        return self::$singleton;
    }
}
