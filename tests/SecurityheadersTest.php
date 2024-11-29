<?php

require_once __DIR__.'/../vendor/autoload.php';

use Bnomei\SecurityHeaders;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Filesystem\F;
use ParagonIE\CSPBuilder\CSPBuilder;

class TestHelper
{
    public const PATHS = [
        'json' => __DIR__.'/fixtures/securityheaders.json',
        'yaml' => __DIR__.'/fixtures/securityheaders.yml',
        'apache' => __DIR__.'/fixtures/.htaccess',
        'nginx' => __DIR__.'/fixtures/nginx.conf',
    ];

    public function __construct(
        public string $json,
        public string $yaml,
        public string $apache,
        public string $nginx,
    ) {}

    public function before(): void
    {
        F::remove($this->apache);
        F::remove($this->nginx);

        if (F::exists($this->json) && ! F::exists($this->yaml)) {
            $json = Json::decode(F::read($this->json));
            F::write($this->yaml, Yaml::encode($json));
        }
    }

    public function after(): void
    {
        F::remove($this->apache);
        F::remove($this->nginx);
    }

    public static function make(array $args = []): self
    {
        return new self(...(count($args) === 0 ? static::PATHS : $args));
    }
}

beforeEach(function () {
    TestHelper::make()->before();
});

afterEach(function () {
    TestHelper::make()->after();
});

test('construct', function () {
    $sec = new Bnomei\SecurityHeaders;
    expect($sec)->toBeInstanceOf(SecurityHeaders::class);
});

test('options', function () {
    $sec = new Bnomei\SecurityHeaders;
    expect($sec->option())->toBeArray();
    expect($sec->option())->toHaveCount(8);

    expect($sec->option('debug'))->toBeTrue();

    // config "force"
    $sec = new Bnomei\SecurityHeaders([
        'debug' => true,
        'enabled' => function () {
            return false;
        },
    ]);
    expect($sec->option('debug'))->toBeTrue();
    expect($sec->option('enabled'))->toBeFalse();
});

test('csp', function () {
    $sec = new Bnomei\SecurityHeaders;
    $builder = $sec->csp();
    expect($builder)->toBeInstanceOf(CSPBuilder::class);
    expect($sec->csp())->toEqual($builder);
});

test('load', function () {
    $sec = new Bnomei\SecurityHeaders;
    $builder = $sec->load([]);
    expect($builder)->toBeInstanceOf(CSPBuilder::class);

    $builder = $sec->load(TestHelper::PATHS['json']);
    expect($builder)->toBeInstanceOf(CSPBuilder::class);

    $builder = $sec->load(TestHelper::PATHS['yaml']);
    expect($builder)->toBeInstanceOf(CSPBuilder::class);

    $builder = $sec->load(Json::decode(F::read(TestHelper::PATHS['json'])));
    expect($builder)->toBeInstanceOf(CSPBuilder::class);
});

test('apply setter', function () {
    $sec = new Bnomei\SecurityHeaders([
        'setter' => function (SecurityHeaders $instance) {
            $instance->saveApache(TestHelper::PATHS['apache']);
        },
    ]);
    $sec->load();
    $sec->applySetter();
    expect(F::exists(TestHelper::PATHS['apache']))->toBeTrue();
});

test('save', function () {
    $sec = SecurityHeaders::singleton();
    expect($sec->saveApache(TestHelper::PATHS['apache']))->toBeTrue()
        ->and($sec->saveNginx(TestHelper::PATHS['nginx']))->toBeTrue();
});

test('singleton', function () {
    $sec = SecurityHeaders::singleton();
    expect($sec)->toBeInstanceOf(SecurityHeaders::class);
});

test('send headers disabled', function () {
    $sec = new Bnomei\SecurityHeaders([
        'enabled' => false, // force against localhost check
    ]);
    expect($sec->sendHeaders())->toBeFalse();
});

test('send headers full', function () {
    $sec = new Bnomei\SecurityHeaders([
        'enabled' => true, // force against localhost check
    ]);
    expect($sec->sendHeaders())->toBeTrue();
});

test('nonces', function () {
    $sec = new Bnomei\SecurityHeaders;
    $n = $sec->setNonce('test');
    expect($n)->toMatch('/^(.){54}==$/')
        ->and($sec->getNonce('test'))->toEqual($n);
});
