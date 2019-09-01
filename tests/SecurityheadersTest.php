<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\SecurityHeaders;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Toolkit\F;
use ParagonIE\CSPBuilder\CSPBuilder;
use PHPUnit\Framework\TestCase;

final class SecurityheadersTest extends TestCase
{
    private $jsonPath;
    private $yamlPath;
    private $apachePath;
    private $nginxPath;

    public function setUp(): void
    {
        $this->jsonPath = __DIR__ . '/test-builder-config.json';
        $this->yamlPath = __DIR__ . '/test-builder-config.yml';
        $this->apachePath = __DIR__ . '/apache.cache';
        $this->nginxPath = __DIR__ . '/nginx.cache';

        if(F::exists($this->jsonPath) && !F::exists($this->yamlPath)) {
            $json = Json::decode(F::read($this->jsonPath));
            F::write($this->yamlPath, Yaml::encode($json));
        }

        F::remove($this->apachePath);
        F::remove($this->nginxPath);
    }

    public function tearDown(): void
    {
        F::remove($this->apachePath);
        F::remove($this->nginxPath);
    }

    public function testConstruct()
    {
        $sec = new Bnomei\SecurityHeaders();
        $this->assertInstanceOf(SecurityHeaders::class, $sec);
    }

    public function testOptions()
    {
        $sec = new Bnomei\SecurityHeaders();
        $this->assertIsArray($sec->option());
        $this->assertCount(6, $sec->option());

        $this->assertNull($sec->option('debug'));

        $sec = new Bnomei\SecurityHeaders([
            'debug' => true,
            'enabled' => function() { return false; }
        ]);
        $this->assertTrue($sec->option('debug'));
        $this->assertFalse($sec->option('enabled'));
    }

    public function testCsp()
    {
        $sec = new Bnomei\SecurityHeaders();
        $this->assertNull($sec->csp());

        $builder = $sec->load();
        $this->assertInstanceOf(CSPBuilder::class, $builder);
        $this->assertEquals($builder, $sec->csp());
    }

    public function testLoad()
    {
        $sec = new Bnomei\SecurityHeaders();
        $builder = $sec->load([]);
        $this->assertInstanceOf(CSPBuilder::class, $builder);

        $builder = $sec->load($this->jsonPath);
        $this->assertInstanceOf(CSPBuilder::class, $builder);

        $builder = $sec->load($this->yamlPath);
        $this->assertInstanceOf(CSPBuilder::class, $builder);

        $builder = $sec->load(Json::decode(F::read($this->jsonPath)));
        $this->assertInstanceOf(CSPBuilder::class, $builder);
    }

    public function testApplySetter()
    {
        $sec = new Bnomei\SecurityHeaders([
            'setter' => function (SecurityHeaders $instance) {
                $instance->saveApache($this->apachePath);
            },
        ]);
        $sec->load();
        $sec->applySetter();
        $this->assertTrue(F::exists($this->apachePath));
    }

    public function testSave()
    {
        $sec = SecurityHeaders::singleton();
        $this->assertTrue($sec->saveApache($this->apachePath));
        $this->assertTrue($sec->saveNginx($this->nginxPath));
    }

    public function testSingleton()
    {
        $sec = SecurityHeaders::singleton();
        $this->assertInstanceOf(SecurityHeaders::class, $sec);
    }

    public function testSendHeadersDisabled()
    {
        $sec = new Bnomei\SecurityHeaders([
            'enabled' => false, // force against localhost check
        ]);
        $sec->load();
        $this->assertFalse($sec->sendHeaders());
    }

    public function testSendHeadersCSPOnly()
    {
        $sec = new Bnomei\SecurityHeaders([
            'enabled' => true, // force against localhost check
            'headers' => [], // no default headers to test covage from sendCSPHeader
        ]);
        $sec->load();
        $this->expectExceptionMessageRegExp(
            '/^Headers already sent!*$/'
        );
        $this->assertFalse($sec->sendHeaders());
    }

    public function testSendHeadersFull()
    {
        $sec = new Bnomei\SecurityHeaders([
            'enabled' => true, // force against localhost check
        ]);
        $sec->load();
        $this->expectExceptionMessageRegExp(
            '/^Cannot modify header information - headers already sent by.*$/'
        );
        $this->assertTrue($sec->sendHeaders());
    }

    public function testNonces()
    {
        $sec = new Bnomei\SecurityHeaders();
        $n = $sec->setNonce('test');
        $this->assertRegExp('/^nonce-(.){54}==$/', $n);
        $this->assertEquals($n, $sec->getNonce('test'));
    }
}
