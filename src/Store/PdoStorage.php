<?php

declare(strict_types=1);

namespace ContactWarden\Store;

use ContactWarden\Reputation\ReputationRecord;
use ContactWarden\Token\ConsumedToken;
use ContactWarden\Token\TokenConsumptionResult;

final class PdoStorage implements StorageInterface
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

    public function storeToken(string $tokenId, array $fieldMap, \DateTimeImmutable $issuedAt, \DateTimeImmutable $expiresAt, string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cw_tokens (id, field_map, issued_at, expires_at, ip) VALUES (:id, :field_map, :issued_at, :expires_at, :ip)'
        );
        $stmt->execute([
            'id' => $tokenId,
            'field_map' => json_encode($fieldMap, JSON_THROW_ON_ERROR),
            'issued_at' => $issuedAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'ip' => $ip,
        ]);
    }

    public function consumeToken(string $tokenId): TokenConsumptionResult
    {
        $stmt = $this->pdo->prepare(
            'SELECT field_map, issued_at, expires_at, consumed_at FROM cw_tokens WHERE id = :id'
        );
        $stmt->execute(['id' => $tokenId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false || $row['consumed_at'] !== null) {
            return TokenConsumptionResult::invalid();
        }

        $now = new \DateTimeImmutable();
        if (new \DateTimeImmutable($row['expires_at']) <= $now) {
            return TokenConsumptionResult::expired();
        }

        $update = $this->pdo->prepare('UPDATE cw_tokens SET consumed_at = :now WHERE id = :id');
        $update->execute(['now' => $now->format('Y-m-d H:i:s'), 'id' => $tokenId]);

        return TokenConsumptionResult::valid(new ConsumedToken(
            fieldMap: json_decode($row['field_map'], true, flags: JSON_THROW_ON_ERROR),
            issuedAt: new \DateTimeImmutable($row['issued_at']),
        ));
    }

    public function logSubmission(string $ip, string $decision, int $score, array $meta, \DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cw_submissions (created_at, ip, decision, score, meta) VALUES (:created_at, :ip, :decision, :score, :meta)'
        );
        $stmt->execute([
            'created_at' => $at->format('Y-m-d H:i:s'),
            'ip' => $ip,
            'decision' => $decision,
            'score' => $score,
            'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
        ]);
    }

    public function logAbuse(string $ip, string $decision, int $score, array $evidence, \DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cw_abuse_log (created_at, ip, score, decision, evidence) VALUES (:created_at, :ip, :score, :decision, :evidence)'
        );
        $stmt->execute([
            'created_at' => $at->format('Y-m-d H:i:s'),
            'ip' => $ip,
            'score' => $score,
            'decision' => $decision,
            'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
        ]);
    }

    public function getReputation(string $subject): ?ReputationRecord
    {
        $stmt = $this->pdo->prepare('SELECT score, last_updated FROM cw_reputation WHERE subject = :subject');
        $stmt->execute(['subject' => $subject]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new ReputationRecord((float) $row['score'], new \DateTimeImmutable($row['last_updated']));
    }

    public function setReputation(string $subject, float $score, \DateTimeImmutable $lastUpdated): void
    {
        // Deliberately portable (MySQL + SQLite) rather than an atomic upsert: `ON DUPLICATE KEY
        // UPDATE` is MySQL-only and SQLite's UPSERT syntax differs, and this isn't a
        // high-concurrency-per-subject path, so the small TOCTOU race is an acceptable tradeoff.
        $exists = $this->pdo->prepare('SELECT 1 FROM cw_reputation WHERE subject = :subject');
        $exists->execute(['subject' => $subject]);

        $sql = $exists->fetchColumn() === false
            ? 'INSERT INTO cw_reputation (subject, score, last_updated) VALUES (:subject, :score, :last_updated)'
            : 'UPDATE cw_reputation SET score = :score, last_updated = :last_updated WHERE subject = :subject';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'subject' => $subject,
            'score' => $score,
            'last_updated' => $lastUpdated->format('Y-m-d H:i:s'),
        ]);
    }

    public function countRecentSubmissions(string $ip, \DateTimeImmutable $since): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cw_submissions WHERE ip = :ip AND created_at >= :since');
        $stmt->execute(['ip' => $ip, 'since' => $since->format('Y-m-d H:i:s')]);

        return (int) $stmt->fetchColumn();
    }
}
