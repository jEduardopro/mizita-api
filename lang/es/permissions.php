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

    'view_staff_members' => [
        'label' => 'Ver el equipo',
        'description' => 'Ver quién trabaja en el negocio, sus datos de contacto y su nivel de permisos.',
    ],

    'create_staff_member' => [
        'label' => 'Invitar al equipo',
        'description' => 'Invitar personas a unirse al negocio y volver a enviarles la invitación.',
    ],

    'edit_staff_member' => [
        'label' => 'Editar al equipo',
        'description' => 'Cambiar el nombre, la foto, el teléfono, la descripción del puesto y el nivel de permisos de una persona del equipo.',
    ],

    'delete_staff_member' => [
        'label' => 'Quitar personas del equipo',
        'description' => 'Dar de baja a alguien del negocio cuando ya no tiene citas próximas.',
    ],

    'reveal_temporary_password' => [
        'label' => 'Copiar contraseñas temporales',
        'description' => 'Copiar la contraseña temporal de una persona invitada al equipo, hasta que inicie sesión por primera vez.',
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

    'view_customers' => [
        'label' => 'Ver los clientes',
        'description' => 'Ver los clientes del negocio, sus datos de contacto, su dirección y sus notas.',
    ],

    'create_customer' => [
        'label' => 'Crear clientes',
        'description' => 'Añadir un cliente nuevo al negocio.',
    ],

    'edit_customer' => [
        'label' => 'Editar clientes',
        'description' => 'Cambiar un cliente, sus datos de contacto, su dirección y sus notas.',
    ],

    'delete_customer' => [
        'label' => 'Eliminar clientes',
        'description' => 'Quitar un cliente del negocio, junto con su teléfono y su dirección.',
    ],

    'view_appointments' => [
        'label' => 'Ver las citas',
        'description' => 'Ver la agenda del negocio, quién tiene cita, para qué y cuándo.',
    ],

    'create_appointment' => [
        'label' => 'Crear citas',
        'description' => 'Agendar a un cliente para un servicio con una persona del equipo.',
    ],

    'edit_appointment' => [
        'label' => 'Editar citas',
        'description' => 'Mover una cita, cambiar su servicio, su persona del equipo o sus notas.',
    ],

    'delete_appointment' => [
        'label' => 'Eliminar citas',
        'description' => 'Quitar una cita de la agenda.',
    ],

    'manage_all_calendars' => [
        'label' => 'Gestionar todas las agendas',
        'description' => 'Ver y cambiar las citas de todas las personas del equipo, no solo las propias.',
    ],

    'view_payments' => [
        'label' => 'Ver los cobros',
        'description' => 'Consultar qué se cobró en una cita, cómo se pagó y cuánto queda pendiente.',
    ],

    'create_payment' => [
        'label' => 'Cobrar citas',
        'description' => 'Registrar lo que un cliente pagó por una cita, con sus complementos y descuentos.',
    ],

    'void_payment_transaction' => [
        'label' => 'Anular transacciones',
        'description' => 'Deshacer un cobro registrado por error. El cobro original queda registrado y la anulación se añade como un movimiento nuevo.',
    ],

    'manage_integrations' => [
        'label' => 'Gestionar integraciones',
        'description' => 'Conectar o desconectar tu propio Google Calendar, para que tus citas aparezcan ahí y los eventos que añadas a mano bloqueen tu disponibilidad.',
    ],

];
