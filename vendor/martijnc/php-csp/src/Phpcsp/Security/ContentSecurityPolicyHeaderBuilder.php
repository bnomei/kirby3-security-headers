<?php
/**
 * Copyright 2015, Martijn Croonen.
 * All rights reserved.
 *
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */
namespace Phpcsp\Security;

/**
 * Class ContentSecurityPolicyBuilder
 *
 * A PHP helper class to dynamically construct Content Security Policy headers as defined by the W3C in the
 * Content Security Policy specification (http://www.w3.org/TR/CSP2/).
 */
class ContentSecurityPolicyHeaderBuilder
{
    /**
     * @var string
     */
    protected $headerName = 'Content-Security-Policy';

    /**
     * @var string
     */
    protected $reportOnlyHeaderName = 'Content-Security-Policy-Report-Only';

    /**
     * @var string
     */
    protected $legacyXssHeader = 'X-XSS-Protection';

    /**
     * @var string
     */
    protected $legacyFrameOptionsHeader = 'X-Frame-Options';

    /**
     * These are tokens defined by CSP and have specials meaning. They have to enclosed by quotes in the
     * CSP value string.
     *
     * @var array
     */
    protected $directiveValueTokens = ['self', 'unsafe-inline', 'unsafe-eval', 'unsafe-redirect', 'none'];

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-base-uri
     *
     * @var string
     */
    const DIRECTIVE_BASE_URI = 'base-uri';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-child-src
     *
     * @var string
     */
    const DIRECTIVE_CHILD_SRC = 'child-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-connect-src
     *
     * @var string
     */
    const DIRECTIVE_CONNECT_SRC = 'connect-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-default-src
     *
     * @var string
     */
    const DIRECTIVE_DEFAULT_SRC = 'default-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-font-src
     *
     * @var string
     */
    const DIRECTIVE_FONT_SRC = 'font-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-form-action
     *
     * @var string
     */
    const DIRECTIVE_FORM_ACTION = 'form-action';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-frame-ancestors
     *
     * @var string
     */
    const DIRECTIVE_FRAME_ANCESTORS = 'frame-ancestors';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-frame-src
     *
     * @var string
     */
    const DIRECTIVE_FRAME_SRC = 'frame-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-img-src
     *
     * @var string
     */
    const DIRECTIVE_IMG_SRC = 'img-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-media-src
     *
     * @var string
     */
    const DIRECTIVE_MEDIA_SRC = 'media-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-object-src
     *
     * @var string
     */
    const DIRECTIVE_OBJECT_SRC = 'object-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-script-src
     *
     * @var string
     */
    const DIRECTIVE_SCRIPT_SRC = 'script-src';

    /**
     * Valid CSP directive name. See http://www.w3.org/TR/CSP2/#directive-style-src
     *
     * @var string
     */
    const DIRECTIVE_STYLE_SRC = 'style-src';

    /**
     * A list of all valid CSP directives, without those (like reflected-xss) that require a defined set of values.
     *
     * @var array
     */
    protected $allowedDirectives = [
        self::DIRECTIVE_BASE_URI,
        self::DIRECTIVE_CHILD_SRC,
        self::DIRECTIVE_CONNECT_SRC,
        self::DIRECTIVE_DEFAULT_SRC,
        self::DIRECTIVE_FONT_SRC,
        self::DIRECTIVE_FORM_ACTION,
        self::DIRECTIVE_FRAME_ANCESTORS,
        self::DIRECTIVE_FRAME_SRC,
        self::DIRECTIVE_IMG_SRC,
        self::DIRECTIVE_MEDIA_SRC,
        self::DIRECTIVE_OBJECT_SRC,
        self::DIRECTIVE_SCRIPT_SRC,
        self::DIRECTIVE_STYLE_SRC
    ];

    /**
     * @var string
     */
    protected $directiveSeparator = '; ';

    /**
     * Contains all source sets that have been defined.
     *
     * @var array
     */
    protected $sourceSets = [];

    /**
     * Contains all the directives that have been defined and their respective values.
     *
     * @var array
     */
    protected $directives = [];

    /**
     * Valid value for the 'referrer' CSP directive. A user-agent should set an empty referrer value when
     * navigating away from a resource that sets the CSP 'referrer' value to 'none'.
     *
     * @var string
     */
    const REFERRER_NONE = 'none';

    /**
     * Valid value for the 'referrer' CSP directive. A user-agent should set an empty referrer value when
     * navigating to a resource that is net served over TLS from a resource that is served over TLS and sets the
     * CSP 'referrer' value to 'none-when-downgrading'.
     *
     * @var string
     */
    const REFERRER_NONE_WHEN_DOWNGRADE = 'none-when-downgrade';

    /**
     * Valid value for the 'referrer' CSP directive. A user-agent should set the resource's origin as the referrer value
     * when the resources sets the CSP 'referrer' value to 'origin'.
     *
     * @var string
     */
    const REFERRER_ORIGIN = 'origin';

    /**
     * Valid value for the 'referrer' CSP directive. A user-agent should set the resource's origin as the referrer value
     * when the resource sets the CSP 'referrer' value to 'origin-when-cross-origin' and it is navigating to a resource
     * from a different origin.
     *
     * @var string
     */
    const REFERRER_ORIGIN_WHEN_CROSS_ORIGIN = 'origin-when-cross-origin';

    /**
     * Valid value for the 'referrer' CSP directive. This it the default user-agent behaviour and could potentially
     * leak information.
     *
     * @var string
     */
    const REFERRER_UNSAFE_URL = 'unsafe-url';

    /**
     * All valid values for the 'referrer' CSP directive.
     *
     * @var array
     */
    protected $referrerValues = [
        self::REFERRER_NONE,
        self::REFERRER_NONE_WHEN_DOWNGRADE,
        self::REFERRER_ORIGIN,
        self::REFERRER_ORIGIN_WHEN_CROSS_ORIGIN,
        self::REFERRER_UNSAFE_URL,
        null
    ];

    /**
     * @var string|null
     */
    protected $referrerValue = null;

    /**
     * Valid valid value for the 'reflected-xss' CSP directive. A user agent must disable its active protections against
     * reflected cross-site scripting attacks for the protected resource. Try to avoid this value.
     *
     * @notice Same behaviour as 'X-XSS-Protection: 0'
     * @var string
     */
    const REFLECTED_XSS_ALLOW = 'allow';

    /**
     * Valid valid value for the 'reflected-xss' CSP directive. A user agent must stop rendering the protected resource
     * upon detection of reflected script, and instead act as if there was a fatal network error and no resource was
     * obtained, and report a violation.
     *
     * @notice Same behaviour as ' X-XSS-Protection: 1; mode=block'
     * @var string
     */
    const REFLECTED_XSS_BLOCK = 'block';

    /**
     * Valid valid value for the 'reflected-xss' CSP directive. A user agent must enable its active protections against
     * reflected cross-site scripting attacks for the protected resource. This might result in filtering script that is
     * believed to be reflected being filtered or selectively blocking script execution.
     *
     * @notice Same behaviour as 'X-XSS-Protection: 1'
     * @var string
     */
    const REFLECTED_XSS_FILTER = 'filter';

    /**
     * All valid values for the 'reflected-xss' CSP directive. This directive related to the deprecated
     * 'X-XSS-Protection' header.
     *
     * @var array
     */
    protected $reflectedXssValues = [
        self::REFLECTED_XSS_ALLOW,
        self::REFLECTED_XSS_BLOCK,
        self::REFLECTED_XSS_FILTER,
        null
    ];

    /**
     * Valid value for the 'X-Frame-Options' header. UA's will refuse to load any resource that sets the value of this
     * header to 'DENY' as part of Frame, iFrame, Object, Applet, or embed tag.
     *
     * @var string
     */
    const FRAME_OPTION_DENY  = 'DENY';

    /**
     * Valid value for the 'X-Frame-Options' header. UA's will refuse to load any resource that sets the value of this
     * header to 'SAMEORIGIN' as part of Frame, iFrame, Object, Applet, or embed tag when the requesting resource is
     * from a different origin.
     *
     * @var string
     */
    const FRAME_OPTION_SAME_ORIGIN  = 'SAMEORIGIN';

    /**
     * Valid value for the 'X-Frame-Options' header. UA's will refuse to load any resource that sets the value of this
     * header to 'ALLOW-FROM [origin]' as part of Frame, iFrame, Object, Applet, or embed tag when the requesting
     * resource is not within the specified origin.
     *
     * @var string
     */
    const FRAME_OPTION_ALLOW_FROM  = 'ALLOW-FROM %s';

    /**
     * All valid values for the 'X-Frame-Options' header.
     *
     * @var array
     */
    protected $FrameOptionsValues = [
        self::FRAME_OPTION_DENY,
        self::FRAME_OPTION_SAME_ORIGIN,
        self::FRAME_OPTION_ALLOW_FROM,
        null
    ];

    /**
     * SHA 256 hash indicator.
     *
     * @var string
     */
    const HASH_SHA_256 = 'sha256';

    /**
     * SHA 384 hash indicator.
     *
     * @var string
     */
    const HASH_SHA_384 = 'sha384';

    /**
     * SHA 512 hash indicator.
     *
     * @var string
     */
    const HASH_SHA_512 = 'sha512';

    /**
     * Supported hash algorithms.
     *
     * @var array
     */
    protected $hashAlgorithmValues = [
        self::HASH_SHA_256,
        self::HASH_SHA_384,
        self::HASH_SHA_512
    ];

    /**
     * Holds the value for the 'X-Frame-Options' header when set.
     *
     * @var string|null
     */
    protected $frameOptionsValue = null;

    /**
     * The 'reflected-xss' CSP directive value.
     *
     * @notice Defines the same behaviour as the 'X-XSS-Protection' header
     * @var null
     */
    protected $reflectedXssValue = null;

    /**
     * When false, a report-only CSP header will be served. This means the policy will not be enforced by the UA but
     * violations will be reported.
     *
     * @var bool
     */
    protected $enforcePolicy = true;

    /**
     * When set to a valid URI, the UA will send violation reports to this URI.
     *
     * @var null
     */
    protected $reportUri = null;

    /**
     * @param bool $enforce
     */
    public function enforcePolicy($enforce)
    {
        $this->enforcePolicy = $enforce;
    }

    /**
     * @return bool
     */
    public function shouldEnforcePolicy()
    {
        return $this->enforcePolicy;
    }

    /**
     * @param string $policy
     * @throws InvalidValueException
     */
    public function setReferrerPolicy($policy)
    {
        if (!in_array($policy, $this->referrerValues)) {
            throw new InvalidValueException(sprintf(
                'Tried to set the CSP referrer policy to "%s" which is an invalid value.',
                $policy
            ));
        }

        $this->referrerValue = $policy;
    }

    /**
     * @param string $policy
     * @throws InvalidValueException
     */
    public function setReflectedXssPolicy($policy)
    {
        if (!in_array($policy, $this->reflectedXssValues)) {
            throw new InvalidValueException(sprintf(
                'Tried to set the CSP reflected XSS policy to "%s" which is an invalid value.',
                $policy
            ));
        }

        $this->reflectedXssValue = $policy;
    }

    /**
     * @param string $policy
     * @param string $origin
     * @throws InvalidValueException
     */
    public function setFrameOptions($policy, $origin = '')
    {
        if (!in_array($policy, $this->FrameOptionsValues)) {
            throw new InvalidValueException(sprintf(
                'Tried to set the X-Frame-Options header to "%s" which is an invalid value.',
                $policy
            ));
        }

        if ($policy == static::FRAME_OPTION_ALLOW_FROM) {
            $origin = static::extractOrigin($origin);

            if (!$origin) {
                throw new InvalidOriginException();
            }
        }

        $this->frameOptionsValue = trim(sprintf($policy, $origin));
    }

    /**
     * @param string $uri
     */
    public function setReportUri($uri)
    {
        $this->reportUri = $uri;
    }

    /**
     * @param string $name
     * @param array $expressions
     */
    public function defineSourceSet($name, array $expressions)
    {
        $this->sourceSets[$name] = $expressions;
    }

    /**
     * @param string $directive
     * @param string $nonce
     * @throws InvalidDirectiveException
     */
    public function addNonce($directive, $nonce)
    {
        if (!in_array($directive, $this->allowedDirectives)) {
            throw new InvalidDirectiveException('Tried to add a CSP nonce for an invalid directive.');
        }

        if (!(isset($this->directives[$directive]) && is_array($this->directives[$directive]))) {
            $this->directives[$directive] = [];
        }

        if (!(isset($this->directives[$directive]['nonces']) && is_array($this->directives[$directive]['nonces']))) {
            $this->directives[$directive]['nonces'] = [];
        }

        $this->directives[$directive]['nonces'][] = $nonce;
    }

    /**
     * @param string $directive
     * @param string $set
     * @throws InvalidDirectiveException
     * @throws SourceSetNotFoundException
     */
    public function addSourceSet($directive, $set)
    {
        if (!in_array($directive, $this->allowedDirectives)) {
            throw new InvalidDirectiveException(
                'Tried to add a source set for an CSP invalid directive.'
            );
        }

        if (!isset($this->sourceSets[$set])) {
            throw new SourceSetNotFoundException(sprintf(
                'Tried to add "%s" as a source set for the "%s" CSP directive but the source set has not been defined',
                $set,
                $directive
            ));
        }

        if (!(isset($this->directives[$directive]) && is_array($this->directives[$directive]))) {
            $this->directives[$directive] = [];
        }

        if (!(isset($this->directives[$directive]['sets']) && is_array($this->directives[$directive]['sets']))) {
            $this->directives[$directive]['sets'] = [];
        }

        $this->directives[$directive]['sets'][] = $set;
    }

    /**
     * @param string $directive
     * @param string $expression
     * @throws InvalidDirectiveException
     */
    public function addSourceExpression($directive, $expression)
    {
        if (!in_array($directive, $this->allowedDirectives)) {
            throw new InvalidDirectiveException(
                'Tried to add a source set for an CSP invalid directive.'
            );
        }

        if (!(isset($this->directives[$directive]) && is_array($this->directives[$directive]))) {
            $this->directives[$directive] = [];
        }

        if (!(
            isset($this->directives[$directive]['expressions'])
            && is_array($this->directives[$directive]['expressions'])
        )) {
            $this->directives[$directive]['expressions'] = [];
        }

        $this->directives[$directive]['expressions'][] = $expression;
    }

    /**
     * Add a hash value to the script-src.
     *
     * @param string $type
     * @param string $hash
     * @throws InvalidDirectiveException
     */
    public function addHash($type, $hash)
    {
        $directive = self::DIRECTIVE_SCRIPT_SRC;
        if (!(isset($this->directives[$directive]) && is_array($this->directives[$directive]))) {
            $this->directives[$directive] = [];
        }

        if (!(isset($this->directives[$directive]['hashes']) && is_array($this->directives[$directive]['hashes']))) {
            $this->directives[$directive]['hashes'] = [];
        }

        $this->directives[$directive]['hashes'][$type][] = $hash;
    }

    /**
     * Returns the CSP header that should be used (enforced mode or report-only mode).
     *
     * @return string
     */
    public function getHeaderName()
    {
        if ($this->shouldEnforcePolicy()) {
            return $this->headerName;
        } else {
            return $this->reportOnlyHeaderName;
        }
    }

    /**
     * Returns the value for the CSP header based on the loaded configuration.
     *
     * @return string|null
     */
    public function getValue()
    {
        $directives = [];
        foreach ($this->directives as $name => $value) {
            $directives[] = sprintf('%s %s', $name, $this->parseDirectiveValue($value));
        }

        if (!is_null($this->reflectedXssValue)) {
            $directives[] = sprintf('%s %s', 'reflected-xss', $this->reflectedXssValue);
        }

        if (!is_null($this->referrerValue)) {
            $directives[] = sprintf('%s %s', 'referrer', $this->referrerValue);
        }

        if (!is_null($this->reportUri)) {
            $directives[] = sprintf('%s %s', 'report-uri', $this->reportUri);
        }

        // No CSP policies set?
        if (count($directives) < 1) {
            return null;
        }

        return trim(sprintf('%s%s', implode($this->directiveSeparator, $directives), $this->directiveSeparator));
    }

    /**
     * @param string $includeLegacy
     * @return array
     */
    public function getHeaders($includeLegacy)
    {
        $value = $this->getValue();
        if (is_null($value)) {
            $headers = [];
        } else {
            $headers[] = [
                'name' => $this->getHeaderName(),
                'value' => $value
            ];
        }

        if ($includeLegacy) {
            return array_merge($headers, $this->getLegacyHeaders());
        }

        return $headers;
    }

    /**
     * @return array
     */
    public function getLegacyHeaders()
    {
        return array_filter([
            $this->getLegacyXssHeader($this->reflectedXssValue),
            $this->getLegacyFrameOptionsHeader()
        ]);
    }

    /**
     * @param array $directive
     * @return null|string
     */
    private function parseDirectiveValue($directive)
    {
        $expressions = [];

        if (!(isset($directive) && is_array($directive))) {
            return null;
        }

        // Parse the source expressions
        if (isset($directive['expressions']) && is_array($directive['expressions'])) {
            $expressions = $directive['expressions'];
        }

        // Parse the source sets
        if (isset($directive['sets']) && is_array($directive['sets'])) {
            foreach ($directive['sets'] as $set) {
                $expressions = array_merge($expressions, $this->sourceSets[$set]);
            }
        }

        // Parse the nonces
        if (isset($directive['nonces']) && is_array($directive['nonces'])) {
            foreach ($directive['nonces'] as $nonce) {
                $expressions[] = sprintf("'nonce-%s'", $nonce);
            }
        }

        // Parse the hashes
        if (isset($directive['hashes']) && is_array($directive['hashes'])) {
            foreach ($directive['hashes'] as $type => $hashes) {
                foreach ($hashes as $hash) {
                    $expressions[] = sprintf("'%s-%s'", $type, base64_encode($hash));
                }
            }
        }

        return trim(implode(' ', array_map(function($value) {
            return $this->encodeDirectiveValue($value);
        }, $expressions)));
    }

    /**
     * @param string $value
     * @return string
     */
    public function encodeDirectiveValue($value)
    {
        $value = str_replace([';', ','], ['%3B', '%2C'], $value);

        if (in_array($value, $this->directiveValueTokens)) {
            $value = sprintf("'%s'", $value);
        }

        return trim($value);
    }

    /**
     * Extracts the (serialized) origin from a uri or false on failure.
     *
     * @param string $uri
     * @return false|string
     */
    public static function extractOrigin($uri)
    {
        $parts = parse_url($uri);

        // parse_url returns false when the uri is seriously malformed
        if (!is_array($parts)) {
            return false;
        }

        if (isset($parts['scheme']) && isset($parts['host'])) {
            if (isset($parts['port'])) {
                return sprintf('%s://%s:%s', $parts['scheme'], $parts['host'], $parts['port']);
            } else {
                return sprintf('%s://%s', $parts['scheme'], $parts['host']);
            }
        }

        return false;
    }

    /**
     * @param string $reflectedXssValue
     * @return array
     */
    private function getLegacyXssHeader($reflectedXssValue)
    {
        $header = [];

        switch ($reflectedXssValue) {
            case 'allow':
                $header = [
                    'name' => $this->legacyXssHeader,
                    'value' => '0'
                ];
                break;

            case 'filter':
                $header = [
                    'name' => $this->legacyXssHeader,
                    'value' => '1'
                ];
                break;

            case 'block':
                $header = [
                    'name' => $this->legacyXssHeader,
                    'value' => '1; mode=block'
                ];
                break;
        }

        return $header;
    }

    /**
     * @return array
     */
    private function getLegacyFrameOptionsHeader()
    {
        if (is_null($this->frameOptionsValue)) {
            return [];
        }

        return [
            'name' => $this->legacyFrameOptionsHeader,
            'value' => $this->frameOptionsValue
        ];
    }
}
