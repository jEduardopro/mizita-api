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

        // One sentence for every way a phone number is turned down - a country
        // we do not operate in, or digits that are not a number within one we
        // do. They mean the same thing to the person filling in the form.
        'unsupported_phone_number' => 'Ese no es un número de teléfono válido para el país seleccionado.',

        'industry_not_found' => 'No hemos encontrado ese rubro.',
        'staff_member_not_found' => 'No hemos encontrado a esa persona del equipo.',

        // Customers.
        'customer_not_found' => 'No hemos encontrado a ese cliente.',
        'customer_name_taken' => 'Ya existe un cliente con ese nombre.',
        'customer_already_inactive' => 'Ese cliente ya está inactivo.',
        'invalid_customer_name' => 'Ese nombre de cliente no es válido.',

    ],

];
