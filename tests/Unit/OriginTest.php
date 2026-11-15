<?php

use App\Widgets\Origin;

it('cleans up valid website addresses', function (string $input, string $expected) {
    expect(Origin::normalize($input))->toBe($expected);
})->with([
    'plain' => ['https://acme.com', 'https://acme.com'],
    'capitals' => ['HTTPS://Acme.COM', 'https://acme.com'],
    'trailing slash' => ['https://acme.com/', 'https://acme.com'],
    'spaces around' => ['  https://help.acme.com  ', 'https://help.acme.com'],
    'default port' => ['https://acme.com:443', 'https://acme.com'],
    'custom port' => ['https://acme.com:8443', 'https://acme.com:8443'],
    'localhost over http' => ['http://localhost:8080', 'http://localhost:8080'],
    'laragon site over http' => ['http://shop.test', 'http://shop.test'],
    'local ip over http' => ['http://127.0.0.1:5500', 'http://127.0.0.1:5500'],
]);

it('rejects addresses that are not a usable origin', function (string $input) {
    expect(Origin::normalize($input))->toBeNull();
})->with([
    'empty' => [''],
    'no scheme' => ['acme.com'],
    'http on a real website' => ['http://acme.com'],
    'other scheme' => ['ftp://acme.com'],
    'javascript' => ['javascript:alert(1)'],
    'page path' => ['https://acme.com/pricing'],
    'query string' => ['https://acme.com?ref=1'],
    'fragment' => ['https://acme.com#top'],
    'login details' => ['https://user:pass@acme.com'],
    'header injection' => ['https://acme.com; script-src *'],
    'wildcard' => ['https://*.acme.com'],
    'no dot' => ['https://acme'],
    'scheme only' => ['https://'],
]);
