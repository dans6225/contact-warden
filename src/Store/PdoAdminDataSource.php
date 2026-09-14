<?php

declare(strict_types=1);

namespace ContactWarden\Store;

use ContactWarden\Admin\AbuseQuery;
use ContactWarden\Admin\AbuseRecord;
use ContactWarden\Admin\AdminDataSource;
use ContactWarden\Admin\ReputationEntry;
use ContactWarden\Admin\ReputationQuery;
use ContactWarden\Admin\SubmissionQuery;
use ContactWarden\Admin\SubmissionRecord;
use ContactWarden\Reputation\ReputationRecord;

final class PdoAdminDataSource implements AdminDataSource
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function listSubmissions(SubmissionQuery $query): array
    {
        [$where, $params] = $this->logConditions($query->decision, $query->ip, $query->since, $query->until);

        $stmt = $this->preparePage(
            'SELECT id, created_at, ip, decision, score, meta FROM cw_submissions' . $where . ' ORDER BY created_at DESC, id DESC',
            $params,
            $query->limit,
            $query->offset,
        );

        return array_map(
            static fn (array $row): SubmissionRecord => new SubmissionRecord(
                id: (int) $row['id'],
                createdAt: new \DateTimeImmutable($row['created_at']),
                ip: $row['ip'],
                decision: $row['decision'],
                score: (int) $row['score'],
                meta: $row['meta'] !== null ? json_decode($row['meta'], true, flags: JSON_THROW_ON_ERROR) : [],
            ),
            $stmt->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    public function countSubmissions(SubmissionQuery $query): int
    {
        [$where, $params] = $this->logConditions($query->decision, $query->ip, $query->since, $query->until);

        return $this->count('cw_submissions', $where, $params);
    }

    public function getSubmission(int $id): ?SubmissionRecord
    {
        $stmt = $this->pdo->prepare('SELECT id, created_at, ip, decision, score, meta FROM cw_submissions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new SubmissionRecord(
            id: (int) $row['id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            ip: $row['ip'],
            decision: $row['decision'],
            score: (int) $row['score'],
            meta: $row['meta'] !== null ? json_decode($row['meta'], true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    public function listAbuseEvents(AbuseQuery $query): array
    {
        [$where, $params] = $this->logConditions($query->decision, $query->ip, $query->since, $query->until);

        $stmt = $this->preparePage(
            'SELECT id, created_at, ip, decision, score, evidence FROM cw_abuse_log' . $where . ' ORDER BY created_at DESC, id DESC',
            $params,
            $query->limit,
            $query->offset,
        );

        return array_map(
            static fn (array $row): AbuseRecord => new AbuseRecord(
                id: (int) $row['id'],
                createdAt: new \DateTimeImmutable($row['created_at']),
                ip: $row['ip'],
                decision: $row['decision'],
                score: (int) $row['score'],
                evidence: json_decode($row['evidence'], true, flags: JSON_THROW_ON_ERROR),
            ),
            $stmt->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    public function countAbuseEvents(AbuseQuery $query): int
    {
        [$where, $params] = $this->logConditions($query->decision, $query->ip, $query->since, $query->until);

        return $this->count('cw_abuse_log', $where, $params);
    }

    public function listReputations(ReputationQuery $query): array
    {
        [$where, $params] = $this->reputationConditions($query->minScore);

        $stmt = $this->preparePage(
            'SELECT subject, score, last_updated FROM cw_reputation' . $where . ' ORDER BY score DESC',
            $params,
            $query->limit,
            $query->offset,
        );

        return array_map(
            static fn (array $row): ReputationEntry => new ReputationEntry(
                subject: $row['subject'],
                record: new ReputationRecord((float) $row['score'], new \DateTimeImmutable($row['last_updated'])),
            ),
            $stmt->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    public function countReputations(ReputationQuery $query): int
    {
        [$where, $params] = $this->reputationConditions($query->minScore);

        return $this->count('cw_reputation', $where, $params);
    }

    public function countTokens(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM cw_tokens')->fetchColumn();
    }

    public function countExpiredOrConsumedTokens(\DateTimeImmutable $now): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cw_tokens WHERE expires_at < :now OR consumed_at IS NOT NULL');
        $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function reputationConditions(?float $minScore): array
    {
        if ($minScore === null) {
            return ['', []];
        }

        return [' WHERE score >= :min_score', [':min_score' => $minScore]];
    }

    /**
     * Shared filter builder for cw_submissions/cw_abuse_log — both are
     * (decision, ip, created_at) logs with the same optional filters.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function logConditions(?string $decision, ?string $ip, ?\DateTimeImmutable $since, ?\DateTimeImmutable $until): array
    {
        $clauses = [];
        $params = [];

        if ($decision !== null) {
            $clauses[] = 'decision = :decision';
            $params[':decision'] = $decision;
        }
        if ($ip !== null) {
            $clauses[] = 'ip = :ip';
            $params[':ip'] = $ip;
        }
        if ($since !== null) {
            $clauses[] = 'created_at >= :since';
            $params[':since'] = $since->format('Y-m-d H:i:s');
        }
        if ($until !== null) {
            $clauses[] = 'created_at <= :until';
            $params[':until'] = $until->format('Y-m-d H:i:s');
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /** @param array<string, mixed> $params */
    private function preparePage(string $sqlWithoutPaging, array $params, int $limit, int $offset): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sqlWithoutPaging . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /** @param array<string, mixed> $params */
    private function count(string $table, string $where, array $params): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . $where);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
