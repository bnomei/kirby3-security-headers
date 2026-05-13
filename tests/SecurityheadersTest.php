<?php

require_once __DIR__.'/../vendor/autoload.php';

use Bnomei\SecurityHeaders;
use Kirby\Cms\App as Kirby;
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

    public static function withKirbyRequest(string $url, Closure $callback, array $options = []): mixed
    {
        $previous = Kirby::instance(null, true);
        $enableWhoops = Kirby::$enableWhoops;
        Kirby::$enableWhoops = false;

        new Kirby([
            'cli' => false,
            'roots' => [
                'index' => __DIR__,
                'content' => __DIR__.'/content',
                'site' => __DIR__.'/site',
            ],
            'options' => array_replace([
                'debug' => false,
                'url' => 'https://example.com',
                'bnomei.securityheaders.enabled' => null,
                'bnomei.securityheaders.loader' => SecurityHeaders::LOADER_DEFAULT,
            ], $options),
            'request' => [
                'method' => 'GET',
                'url' => $url,
            ],
        ]);

        try {
            return $callback();
        } finally {
            Kirby::$enableWhoops = $enableWhoops;

            if ($previous !== null) {
                Kirby::instance($previous);
            }
        }
    }
}

beforeEach(function () {
    TestHelper::make()->before();
});

afterEach(function () {
    TestHelper::make()->after();
});

test('construct', function () {
    $sec = new SecurityHeaders;
    expect($sec)->toBeInstanceOf(SecurityHeaders::class);
});

test('options', function () {
    $sec = new SecurityHeaders;
    expect($sec->option())->toBeArray();
    expect($sec->option())->toHaveCount(9);

    expect($sec->option('debug'))->toBeTrue();

    // config "force"
    $sec = new SecurityHeaders([
        'debug' => true,
        'enabled' => function () {
            return false;
        },
    ]);
    expect($sec->option('debug'))->toBeTrue();
    expect($sec->option('enabled'))->toBeFalse();
});

test('csp', function () {
    $sec = new SecurityHeaders;
    $builder = $sec->csp();
    expect($builder)->toBeInstanceOf(CSPBuilder::class);
    expect($sec->csp())->toEqual($builder);
});

test('load', function () {
    $sec = new SecurityHeaders;
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
    $sec = new SecurityHeaders([
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
    $sec = new SecurityHeaders([
        'enabled' => false, // force against localhost check
    ]);
    expect($sec->sendHeaders())->toBeFalse();
});

test('send headers full', function () {
    $sec = new SecurityHeaders([
        'enabled' => true, // force against localhost check
    ]);
    expect($sec->sendHeaders())->toBeTrue();
});

test('nonces', function () {
    $sec = new SecurityHeaders;
    $n = $sec->setNonce('test');
    expect($n)->toMatch('/^[A-Za-z0-9+\/]{24}$/')
        ->and($sec->getNonce('test'))->toEqual($n);
});

test('nonces are random per generation', function () {
    $sec = new SecurityHeaders(['seed' => null]);

    $first = $sec->setNonce('test');
    $second = $sec->setNonce('test');

    expect($first)->toMatch('/^[A-Za-z0-9+\/]{24}$/');
    expect($second)->toMatch('/^[A-Za-z0-9+\/]{24}$/');
    expect($second)->not->toEqual($first)
        ->and($sec->getNonce('test'))->toEqual($second);
});

test('frontend nonce is registered in script and style csp directives', function () {
    $sec = new SecurityHeaders(['seed' => 'frontend']);
    $nonce = $sec->getNonce('frontend');

    expect($nonce)->toBeString()
        ->and(substr_count($sec->csp()->compile(), "'nonce-".$nonce."'"))->toBe(2);
});

test('auto detection ignores api and panel urls in query strings', function () {
    TestHelper::withKirbyRequest('https://example.com/some-page?next=https://example.com/api', function () {
        $sec = new SecurityHeaders;

        expect($sec->option('enabled'))->toBeTrue()
            ->and($sec->option('panel'))->toBeFalse();
    });

    TestHelper::withKirbyRequest('https://example.com/some-page?next=https://example.com/panel', function () {
        $sec = new SecurityHeaders;

        expect($sec->option('enabled'))->toBeTrue()
            ->and($sec->option('panel'))->toBeFalse();
    });
});

test('auto detection only disables protected route segments', function () {
    $cases = [
        'https://example.com/api' => false,
        'https://example.com/api/pages' => false,
        'https://example.com/panel' => false,
        'https://example.com/panel/site' => false,
        'https://example.com/media/pages/home/hash/file.jpg' => false,
        'https://example.com/apiary' => true,
        'https://example.com/panelist' => true,
        'https://example.com/mediator' => true,
    ];

    foreach ($cases as $url => $enabled) {
        TestHelper::withKirbyRequest($url, function () use ($enabled) {
            $sec = new SecurityHeaders;

            expect($sec->option('enabled'))->toBe($enabled);
        });
    }
});
