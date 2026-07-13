<?php
$envPath = __DIR__ . '/../.env';
$_env = file_exists($envPath) ? parse_ini_file($envPath) : [];

return [
    'driver' => 'brevo',
    'api_key' => $_env['BREVO_API_KEY'] ?? '',
    'from_email' => 'noreply@yanmar.co.id',
    'from_name' => '4M Change System',
    'enabled' => true,
];