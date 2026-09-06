import { ref, type Ref } from 'vue';

/** One optional category the banner asks about. */
export type ConsentCategory = {
    value: string;
    label: string;
    description: string;
};

export type ConsentConfig = {
    categories: ConsentCategory[];
    granted: string[];
    needsAnswer: boolean;
    privacyPolicyUrl: string;
    termsUrl: string;
};

const EMPTY: ConsentConfig = {
    categories: [],
    granted: [],
    needsAnswer: false,
    privacyPolicyUrl: '',
    termsUrl: '',
};

/**
 * The consent state the server put in the document.
 *
 * It arrives as a JSON island rather than an Inertia prop because the decision
 * it describes is made before the page is built: the same pass that writes this
 * decides which measurement tags the head carries. Reading it here keeps the
 * banner and the tags looking at one answer rather than two.
 *
 * Read once and memoised — the element never changes without a full document
 * load, which is exactly what happens when consent changes.
 */
let cached: ConsentConfig | null = null;

export function consentConfig(): ConsentConfig {
    if (cached !== null) {
        return cached;
    }

    if (typeof document === 'undefined') {
        return EMPTY;
    }

    const element = document.getElementById('consent-config');

    if (element === null) {
        cached = EMPTY;

        return cached;
    }

    try {
        cached = { ...EMPTY, ...(JSON.parse(element.textContent ?? '{}') as Partial<ConsentConfig>) };
    } catch {
        cached = EMPTY;
    }

    return cached;
}

/**
 * Whether the preferences panel is open.
 *
 * Module-level so the footer's "Cookie preferences" link can reopen the banner
 * a visitor has already answered, without the two components having to know
 * about each other.
 */
const preferencesOpen: Ref<boolean> = ref(false);

export function useConsentPreferences() {
    return {
        preferencesOpen,
        openConsentPreferences: () => {
            preferencesOpen.value = true;
        },
        closeConsentPreferences: () => {
            preferencesOpen.value = false;
        },
    };
}

/**
 * Whether the store asks about anything at all. When it does not, the banner
 * and the footer link both stay away — there is no choice to offer.
 */
export function consentIsOffered(): boolean {
    return consentConfig().categories.length > 0;
}
