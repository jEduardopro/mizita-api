<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Messages
    |--------------------------------------------------------------------------
    |
    | The vocabulary for the strings the platform produces itself, as opposed to
    | the framework's validation, auth and password lines. Anything a user can
    | read - an API error body included - is looked up here instead of being
    | written inline, so es and en never drift apart.
    |
    | Keep the groups shallow and the keys descriptive: errors.forbidden is the
    | generic wording, errors.no_business the specific case underneath it.
    |
    */

    'errors' => [

        // Generic fallbacks, one per status the API answers with.
        'unauthenticated' => 'You need to sign in to continue.',
        'forbidden' => 'You are not allowed to perform this action.',
        'not_found' => 'The requested resource does not exist.',
        'conflict' => 'This action conflicts with the current state of the resource.',
        'too_many_requests' => 'Too many requests. Please try again in a moment.',
        'server_error' => 'Something went wrong on our side. Please try again.',

        // A caller reached a tenant-scoped route without belonging to a business.
        'no_business' => 'This user does not belong to a business.',

        // A public slug that resolves to nothing. The wording says "not found"
        // and nothing more: confirming that a slug exists is an information leak.
        'business_not_found' => 'We could not find that business.',

        // The database rejected an appointment overlapping an existing one.
        'appointment_overlap' => 'That time slot has just been booked. Please choose another one.',

        // The Google round trip did not complete: consent was denied, the
        // session expired, or the profile came back unusable.
        'google_sign_in_failed' => 'We could not complete the sign in with Google. Please try again.',

        // Google has not verified the address, so it proves nothing. The wording
        // stays vague on purpose: it must not reveal whether an account exists.
        'google_email_not_verified' => 'Your Google account email is not verified. Verify it with Google and try again.',

        // The ID token failed verification, or was never an ID token.
        'google_invalid_id_token' => 'That Google sign in credential is not valid.',

    ],

];
