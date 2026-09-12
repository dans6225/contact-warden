<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use ContactWarden\FieldObfuscation\FieldMap;

header('Content-Type: application/json');

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$fieldMap = FieldMap::generate($fieldNames);
$tokenId = $tokenIssuer->issue($fieldMap, $ip);

echo json_encode(['token' => $tokenId, 'fieldMap' => $fieldMap], JSON_THROW_ON_ERROR);
