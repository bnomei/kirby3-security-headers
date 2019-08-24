# PHP CSP
A PHP helper class to dynamically construct Content Security Policy headers as defined by the W3C in the Content Security Policy specification (http://www.w3.org/TR/CSP2/).

## How to use
Add `php-csp` to your project and create an instance of the `ContentSecurityPolicyHeaderBuilder` class and use it to setup your CSP policy. Once your policy is complete, use `ContentSecurityPolicyHeaderBuilder::getHeaders()` to get your CSP header.

```php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

$policy = new ContentSecurityPolicyHeaderBuilder();

// Set the script-src directive to 'none'
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, 'none');

// Enable the browsers xss blocking features
$policy->setReflectedXssPolicy(ContentSecurityPolicyHeaderBuilder::REFLECTED_XSS_BLOCK);

// Set a report URL
$policy->setReportUri('https://example.com/csp/report.php');

// Get your CSP headers
$headers = $policy->getHeaders(false);
```

`ContentSecurityPolicy::getHeaders()` returns an array of HTTP headers you should send. For the example above this results in the following array:

```
array (size=1)
  0 => 
    array (size=2)
      'name' => string 'Content-Security-Policy' (length=23)
      'value' => string 'script-src 'none'; reflected-xss block; report-uri https://example.com/csp/report.php;' (length=86)
```

### Source expressions
The most straightforward use of this class is by adding origins to the directives of your choice like in this example:

```php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

$policy = new ContentSecurityPolicyHeaderBuilder();

// Set the default-src directive to 'none'
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_DEFAULT_SRC, 'none');

// Add a single origin for the script-src directive
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, 'https://example.com/scripts/');

// Add a single origin for the style-src directive
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_STYLE_SRC, 'https://example.com/style/');

foreach ($policy->getHeaders(true) as $header) {
    header(sprintf('%s: %s', $header['name'], $header['value']));
}

```
This example would output the following headers:

```
array (size=1)
  0 => 
    array (size=2)
      'name' => string 'Content-Security-Policy' (length=23)
      'value' => string 'default-src 'none'; script-src https://example.com/scripts/; style-src https://example.com/style/;' (length=98)
```
      
### Source sets
You can define source-sets and link them to any CSP directive you want. For example

```php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

$policy = new ContentSecurityPolicyHeaderBuilder();

// Set the default-src directive to 'none'
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_DEFAULT_SRC, 'none');

// Define two source sets
$policy->defineSourceSet('my-scripts-cdn', [
    'https://cdn-scripts1.example.com/scripts/',
    'https://cdn-scripts2.example.com/scripts/'
]);

$policy->defineSourceSet('my-style-cdn', [
    'https://cdn-style1.example.com/css/',
    'https://cdn-style2.example.com/css/'
]);

// Add both to a directive
$policy->addSourceSet(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, 'my-scripts-cdn');
$policy->addSourceSet(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_STYLE_SRC, 'my-style-cdn');
$headers = $policy->getHeaders(false);
```

Would result in the following headers:

```
array (size=1)
  0 => 
    array (size=2)
      'name' => string 'Content-Security-Policy' (length=23)
      'value' => string 'default-src 'none'; script-src https://cdn-scripts1.example.com/scripts/ https://cdn-scripts2.example.com/scripts/; style-src https://cdn-style1.example.com/css/ https://cdn-style2.example.com/css/;' (length=198)
```

### Nonces
CSP blocks inline scripts but they can be enabled again by adding `unsafe-inline` to the `script-src` directive. Doing this would defeat the entire purpose of using CSP and therefor, the use of `unsafe-inline` is not recommended. If you need to inline scripts for whatever reason, you should use nonces. A nonce is a random string you add to the `script-src` directive and the inline script tags you allow on your webpage like in the following example:

```php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

$policy = new ContentSecurityPolicyHeaderBuilder();

// Set the default-src directive to 'none'
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_DEFAULT_SRC, 'none');

$myScriptNonce = 'thisShouldBeRandom';

// Add the nonce to the script-src directive
$policy->addNonce(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, $myScriptNonce);

foreach ($policy->getHeaders(true) as $header) {
    header(sprintf('%s: %s', $header['name'], $header['value']));
}

```
Would result in the following headers:

```
array (size=1)
  0 => 
    array (size=2)
      'name' => string 'Content-Security-Policy' (length=23)
      'value' => string 'default-src 'none'; script-src 'nonce-thisShouldBeRandom';' (length=58)
```
Your HTML should look like this:

```
<script>
    // This would be blocked because it has no nonce
</script>

<script nonce="thisIsAWrongNonce">
   // This would be blocked because the nonce is invalid
</script>

<script nonce="thisShouldBeRandom">
    // This would work fine
</script>
```

The nonce should be random for each request so attackers cannot predict the nonce value.

### Hashes
If your application requires inline scripts you can serve the SHA256, SHA384, or SHA512 hash of the source as part of the script-src directive in your policy to allow the script to run. This way you don't need to enable unsafe-inline.

### Violation reports
CSP gives you the option to receive reports about CSP violations. Each time a page loads a resource that is blocked by your CSP policy, the browser will submit a JSON object to the URL you specified in your policy. In the following example, those report will be send to `https://example.com/csp/report.php`:

```php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

$policy = new ContentSecurityPolicyHeaderBuilder();

// Set the script-src directive to 'none'
$policy->addSourceExpression(ContentSecurityPolicyHeaderBuilder::DIRECTIVE_SCRIPT_SRC, 'https://example.com/scripts/');

// Set a report URL
$policy->setReportUri('https://example.com/csp/report.php');
```

You can also use CSP in a report-only mode. This mode is ideal if you are implenting CSP on an existing website without breaking things. Each time a resource load violates your CSP policy, the browser will send a violation report but it won't actually block the resource.

```php
// Set a report URL
$policy->setReportUri('https://example.com/csp/report.php');

// Use report only mode
$policy->enforcePolicy(false);
```
## Legacy header support
This class also provides support for some legacy headers which are being replaced by CSP. Currently it has support for the `X-XSS-Protection` and `X-Frame-Options` headers.

```php
use Phpcsp\Security\ContentSecurityPolicyHeaderBuilder;

$policy = new ContentSecurityPolicyHeaderBuilder();

// Enable the browsers xss blocking features
$policy->setReflectedXssPolicy(ContentSecurityPolicyHeaderBuilder::REFLECTED_XSS_BLOCK);

// Set the 'X-Frame-Options' header
$policy->setFrameOptions(ContentSecurityPolicyHeaderBuilder::FRAME_OPTION_SAME_ORIGIN);

// Get your CSP headers, including legacy headers
$headers = $policy->getHeaders(true);

foreach ($headers as $header) {
    header(sprintf('%s: %s', $header['name'], $header['value']));
}
```
This would result in the following headers:

```
array (size=3)
  0 => 
    array (size=2)
      'name' => string 'Content-Security-Policy' (length=23)
      'value' => string 'script-src 'none'; reflected-xss block; report-uri https://example.com/csp/report.php;' (length=86)
  1 => 
    array (size=2)
      'name' => string 'X-XSS-Protection' (length=16)
      'value' => string '1; mode=block' (length=13)
  2 => 
    array (size=2)
      'name' => string 'X-Frame-Options' (length=15)
      'value' => string 'SAMEORIGIN' (length=10)
```
