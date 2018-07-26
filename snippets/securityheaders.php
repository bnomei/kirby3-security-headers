<?php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

// https://github.com/Martijnc/php-csp

$policy = new ContentSecurityPolicyHeaderBuilder();

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
foreach (option('bnomei.securityheaders.nounces', []) as $n) {
    $policy->addNonce(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, $n);
}
foreach (option('bnomei.securityheaders.hashes', []) as $h) {
    $policy->addHash(ContentSecurityPolicyHeaderBuilder::HASH_SHA_256, $h);
    // hash(ContentSecurityPolicyHeaderBuilder::HASH_SHA_256, $script, true)
}

Bnomei\SecurityHeaders::headers($policy->getHeaders(true));
