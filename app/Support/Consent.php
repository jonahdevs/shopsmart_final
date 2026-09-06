<?php

namespace App\Support;

use App\Enums\ConsentCategory;
use App\Http\Controllers\Shop\ConsentController;
use Illuminate\Http\Request;

/**
 * The visitor's answer to the cookie banner, and the only place that decides
 * whether an optional category may load.
 *
 * The answer is kept in a first-party cookie rather than in `localStorage`
 * because the gate has to run on the *server*: a tag that is only hidden by
 * client-side JavaScript has already been fetched from the vendor by the time
 * the check runs. Nothing belonging to an ungranted category is written into
 * the page at all, so a declining visitor makes no request to Google or Meta
 * and receives none of their cookies.
 *
 * The cookie records the offered set alongside the granted one. Adding a
 * category in the admin therefore re-asks everybody rather than silently
 * inheriting an answer that was given to a different question.
 */
class Consent
{
    /**
     * Encrypted like every other cookie in this application: it is written by
     * {@see ConsentController}, not by JavaScript,
     * so it never has to survive a round trip through the browser unencrypted.
     */
    public const COOKIE = 'consent';

    /** One year, in minutes. */
    public const LIFETIME = 60 * 24 * 365;

    public function __construct(private PrivacyConfig $config) {}

    /**
     * The optional categories this store asks about. An empty list means the
     * banner does not render and no optional tag can ever load.
     *
     * Read through {@see PrivacyConfig} rather than the settings object: this
     * runs on every document, and resolving the settings costs a query.
     *
     * @return list<ConsentCategory>
     */
    public function offered(): array
    {
        $categories = array_filter(array_map(
            static fn (string $value): ?ConsentCategory => ConsentCategory::tryFrom($value),
            $this->config->get()['categories'],
        ));

        return array_values(array_filter(
            $categories,
            static fn (ConsentCategory $category): bool => $category->isOptional(),
        ));
    }

    public function isOffered(ConsentCategory $category): bool
    {
        return in_array($category, $this->offered(), true);
    }

    /**
     * The categories this visitor has granted, intersected with what is
     * currently offered.
     *
     * @return list<ConsentCategory>
     */
    public function granted(Request $request): array
    {
        $answer = $this->answer($request);

        if ($answer === null) {
            return [];
        }

        return array_values(array_filter(
            $this->offered(),
            static fn (ConsentCategory $category): bool => in_array($category->value, $answer['granted'], true),
        ));
    }

    /**
     * Whether a category may load for this request. `necessary` always may;
     * everything else needs to be both offered and granted.
     */
    public function allows(Request $request, ConsentCategory $category): bool
    {
        if (! $category->isOptional()) {
            return true;
        }

        return in_array($category, $this->granted($request), true);
    }

    /**
     * Whether the banner still has a question to ask. True when the store
     * offers something the visitor has not answered for — including the case
     * where a new category was added after they answered.
     */
    public function needsAnswer(Request $request): bool
    {
        if ($this->offered() === []) {
            return false;
        }

        $answer = $this->answer($request);

        if ($answer === null) {
            return true;
        }

        return $answer['offered'] !== $this->offeredValues();
    }

    /**
     * The cookie payload for a set of granted categories, with anything not
     * currently offered dropped. Stored as JSON rather than a bare list so the
     * offered set travels with it.
     *
     * @param  array<int, string>  $granted
     */
    public function payload(array $granted): string
    {
        $allowed = array_values(array_intersect($this->offeredValues(), $granted));

        return (string) json_encode([
            'granted' => $allowed,
            'offered' => $this->offeredValues(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function offeredValues(): array
    {
        return array_map(
            static fn (ConsentCategory $category): string => $category->value,
            $this->offered(),
        );
    }

    /**
     * The decoded cookie, or null when it is absent or unreadable. A cookie
     * that fails to parse is treated as no answer at all, which denies
     * everything optional.
     *
     * @return array{granted: list<string>, offered: list<string>}|null
     */
    private function answer(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'granted' => $this->stringList($decoded['granted'] ?? []),
            'offered' => $this->stringList($decoded['offered'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
