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

        // A public slug that resolves to nothing. The wording says "not found"
        // and nothing more: confirming that a slug exists is an information leak.
        'business_not_found' => 'No hemos encontrado ese negocio.',

        // The database rejected an appointment overlapping an existing one.
        'appointment_overlap' => 'Acaban de reservar ese horario. Elige otro, por favor.',

    ],

];
