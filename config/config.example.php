<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'deportes_pnp',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Deportes PNP - Carrera 5K',
        // Déjalo vacío para detectar automáticamente la ruta en XAMPP.
        'base_url' => '',
        'timezone' => 'America/Panama',
        'max_upload_bytes' => 5 * 1024 * 1024,
    ],
];
