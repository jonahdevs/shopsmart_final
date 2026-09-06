<?php

namespace App\Http\Requests\Admin;

/**
 * Inviting a colleague.
 *
 * No password field, deliberately. An admin typing someone else's first password
 * means a credential exists that two people know and that the store has no
 * record of agreeing on; the invitation goes out through Fortify's own password
 * reset broker instead, and the colleague is the only person who ever sets it.
 * Everything else about the rules — the roles a staff member may hand out, and
 * why — lives in {@see StaffFormRequest}.
 */
class StaffStoreRequest extends StaffFormRequest {}
