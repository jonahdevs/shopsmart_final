<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

/**
 * Reduces stored SVG markup to a safe inline glyph.
 *
 * `categories.icon_svg` is rendered with `v-html`, so whatever is in that
 * column becomes live markup in the storefront. Sanitising here rather than in
 * a form request means the guarantee holds however the column was written — an
 * admin form, an import, a seeder or a console command.
 *
 * The allow-list is deliberately narrower than "safe SVG": these are flat
 * navigation glyphs, so shape elements are enough. Anything that can reference
 * another document or carry script — `use`, `image`, `foreignObject`, `script`,
 * `style`, the animation elements — is dropped rather than inspected, and so is
 * the `style` attribute, whose `url()` support reintroduces external
 * references. A category that needs richer artwork uploads an image instead;
 * CategoryTiles already prefers one when it is present.
 */
class SvgSanitizer
{
    /** @var list<string> */
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'title', 'desc',
        'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect',
    ];

    /** @var list<string> */
    private const ALLOWED_ATTRIBUTES = [
        'viewbox', 'xmlns', 'width', 'height', 'class', 'role', 'aria-hidden', 'focusable',
        'd', 'points', 'transform',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'fill', 'fill-rule', 'fill-opacity', 'clip-rule', 'opacity',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit',
    ];

    /**
     * Return the markup reduced to allow-listed elements and attributes, or
     * null when it is empty, unparseable, or not rooted in an `<svg>` element.
     */
    public function sanitize(?string $markup): ?string
    {
        if ($markup === null || trim($markup) === '') {
            return null;
        }

        $document = new DOMDocument;

        // Suppress libxml's own warnings: malformed markup is an expected input
        // here, and it is answered by returning null rather than by a log line.
        $previous = libxml_use_internal_errors(true);
        $parsed = $document->loadXML($markup, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($parsed === false) {
            return null;
        }

        $root = $document->documentElement;

        if (! $root instanceof DOMElement || strtolower($root->nodeName) !== 'svg') {
            return null;
        }

        $this->clean($root);

        $svg = $document->saveXML($root);

        return $svg === false ? null : $svg;
    }

    /**
     * Strip disallowed attributes from this element, then recurse into its
     * children, removing any element that is not allow-listed along with
     * everything beneath it.
     */
    private function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->nodeName), self::ALLOWED_ATTRIBUTES, true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (in_array(strtolower($child->nodeName), self::ALLOWED_ELEMENTS, true)) {
                    $this->clean($child);
                } else {
                    $element->removeChild($child);
                }

                continue;
            }

            // Comments and CDATA carry no glyph and can hide markup from a
            // casual read of the column, so only text survives.
            if ($child->nodeType !== XML_TEXT_NODE) {
                $element->removeChild($child);
            }
        }
    }
}
