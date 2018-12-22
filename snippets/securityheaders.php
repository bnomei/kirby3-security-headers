<?php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

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
    \Bnomei\SecurityHeaders::nonce($id, $nonce);
    $policy->addNonce(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, $nonce);
}
foreach (option('bnomei.securityheaders.hashes', []) as $h) {
    $policy->addHash(ContentSecurityPolicyHeaderBuilder::HASH_SHA_256, $h);
    // hash(ContentSecurityPolicyHeaderBuilder::HASH_SHA_256, $script, true)
}

Bnomei\SecurityHeaders::headers($policy->getHeaders(true));
