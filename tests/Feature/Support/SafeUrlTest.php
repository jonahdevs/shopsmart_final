<?php

use App\Support\SafeUrl;

/**
 * `hero_slides.cta_url` is bound straight to an href, so this is what decides
 * whether a stored value may become a link target.
 */
beforeEach(function () {
    $this->safeUrl = new SafeUrl;
});

test('it allows same-site paths and ordinary web schemes', function (string $url) {
    expect($this->safeUrl->forLink($url))->toBe($url);
})->with([
    'root path' => '/shop',
    'nested path' => '/shop/kettles?sort=newest',
    'https' => 'https://example.test/promo',
    'http' => 'http://example.test/promo',
    'mailto' => 'mailto:sales@example.test',
    'tel' => 'tel:+254700000000',
]);

test('it rejects a scheme that executes on click', function (string $url) {
    expect($this->safeUrl->forLink($url))->toBeNull();
})->with([
    'javascript' => 'javascript:alert(1)',
    'mixed case javascript' => 'JaVaScRiPt:alert(1)',
    'data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript' => 'vbscript:msgbox(1)',
]);

test('it rejects a protocol-relative url, which reads as a path but leaves the site', function () {
    expect($this->safeUrl->forLink('//evil.test/promo'))->toBeNull();
});

test('it treats blank values as absent', function (?string $url) {
    expect($this->safeUrl->forLink($url))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'whitespace' => '   ',
]);
