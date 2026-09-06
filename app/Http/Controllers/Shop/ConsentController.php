<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreConsentRequest;
use App\Support\Consent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

/**
 * Records the visitor's answer to the cookie banner.
 *
 * The answer is written by the server so it comes back on every subsequent
 * request already decrypted, which is what lets the document head be assembled
 * with the tags this visitor allowed and nothing else. A choice made here
 * therefore only takes effect on the next full document — the banner reloads
 * the page itself when the granted set has actually changed.
 */
class ConsentController extends Controller
{
    public function store(StoreConsentRequest $request, Consent $consent): RedirectResponse
    {
        Cookie::queue(Cookie::make(
            Consent::COOKIE,
            $consent->payload($request->grantedCategories()),
            Consent::LIFETIME,
        ));

        return back();
    }
}
