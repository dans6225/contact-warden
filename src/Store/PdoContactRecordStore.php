<?php

declare(strict_types=1);

namespace ContactWarden\Store;

use ContactWarden\Http\RequestContext;

final class PdoContactRecordStore implements ContactRecordStore
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public static function fromConfig(DatabaseConfig $config): self
    {
        return new self(new \PDO(
            $config->toDsn(),
            $config->username,
            $config->password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        ));
    }

    public function record(array $data, RequestContext $context): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cw_contacts (created_at, ip, fields) VALUES (:created_at, :ip, :fields)'
        );
        $stmt->execute([
            'created_at' => $context->receivedAt->format('Y-m-d H:i:s'),
            'ip' => $context->ip,
            'fields' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }
}
