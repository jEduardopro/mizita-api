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

        // The caller is a member of the business but the role they hold there
        // does not carry the permission the route needs. The wording names no
        // permission: what is missing is the owner's to know, not the caller's.
        'missing_permission' => 'You are not allowed to perform this action in this business.',

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
        'invalid_business_contact_email' => 'That contact email address is not valid.',
        'invalid_business_about' => 'That description is not valid.',
        'invalid_business_currency' => 'That currency is not valid.',
        'invalid_business_coordinates' => 'That map location is not valid.',
        'business_logo_too_large' => 'That logo is too large. Please use one under 2 MB.',
        'unsupported_business_logo' => 'That file is not an image we support. Please use a JPG, PNG or WebP.',

        // One sentence for every way a phone number is turned down - a country
        // we do not operate in, or digits that are not a number within one we
        // do. They mean the same thing to the person filling in the form.
        'unsupported_phone_number' => 'That is not a valid phone number for the selected country.',

        'industry_not_found' => 'We could not find that industry.',
        'staff_member_not_found' => 'We could not find that team member.',

        // Services. The wording never repeats the name, slug or amount that was
        // rejected, and never confirms that a service exists in another business.
        'service_not_found' => 'We could not find that service.',
        'service_name_taken' => 'You already have a service with that name.',
        'service_slug_taken' => 'That web address is already in use by another service.',
        'service_name_not_sluggable' => 'That name cannot be turned into a web address. Please use letters or numbers.',
        'invalid_service_name' => 'That service name is not valid.',
        'invalid_service_slug' => 'That web address is not valid.',
        'invalid_service_description' => 'That description is not valid.',
        'invalid_service_duration' => 'That duration is not valid.',
        'invalid_service_buffer' => 'That buffer time is not valid.',
        'invalid_service_price' => 'That price is not valid.',
        'invalid_service_color' => 'That colour is not one a service may be given.',
        'invalid_service_search' => 'That search is too long.',
        'service_already_active' => 'That service is already visible.',
        'service_already_inactive' => 'That service is already hidden.',
        'unknown_staff_member' => 'One of the selected team members is not available.',
        'service_requires_staff' => 'You need to pick at least one person to perform this service.',
        'service_image_too_large' => 'That image is too large. Please use one under 2 MB.',
        'unsupported_service_image' => 'That file is not an image we support. Please use a JPG, PNG or WebP.',

        'customer_not_found' => 'We could not find that customer.',
        'customer_email_taken' => 'You already have a customer with that email address.',
        'customer_phone_taken' => 'You already have a customer with that phone number.',
        'invalid_customer_name' => 'That customer name is not valid.',
        'invalid_customer_email' => 'That email address is not valid.',
        'invalid_customer_phone' => 'That is not a valid phone number for the selected country.',
        'invalid_customer_birth_date' => 'That date of birth is not valid.',
        'invalid_customer_notes' => 'Those notes are too long.',
        'invalid_customer_search' => 'That search is too long.',
        'customer_photo_too_large' => 'That photo is too large. Please use one under 2 MB.',
        'unsupported_customer_photo' => 'That file is not a photo we support. Please use a JPG, PNG or WebP.',

        'invalid_address_street' => 'That street is not valid.',
        'invalid_address_city' => 'That city is not valid.',
        'invalid_address_postal_code' => 'That postal code is not valid.',
        'address_city_cannot_be_cleared' => 'The city of a saved address cannot be removed, only changed.',
        'address_postal_code_cannot_be_cleared' => 'The postal code of a saved address cannot be removed, only changed.',
        'invalid_coordinates' => 'That location is not a point on the map.',
        'unsupported_country' => 'We do not operate in that country yet.',
        'unknown_state' => 'That state is not one we have on record.',

        'invalid_link_url' => 'That link is not a valid web address.',
        'invalid_link_platform' => 'That is not a network you can link to.',
        'link_platform_mismatch' => 'That link does not point at the network you picked.',
        'duplicate_link_platform' => 'You already have a link for that network.',

        'invalid_time_of_day' => 'That time is not valid. Please use the HH:MM format.',
        'invalid_weekday' => 'That is not a day of the week.',
        'schedule_interval_inverted' => 'An opening time has to come before its closing time.',
        'overlapping_schedule_intervals' => 'Two time ranges on the same day overlap.',

        'booking_page_not_found' => 'We could not find your booking page.',
        'booking_page_image_too_large' => 'That image is too large. Please use one under 5 MB.',
        'unsupported_booking_page_image' => 'That file is not an image we support. Please use a JPG, PNG or WebP.',
        'booking_page_gallery_full' => 'Your gallery is full. Remove a photo before adding another.',
        'booking_page_image_not_found' => 'We could not find that photo.',
        'invalid_gallery_order' => 'That photo order does not match the photos in your gallery.',
        'invalid_booking_page_accent_color' => 'That colour is not one a booking page may be given.',
        'invalid_booking_page_button_shape' => 'That button shape is not one a booking page may be given.',
        'invalid_booking_page_theme' => 'That theme is not one a booking page may be given.',

        'appointment_not_found' => 'We could not find that appointment.',
        'appointment_service_not_found' => 'That service is not one this business offers.',
        'appointment_customer_not_found' => 'That customer is not one of this business.',
        'appointment_staff_not_found' => 'That team member is not one of this business.',
        'invalid_appointment_schedule' => 'That appointment time is not valid.',
        'invalid_appointment_notes' => 'Those notes are too long.',
        'invalid_calendar_range' => 'That calendar range is not valid.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Warnings
    |--------------------------------------------------------------------------
    |
    | A warning rides alongside a successful response: the work was done, but
    | something secondary did not go to plan. Keyed exactly like errors, so a
    | Warning code is looked up here and nowhere else.
    |
    */

    'warnings' => [],

];
