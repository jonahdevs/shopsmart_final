/**
 * Paystack's inline popup, fetched only when someone is actually paying.
 *
 * The script is not in the document head: it is third-party code on a critical
 * path that most visits never reach, so it is added on demand and the promise
 * that resolves it is cached at module scope. That cache is what makes this
 * safe to call from `onMounted` — a page that mounts, unmounts and mounts again
 * (every Inertia visit re-keys the component) reuses the one script tag rather
 * than stacking a second copy on the page.
 *
 * Nothing here talks to Paystack directly. The access code is minted server
 * side and the reference the popup returns is only ever a claim: it is the
 * `payment.verify` round trip, not this file, that decides whether an order
 * was paid for.
 */

/** What Paystack hands back once the shopper has been charged. */
export interface PaystackTransaction {
    reference: string;
    status?: string;
    message?: string;
}

/** The three ways a popup can end, from this application's point of view. */
export interface PaystackResumeCallbacks {
    onSuccess?: (transaction: PaystackTransaction) => void;
    onCancel?: () => void;
    onError?: (error: { message?: string }) => void;
}

export interface PaystackPopInstance {
    resumeTransaction(
        accessCode: string,
        callbacks?: PaystackResumeCallbacks,
    ): void;
}

export type PaystackPopConstructor = new () => PaystackPopInstance;

declare global {
    interface Window {
        PaystackPop?: PaystackPopConstructor;
    }
}

const SCRIPT_SRC = 'https://js.paystack.co/v2/inline.js';

/** The in-flight (or settled) load, so a second caller waits rather than adds. */
let loading: Promise<PaystackPopConstructor> | null = null;

/**
 * Resolve the `PaystackPop` constructor, adding the script if it is not there.
 *
 * Rejects rather than throwing later: a shopper whose network ate the script
 * needs to be told the payment window could not open, not left staring at a
 * button that does nothing.
 */
export function loadPaystack(): Promise<PaystackPopConstructor> {
    if (window.PaystackPop) {
        return Promise.resolve(window.PaystackPop);
    }

    loading ??= new Promise<PaystackPopConstructor>((resolve, reject) => {
        const fail = (): void => {
            // Cleared so a retry gets a fresh attempt instead of the old
            // rejection: the usual cause is a dropped connection, and the
            // shopper's second press should really try again.
            loading = null;
            reject(new Error('The Paystack script could not be loaded.'));
        };

        const settle = (): void => {
            if (window.PaystackPop) {
                resolve(window.PaystackPop);

                return;
            }

            fail();
        };

        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${SCRIPT_SRC}"]`,
        );

        const script = existing ?? document.createElement('script');

        script.addEventListener('load', settle, { once: true });
        script.addEventListener('error', fail, { once: true });

        if (existing) {
            return;
        }

        script.src = SCRIPT_SRC;
        script.async = true;
        document.head.append(script);
    });

    return loading;
}

/**
 * The `message` out of a JSON error body, or the caller's own wording.
 *
 * The gateway failures this page can hit (502, 409) are phrased server side in
 * the shopper's language, so they are shown verbatim when they arrive and
 * quietly replaced when the body is not what was expected.
 */
export function messageFrom(body: unknown, fallback: string): string {
    let parsed = body;

    if (typeof parsed === 'string') {
        try {
            parsed = JSON.parse(parsed) as unknown;
        } catch {
            return fallback;
        }
    }

    if (parsed !== null && typeof parsed === 'object' && 'message' in parsed) {
        const message = (parsed as { message: unknown }).message;

        if (typeof message === 'string' && message !== '') {
            return message;
        }
    }

    return fallback;
}
