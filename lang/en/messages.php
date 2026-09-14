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

        // A caller asked to operate a business they are not a member of. The
        // distinction from no_business matters: this one does have businesses,
        // just not the one requested. The wording names none of them.
        'business_not_accessible' => 'You do not have access to that business.',

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

        // Accounts. None of these name the address involved: an error body that
        // confirms an email is registered is an account enumeration oracle.
        'account_already_registered' => 'An account with that email already exists.',
        'account_not_found' => 'We could not find that account.',
        'invalid_account_email' => 'That email address is not valid.',
        'invalid_account_name' => 'That name is not valid.',
        'invalid_provider_user_id' => 'We could not identify that sign in account.',
        'social_identity_already_linked' => 'That sign in account is already linked to another account.',

        // Business onboarding. The wording never repeats the name, slug or
        // timezone that was rejected - the caller already knows what it sent.
        'business_name_taken' => 'There is already a business with that name.',
        'business_slug_taken' => 'That web address is already in use. Please choose another one.',
        'owner_already_has_business' => 'You already have a business registered.',
        'business_name_not_sluggable' => 'That name cannot be turned into a web address. Please use letters or numbers.',
        'unknown_industry' => 'That industry is not one we support.',
        'invalid_timezone' => 'That time zone is not valid.',
        'invalid_business_name' => 'That business name is not valid.',
        'invalid_business_slug' => 'That web address is not valid.',
        'invalid_business_owner' => 'We could not identify the account registering this business.',

        // One sentence for every way a phone number is turned down - a country
        // we do not operate in, or digits that are not a number within one we
        // do. They mean the same thing to the person filling in the form.
        'unsupported_phone_number' => 'That is not a valid phone number for the selected country.',

        'industry_not_found' => 'We could not find that industry.',
        'staff_member_not_found' => 'We could not find that team member.',

        // Customers.
        'customer_not_found' => 'We could not find that customer.',
        'customer_name_taken' => 'There is already a customer with that name.',
        'customer_already_inactive' => 'That customer is already inactive.',
        'invalid_customer_name' => 'That customer name is not valid.',
        'invalid_customer_email' => 'That customer email address is not valid.',
        'invalid_customer_phone' => 'That customer phone number is not valid.',

    ],

];
