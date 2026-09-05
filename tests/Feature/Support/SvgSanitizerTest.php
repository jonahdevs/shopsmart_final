<?php

use App\Support\SvgSanitizer;

/**
 * `categories.icon_svg` reaches the storefront through `v-html`, so this is the
 * boundary that decides what may become live markup. The allow-list is narrow
 * on purpose: flat navigation glyphs only.
 */
beforeEach(function () {
    $this->sanitizer = new SvgSanitizer;
});

test('it keeps an ordinary icon glyph intact', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18"/><circle cx="12" cy="12" r="9"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    expect($clean)->toContain('<path d="M3 12h18"/>')
        ->and($clean)->toContain('<circle cx="12" cy="12" r="9"/>')
        ->and($clean)->toContain('viewBox="0 0 24 24"')
        ->and($clean)->toContain('stroke-width="2"');
});

test('it strips a script element and everything inside it', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    expect($clean)->not->toContain('script')
        ->and($clean)->not->toContain('alert')
        ->and($clean)->toContain('<path d="M0 0"/>');
});

test('it strips event handler attributes', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0" onclick="alert(2)" onmouseover="alert(3)"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    expect($clean)->not->toContain('onload')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onmouseover')
        ->and($clean)->not->toContain('alert');
});

test('it drops elements that can reach another document', function (string $markup, string $banned) {
    expect($this->sanitizer->sanitize($markup))->not->toContain($banned);
})->with([
    'foreignObject' => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml">hi</body></foreignObject></svg>', 'foreignObject'],
    'use' => ['<svg xmlns="http://www.w3.org/2000/svg"><use href="http://evil.test/x.svg#a"/></svg>', 'use'],
    'image' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="http://evil.test/x.png"/></svg>', 'image'],
    'style' => ['<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(http://evil.test/x.css);</style></svg>', 'style'],
    'animate' => ['<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"><animate attributeName="d" to="M1 1"/></path></svg>', 'animate'],
]);

test('it drops the style attribute, which can carry an external reference', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" style="background:url(http://evil.test/x.png)"/></svg>';

    expect($this->sanitizer->sanitize($svg))->not->toContain('style')
        ->and($this->sanitizer->sanitize($svg))->not->toContain('evil.test');
});

test('it strips comments and CDATA that could hide markup from a casual read', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><!-- <script>alert(1)</script> --><path d="M0 0"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    expect($clean)->not->toContain('alert')
        ->and($clean)->toContain('<path d="M0 0"/>');
});

test('it rejects markup that is not rooted in an svg element', function (?string $markup) {
    expect($this->sanitizer->sanitize($markup))->toBeNull();
})->with([
    'html' => '<div onclick="alert(1)">not an icon</div>',
    'bare script' => '<script>alert(1)</script>',
    'malformed' => '<svg><path d="M0 0"',
    'empty' => '',
    'whitespace' => '   ',
    'null' => null,
]);

test('it refuses a document carrying a doctype, closing the XXE file-read path', function () {
    $secret = tempnam(sys_get_temp_dir(), 'svg');
    file_put_contents($secret, 'APP_KEY=base64:super-secret');

    $svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file://'
        .str_replace(chr(92), '/', $secret)
        .'">]><svg xmlns="http://www.w3.org/2000/svg"><title>&x;</title><path d="M0 0"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    unlink($secret);

    expect($clean)->toBeNull();
});

test('it still expands predefined and numeric character references', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><title>Tea &amp; Coffee &#8212; hot</title><path d="M0 0"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    expect($clean)->toContain('Tea &amp; Coffee')
        ->and($clean)->toContain('hot');
});

test('it strips class, so stored markup cannot claim shipped utilities', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" class="fixed inset-0 z-50"><rect x="0" y="0" width="100" height="100"/></svg>';

    $clean = $this->sanitizer->sanitize($svg);

    expect($clean)->not->toContain('class')
        ->and($clean)->not->toContain('fixed')
        ->and($clean)->toContain('<rect');
});
