<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Etiquetas de roles
    |--------------------------------------------------------------------------
    |
    | Cómo se llama cada rol de config/authorization.php. Igual que con los
    | permisos, la tabla de roles guarda la clave y nunca el texto.
    |
    | Una sola clave sirve para todas las copias de un rol plantilla: cada
    | negocio tiene su propia fila "staff", todas llevan el mismo nombre y todas
    | resuelven aquí a roles.staff. Lo que cada negocio cambia son los permisos
    | de su propia fila, no cómo se llama el rol.
    |
    */

    'owner' => [
        'label' => 'Propietario',
        'description' => 'Registró el negocio. Tiene todos los permisos y el rol en sí no se puede editar ni eliminar.',
    ],

    'staff' => [
        'label' => 'Personal',
        'description' => 'Trabaja en el negocio y opera su agenda. Cada negocio decide qué puede hacer este rol.',
    ],

];
