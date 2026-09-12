<?php

declare(strict_types=1);

namespace ContactWarden;

use ContactWarden\Config\ContactWardenConfig;
use ContactWarden\FieldObfuscation\FieldMap;
use ContactWarden\Http\RequestContext;
use ContactWarden\Mail\MailerInterface;
use ContactWarden\Reputation\ReputationStore;
use ContactWarden\Scoring\Decision;
use ContactWarden\Scoring\ScoreEngine;
use ContactWarden\Signals\SignalInterface;
use ContactWarden\Store\StorageInterface;
use ContactWarden\Token\TokenIssuer;

/** Orchestrator: evaluate -> score -> decide -> act. */
final class Engine
{
    /** @param SignalInterface[] $signals */
    public function __construct(
        private readonly ContactWardenConfig $config,
        private readonly array $signals,
        private readonly ScoreEngine $scoreEngine,
        private readonly StorageInterface $storage,
        private readonly ReputationStore $reputationStore,
        private readonly TokenIssuer $tokenIssuer,
        private readonly MailerInterface $mailer,
        private readonly string $tokenFieldName = '_cw_token',
    ) {
    }

    public function handle(RequestContext $context): Decision
    {
        $context = $this->resolveTokenAttributes($context);

        $results = array_map(
            static fn (SignalInterface $signal) => $signal->evaluate($context),
            $this->signals,
        );

        $decision = $this->scoreEngine->decide($results);

        $this->act($context, $decision);

        return $decision;
    }

    private function resolveTokenAttributes(RequestContext $context): RequestContext
    {
        $tokenId = (string) ($context->body[$this->tokenFieldName] ?? '');

        if ($tokenId === '') {
            return $context->withAttributes(['token_status' => 'missing']);
        }

        $result = $this->tokenIssuer->validateAndConsume($tokenId);

        if ($result->status !== 'valid' || $result->token === null) {
            return $context->withAttributes(['token_status' => $result->status]);
        }

        $resolvedFields = FieldMap::resolve($result->token->fieldMap, $context->body);
        $reportedInteractionCount = isset($resolvedFields['_interaction_count'])
            ? (int) $resolvedFields['_interaction_count']
            : null;

        return new RequestContext(
            ip: $context->ip,
            headers: $context->headers,
            body: [...$context->body, ...$resolvedFields],
            receivedAt: $context->receivedAt,
            attributes: [
                'token_status' => 'valid',
                'form_started_at' => $result->token->issuedAt,
                'reported_interaction_count' => $reportedInteractionCount,
                'resolved_fields' => $resolvedFields,
            ],
        );
    }

    private function act(RequestContext $context, Decision $decision): void
    {
        $this->storage->logSubmission($context->ip, $decision->result, $decision->score, [], $context->receivedAt);

        if ($decision->isAccept()) {
            $this->mailer->send($context->body, $context);

            return;
        }

        $evidence = array_map(
            static fn ($r) => ['name' => $r->name, 'score' => $r->score, 'evidence' => $r->evidence],
            $decision->evidence,
        );
        $this->storage->logAbuse($context->ip, $decision->result, $decision->score, $evidence, $context->receivedAt);

        $penalty = $decision->isReject()
            ? $this->config->reputationPenaltyOnReject
            : $this->config->reputationPenaltyOnChallenge;
        $this->reputationStore->penalize($context->ip, (float) $penalty, $context->receivedAt);
    }
}
