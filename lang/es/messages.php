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
        'unauthenticated' => 'Necesitas iniciar sesión para continuar.',
        'forbidden' => 'No tienes permiso para realizar esta acción.',
        'not_found' => 'El recurso solicitado no existe.',
        'conflict' => 'Esta acción entra en conflicto con el estado actual del recurso.',
        'too_many_requests' => 'Demasiadas solicitudes. Inténtalo de nuevo en unos instantes.',
        'server_error' => 'Algo ha ido mal por nuestra parte. Inténtalo de nuevo.',

        // A caller reached a tenant-scoped route without belonging to a business.
        'no_business' => 'Este usuario no pertenece a ningún negocio.',

        // A caller asked to operate a business they are not a member of. The
        // distinction from no_business matters: this one does have businesses,
        // just not the one requested. The wording names none of them.
        'business_not_accessible' => 'No tienes acceso a ese negocio.',

        // The caller is a member of the business but the role they hold there
        // does not carry the permission the route needs. The wording names no
        // permission: what is missing is the owner's to know, not the caller's.
        'missing_permission' => 'No tienes permiso para hacer esto en este negocio.',

        // A public slug that resolves to nothing. The wording says "not found"
        // and nothing more: confirming that a slug exists is an information leak.
        'business_not_found' => 'No hemos encontrado ese negocio.',

        // The database rejected an appointment overlapping an existing one.
        'appointment_overlap' => 'Acaban de reservar ese horario. Elige otro, por favor.',

        // The Google round trip did not complete: consent was denied, the
        // session expired, or the profile came back unusable.
        'google_sign_in_failed' => 'No hemos podido completar el inicio de sesión con Google. Inténtalo de nuevo.',

        // Google has not verified the address, so it proves nothing. The wording
        // stays vague on purpose: it must not reveal whether an account exists.
        'google_email_not_verified' => 'El correo de tu cuenta de Google no está verificado. Verifícalo en Google e inténtalo de nuevo.',

        // The ID token failed verification, or was never an ID token.
        'google_invalid_id_token' => 'Esa credencial de inicio de sesión con Google no es válida.',

        // Accounts. None of these name the address involved: an error body that
        // confirms an email is registered is an account enumeration oracle.
        'account_already_registered' => 'Ya existe una cuenta con ese correo.',
        'account_not_found' => 'No hemos encontrado esa cuenta.',
        'invalid_account_email' => 'Ese correo electrónico no es válido.',
        'invalid_account_name' => 'Ese nombre no es válido.',
        'invalid_provider_user_id' => 'No hemos podido identificar esa cuenta de inicio de sesión.',
        'social_identity_already_linked' => 'Esa cuenta de inicio de sesión ya está vinculada a otra cuenta.',

        // Business onboarding. The wording never repeats the name, slug or
        // timezone that was rejected - the caller already knows what it sent.
        'business_name_taken' => 'Ya existe un negocio con ese nombre.',
        'business_slug_taken' => 'Esa dirección web ya está en uso. Elige otra.',
        'owner_already_has_business' => 'Ya tienes un negocio registrado.',
        'business_name_not_sluggable' => 'Ese nombre no se puede convertir en una dirección web. Usa letras o números.',
        'unknown_industry' => 'Ese rubro no está entre los que admitimos.',
        'invalid_timezone' => 'Esa zona horaria no es válida.',
        'invalid_business_name' => 'Ese nombre de negocio no es válido.',
        'invalid_business_slug' => 'Esa dirección web no es válida.',
        'invalid_business_owner' => 'No hemos podido identificar la cuenta que registra este negocio.',
        'invalid_business_contact_email' => 'Ese correo de contacto no es válido.',
        'invalid_business_about' => 'Esa descripción no es válida.',
        'invalid_business_currency' => 'Esa moneda no es válida.',
        'invalid_business_coordinates' => 'Esa ubicación en el mapa no es válida.',
        'business_logo_too_large' => 'Ese logotipo es demasiado grande. Usa uno de menos de 2 MB.',
        'unsupported_business_logo' => 'Ese archivo no es una imagen compatible. Usa un JPG, PNG o WebP.',

        // One sentence for every way a phone number is turned down - a country
        // we do not operate in, or digits that are not a number within one we
        // do. They mean the same thing to the person filling in the form.
        'unsupported_phone_number' => 'Ese no es un número de teléfono válido para el país seleccionado.',

        'industry_not_found' => 'No hemos encontrado ese rubro.',
        'staff_member_not_found' => 'No hemos encontrado a esa persona del equipo.',

        // Services. The wording never repeats the name, slug or amount that was
        // rejected, and never confirms that a service exists in another business.
        'service_not_found' => 'No hemos encontrado ese servicio.',
        'service_name_taken' => 'Ya tienes un servicio con ese nombre.',
        'service_slug_taken' => 'Esa dirección web ya la usa otro servicio.',
        'service_name_not_sluggable' => 'Ese nombre no se puede convertir en una dirección web. Usa letras o números.',
        'invalid_service_name' => 'Ese nombre de servicio no es válido.',
        'invalid_service_slug' => 'Esa dirección web no es válida.',
        'invalid_service_description' => 'Esa descripción no es válida.',
        'invalid_service_duration' => 'Esa duración no es válida.',
        'invalid_service_buffer' => 'Ese tiempo de buffer no es válido.',
        'invalid_service_price' => 'Ese precio no es válido.',
        'invalid_service_color' => 'Ese color no es uno de los que puede tener un servicio.',
        'invalid_service_search' => 'Esa búsqueda es demasiado larga.',
        'service_already_active' => 'Ese servicio ya está visible.',
        'service_already_inactive' => 'Ese servicio ya está oculto.',
        'unknown_staff_member' => 'Alguna de las personas seleccionadas no está disponible.',
        'service_requires_staff' => 'Necesitas elegir al menos a una persona para dar este servicio.',
        'service_image_too_large' => 'Esa imagen es demasiado grande. Usa una de menos de 2 MB.',
        'unsupported_service_image' => 'Ese archivo no es una imagen compatible. Usa un JPG, PNG o WebP.',

        'customer_not_found' => 'No hemos encontrado a ese cliente.',
        'customer_email_taken' => 'Ya tienes un cliente con ese correo electrónico.',
        'customer_phone_taken' => 'Ya tienes un cliente con ese número de teléfono.',
        'invalid_customer_name' => 'Ese nombre de cliente no es válido.',
        'invalid_customer_email' => 'Ese correo electrónico no es válido.',
        'invalid_customer_phone' => 'Ese no es un número de teléfono válido para el país seleccionado.',
        'invalid_customer_birth_date' => 'Esa fecha de nacimiento no es válida.',
        'invalid_customer_notes' => 'Esas notas son demasiado largas.',
        'invalid_customer_search' => 'Esa búsqueda es demasiado larga.',
        'invalid_guest_contact' => 'Deja un correo electrónico o un número de teléfono para poder confirmar la reserva.',
        'customer_photo_too_large' => 'Esa foto es demasiado grande. Usa una de menos de 2 MB.',
        'unsupported_customer_photo' => 'Ese archivo no es una foto compatible. Usa un JPG, PNG o WebP.',

        'invalid_address_street' => 'Esa calle no es válida.',
        'invalid_address_city' => 'Esa ciudad no es válida.',
        'invalid_address_postal_code' => 'Ese código postal no es válido.',
        'address_city_cannot_be_cleared' => 'La ciudad de una dirección guardada no se puede quitar, solo cambiar.',
        'address_postal_code_cannot_be_cleared' => 'El código postal de una dirección guardada no se puede quitar, solo cambiar.',
        'invalid_coordinates' => 'Esa ubicación no es un punto del mapa.',
        'unsupported_country' => 'Todavía no operamos en ese país.',
        'unknown_state' => 'Ese estado no es uno de los que tenemos registrados.',

        'invalid_link_url' => 'Ese enlace no es una dirección web válida.',
        'invalid_link_platform' => 'Esa no es una red a la que puedas enlazar.',
        'link_platform_mismatch' => 'Ese enlace no apunta a la red que elegiste.',
        'duplicate_link_platform' => 'Ya tienes un enlace para esa red.',

        'invalid_time_of_day' => 'Esa hora no es válida. Usa el formato HH:MM.',
        'invalid_weekday' => 'Ese no es un día de la semana.',
        'schedule_interval_inverted' => 'La hora de apertura tiene que ser anterior a la de cierre.',
        'overlapping_schedule_intervals' => 'Dos rangos de horario del mismo día se traslapan.',

        'booking_page_not_found' => 'No hemos encontrado tu página de reservas.',
        'booking_page_image_too_large' => 'Esa imagen es demasiado grande. Usa una de menos de 5 MB.',
        'unsupported_booking_page_image' => 'Ese archivo no es una imagen compatible. Usa un JPG, PNG o WebP.',
        'booking_page_gallery_full' => 'Tu galería está llena. Quita una foto antes de agregar otra.',
        'booking_page_image_not_found' => 'No hemos encontrado esa foto.',
        'invalid_gallery_order' => 'Ese orden de fotos no coincide con las fotos de tu galería.',
        'invalid_booking_page_accent_color' => 'Ese color no es uno de los que puede tener una página de reservas.',
        'invalid_booking_page_button_shape' => 'Esa forma de botón no es una de las que puede tener una página de reservas.',
        'invalid_booking_page_theme' => 'Ese tema no es uno de los que puede tener una página de reservas.',

        'appointment_not_found' => 'No hemos encontrado esa cita.',
        'appointment_service_not_found' => 'Ese servicio no es uno de los que ofrece este negocio.',
        'appointment_customer_not_found' => 'Ese cliente no es uno de este negocio.',
        'appointment_staff_not_found' => 'Esa persona del equipo no es de este negocio.',
        'invalid_appointment_schedule' => 'Ese horario de cita no es válido.',
        'invalid_appointment_notes' => 'Esas notas son demasiado largas.',
        'invalid_calendar_range' => 'Ese rango de la agenda no es válido.',

        'invalid_availability_range' => 'Ese rango de disponibilidad no es válido.',
        'availability_range_too_wide' => 'Ese rango de disponibilidad abarca demasiados días.',
        'invalid_slot_query' => 'Elige un servicio y un miembro del equipo para ver los horarios disponibles.',
        'bookable_service_not_found' => 'Este servicio no está disponible para reservar.',
        'staff_member_not_bookable' => 'Este miembro del equipo no ofrece el servicio seleccionado.',
        'invalid_booking_block' => 'Este servicio no tiene una duración válida, así que no se pueden ofrecer horarios.',

        'business_currently_closed' => 'Este negocio está cerrado en este momento. Podrás reservar cuando vuelva a abrir.',
        'guest_booking_not_found' => 'No encontramos ninguna reserva con ese código y ese enlace.',
        'appointment_slot_not_bookable' => 'Ese horario no está disponible para reservar.',
        'appointment_changes_not_allowed' => 'Este negocio no permite que los clientes modifiquen sus reservas.',
        'cancellation_window_closed' => 'El plazo para modificar esta reserva ya se cerró.',
        'invalid_guest_name' => 'Ingresa el nombre de la persona que asistirá.',
        'invalid_guest_email' => 'Ese correo electrónico no es válido.',
        'invalid_guest_phone' => 'Ese número de teléfono no es válido.',
        'missing_guest_contact_channel' => 'Proporciona un correo electrónico o un número de teléfono.',

        'invalid_reference_code' => 'Ese código de reserva no es válido.',
        'invalid_manage_token' => 'Ese enlace de gestión no es válido.',
        'appointment_already_cancelled' => 'Esa cita ya fue cancelada.',
        'appointment_already_started' => 'Esa cita ya comenzó y ya no puede modificarse.',

        'booking_policy_not_found' => 'No hemos encontrado esa política de reservas.',
        'incomplete_booking_policy' => 'La política de reservas debe guardarse con todos sus ajustes.',
        'invalid_lead_time' => 'Esa antelación mínima de reserva no es válida.',
        'invalid_booking_window' => 'Ese periodo de reserva no es válido.',
        'invalid_slot_granularity' => 'Ese intervalo entre horarios no es válido.',
        'invalid_cancellation_window' => 'Ese plazo de cancelación no es válido.',
        'invalid_policy_message' => 'Ese mensaje de la política no es válido.',

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
