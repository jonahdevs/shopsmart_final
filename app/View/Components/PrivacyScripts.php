<?php

namespace App\View\Components;

use App\Enums\ConsentCategory;
use App\Settings\LegalSettings;
use App\Support\AnalyticsTags;
use App\Support\Consent;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Everything the document head needs to know about this visitor's privacy
 * choices: the measurement tags they have agreed to, and the configuration the
 * consent banner renders itself from.
 *
 * A Blade component rather than a shared Inertia prop for two reasons. The tags
 * are `<script>` elements that must exist in the document the browser parses,
 * and the gate has to be applied before the response is written — an Inertia
 * prop is read after the page has already loaded whatever the head contained.
 *
 * @see Consent for the gate itself.
 */
class PrivacyScripts extends Component
{
    public function __construct(
        private AnalyticsTags $tags,
        private Consent $consent,
        private LegalSettings $legal,
    ) {}

    public function render(): View
    {
        $request = request();

        return view('components.privacy-scripts', [
            'tags' => $this->tags->forRequest($request),
            'googleConsent' => $this->tags->googleConsentState($request),
            'config' => [
                'categories' => $this->offeredOptions(),
                'granted' => array_map(
                    static fn (ConsentCategory $category): string => $category->value,
                    $this->consent->granted($request),
                ),
                'needsAnswer' => $this->consent->needsAnswer($request),
                'privacyPolicyUrl' => $this->legal->privacy_policy_url,
                'termsUrl' => $this->legal->terms_url,
            ],
        ]);
    }

    /**
     * The categories this store asks about, in enum order, with the copy the
     * banner shows for each.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function offeredOptions(): array
    {
        return array_map(
            static fn (ConsentCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
            ],
            $this->consent->offered(),
        );
    }
}
