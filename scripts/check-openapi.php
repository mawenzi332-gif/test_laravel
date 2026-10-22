<?php

if ($argc !== 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/check-openapi.php <openapi.json>\n");
    exit(2);
}

$document = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);

if (! str_starts_with($document['openapi'] ?? '', '3.1.')) {
    throw new RuntimeException('Expected an OpenAPI 3.1 document.');
}

$expected = [
    '/v1/auth/register' => ['post'],
    '/v1/auth/login' => ['post'],
    '/v1/auth/logout' => ['post'],
    '/v1/auth/forgot-password' => ['post'],
    '/v1/auth/reset-password' => ['post'],
    '/v1/me' => ['get', 'patch'],
    '/v1/comments' => ['get', 'post'],
    '/v1/comments/preview' => ['post'],
    '/v1/comments/{comment}' => ['patch', 'delete'],
    '/v1/attachments/{attachment}' => ['get'],
    '/v1/attachments/{attachment}/preview' => ['get'],
];

foreach ($expected as $path => $methods) {
    foreach ($methods as $method) {
        $operation = $document['paths'][$path][$method] ?? null;
        if (! is_array($operation)) {
            throw new RuntimeException("Missing runtime API operation: {$method} {$path}");
        }

        foreach ($operation['responses'] ?? [] as $status => $response) {
            if ((int) $status < 400) {
                continue;
            }

            $schema = $response['content']['application/json']['schema'] ?? null;
            if (($schema['properties']['error']['type'] ?? null) !== 'object') {
                throw new RuntimeException("{$method} {$path} {$status} does not document the API error envelope.");
            }
        }
    }
}

$commentsList = $document['paths']['/v1/comments']['get']['responses']['200']['content']['application/json']['schema'] ?? [];
foreach (['data', 'links', 'meta'] as $property) {
    if (! isset($commentsList['properties'][$property])) {
        throw new RuntimeException("The comments list response is missing pagination property [{$property}].");
    }
}

foreach ([['/v1/auth/register', '201'], ['/v1/auth/logout', '204']] as [$path, $status]) {
    if (! isset($document['paths'][$path]['post']['responses'][$status])) {
        throw new RuntimeException("The runtime success status {$status} is missing for POST {$path}.");
    }
}

foreach (['/v1/auth/forgot-password', '/v1/auth/reset-password'] as $path) {
    $schema = $document['paths'][$path]['post']['responses']['200']['content']['application/json']['schema'] ?? [];
    if (! isset($schema['properties']['message'])) {
        throw new RuntimeException("The POST {$path} response must document its message field.");
    }
}

fwrite(STDOUT, 'OpenAPI contract check passed: '.count($expected).' paths, '.array_sum(array_map('count', $expected))." operations.\n");
