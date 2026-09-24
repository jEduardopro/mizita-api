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
        'staff_profile_not_found' => 'We could not find your profile.',
        'invalid_profile_name' => 'That name is not valid.',
        'invalid_profile_job_title' => 'That role is too long.',
        'invalid_profile_about' => 'That description is too long.',
        'invalid_profile_phone' => 'That is not a valid phone number for the selected country.',
        'profile_photo_too_large' => 'That photo is too large. Please use one under 2 MB.',
        'unsupported_profile_photo' => 'That file is not a photo we support. Please use a JPG, PNG or WebP.',

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
        'invalid_customer_address' => 'That address is not valid.',
        'invalid_guest_address' => 'That address is not valid.',
        'invalid_customer_phone' => 'That is not a valid phone number for the selected country.',
        'invalid_customer_birth_date' => 'That date of birth is not valid.',
        'invalid_customer_notes' => 'Those notes are too long.',
        'invalid_customer_search' => 'That search is too long.',
        'customer_photo_too_large' => 'That photo is too large. Please use one under 2 MB.',
        'unsupported_customer_photo' => 'That file is not a photo we support. Please use a JPG, PNG or WebP.',

        'invalid_address_street' => 'That street is not valid.',
        'invalid_address_state_name' => 'That state is not valid.',
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
        'schedule_not_submitted' => 'Send the full weekly schedule, even if it is empty.',

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

        'invalid_availability_range' => 'That availability range is not valid.',
        'availability_range_too_wide' => 'That availability range covers too many days.',
        'invalid_slot_query' => 'Choose a service and a team member to see available times.',
        'bookable_service_not_found' => 'This service is not available for booking.',
        'staff_member_not_bookable' => 'This team member does not offer the selected service.',
        'invalid_booking_block' => 'This service has no valid duration, so no times can be offered.',

        'guest_booking_not_found' => 'We could not find a booking for that reservation code and link.',
        'appointment_slot_not_bookable' => 'That time is not available for booking.',
        'appointment_changes_not_allowed' => 'This business does not allow customers to change their bookings.',
        'cancellation_window_closed' => 'The window for changing this booking has already closed.',
        'invalid_guest_name' => 'Please enter the name of the person attending.',
        'invalid_guest_email' => 'That email address is not valid.',
        'invalid_guest_phone' => 'That phone number is not valid.',
        'missing_guest_phone' => 'Please enter your phone number.',
        'missing_guest_email' => 'Please enter your email address.',
        'missing_guest_address' => 'Please enter your full address.',

        'invalid_reference_code' => 'That reservation code is not valid.',
        'invalid_manage_token' => 'That management link is not valid.',
        'appointment_already_cancelled' => 'That appointment has already been cancelled.',
        'appointment_already_started' => 'That appointment has already started and can no longer be changed.',

        'booking_policy_not_found' => 'We could not find that booking policy.',
        'incomplete_booking_policy' => 'The booking policy must be saved with all of its settings.',
        'invalid_lead_time' => 'That booking lead time is not valid.',
        'invalid_booking_window' => 'That booking window is not valid.',
        'invalid_slot_granularity' => 'That slot interval is not valid.',
        'invalid_cancellation_window' => 'That cancellation window is not valid.',
        'invalid_policy_message' => 'That policy message is not valid.',
        'incomplete_contact_fields' => 'The booking form fields must be saved with phone, email and address all set.',
        'invalid_contact_field_requirement' => 'Each booking form field must be hidden, optional or required.',

        // Payments. Nothing here repeats an amount the caller already sent, and
        // nothing confirms that a payment exists in another business.
        'payment_not_found' => 'We could not find that payment.',
        'payment_appointment_not_found' => 'We could not find that appointment.',
        'payment_service_not_found' => 'We could not find the service for that appointment.',
        'payment_business_not_found' => 'We could not find that business.',
        'appointment_already_has_payment' => 'That appointment has already been charged.',
        'appointment_has_payment' => 'That appointment has been charged, so it cannot be deleted. Cancel it instead.',
        'too_many_payment_items' => 'That charge has too many lines.',
        'invalid_payment_item_name' => 'That line needs a name.',
        'invalid_payment_item_amount' => 'That amount is not valid.',
        'invalid_payment_discount' => 'That discount is not valid.',
        'discount_exceeds_subtotal' => 'The discount cannot be larger than the total.',
        'payment_already_started' => 'That charge has already been collected, so its lines can no longer be changed.',
        'payment_already_settled' => 'That appointment is already paid in full.',
        'payment_overpaid' => 'That amount is larger than the outstanding balance.',
        'invalid_transaction_amount' => 'That amount is not valid.',
        'payment_transaction_not_found' => 'We could not find that transaction.',
        'payment_transaction_not_voidable' => 'Only a collected charge can be voided.',
        'void_exceeds_paid_amount' => 'That charge is larger than the amount still collected.',
        'payment_method_not_found' => 'We could not find that payment method.',
        'payment_method_not_enabled' => 'That payment method is not one you accept.',
        'payment_account_not_found' => 'We could not find that account.',
        'invalid_payment_actor' => 'We could not identify who is recording this payment.',
        'invalid_void_actor' => 'We could not identify who is voiding this transaction.',
        'invalid_money_amount' => 'That amount is not valid.',
        'currency_mismatch' => 'Those amounts are in different currencies.',

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
