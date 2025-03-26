<?php

return [
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // En production, spécifiez l'URL de votre app Spring Boot
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
