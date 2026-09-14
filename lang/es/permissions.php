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
    | El anidamiento refleja el nombre del permiso, que es lo que hace que el
    | valor de permissions.description resuelva tal cual: "business.manage" se
    | guarda con la clave "permissions.business.manage.description".
    |
    */

    'business' => [

        'manage' => [
            'label' => 'Gestionar el negocio',
            'description' => 'Editar el perfil del negocio, sus datos de contacto, su zona horaria y su política de reservas.',
        ],

    ],

    'staff' => [

        'manage' => [
            'label' => 'Gestionar al personal',
            'description' => 'Invitar personas al negocio, cambiar lo que pueden hacer y darlas de baja.',
        ],

    ],

];
