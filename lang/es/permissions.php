<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Etiquetas de permisos
    |--------------------------------------------------------------------------
    |
    | Cómo se llama cada permiso de config/authorization.php y qué permite hacer
    | en realidad. Las etiquetas nunca viven en la base de datos: la tabla de
    | permisos guarda la clave y la clave se resuelve aquí, así que cambiar un
    | texto es editar una traducción, no migrar.
    |
    | Una entrada plana por permiso, con la misma clave que el nombre escrito en
    | el catálogo: "manage_business" se guarda con la clave de descripción
    | "permissions.manage_business.description".
    |
    */

    'manage_business' => [
        'label' => 'Gestionar el negocio',
        'description' => 'Editar el perfil del negocio, sus datos de contacto, su zona horaria y su política de reservas.',
    ],

    'view_business_settings' => [
        'label' => 'Ver la configuración del negocio',
        'description' => 'Ver la marca, los datos de contacto, la ubicación, los horarios y los enlaces del negocio.',
    ],

    'edit_business_settings' => [
        'label' => 'Editar la configuración del negocio',
        'description' => 'Cambiar la marca, los datos de contacto, la ubicación, los horarios y los enlaces del negocio.',
    ],

    'manage_staff' => [
        'label' => 'Gestionar al personal',
        'description' => 'Invitar personas al negocio, cambiar lo que pueden hacer y darlas de baja.',
    ],

    'view_services' => [
        'label' => 'Ver los servicios',
        'description' => 'Ver los servicios que ofrece el negocio, sus precios y quién puede realizarlos.',
    ],

    'create_service' => [
        'label' => 'Crear servicios',
        'description' => 'Añadir un servicio nuevo al catálogo o duplicar uno existente.',
    ],

    'edit_service' => [
        'label' => 'Editar servicios',
        'description' => 'Cambiar un servicio, su imagen y las personas que pueden realizarlo.',
    ],

    'delete_service' => [
        'label' => 'Eliminar servicios',
        'description' => 'Quitar un servicio del catálogo.',
    ],

];
