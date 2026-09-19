<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

/**
 * The standard maintenance-action vocabulary behind AdminConnectorInterface::
 * handleAction(), implemented once so every connector maps an action name and
 * its input onto AdminMaintenance the same way instead of each carrying its own
 * copy. A connector's handleAction() is normally just a call to handle().
 *
 * Actions: `purge_tokens`; `purge_submissions` / `purge_abuse` (both take
 * `['days' => int]`); `forget_reputation` (takes `['subject' => string]`);
 * `reset_reputation`. Anything else is a failed ActionResult, so a connector
 * that supports extra actions can handle its own first and delegate the rest.
 */
final class MaintenanceActions
{
    public function __construct(private readonly AdminMaintenance $maintenance)
    {
    }

    /** @param array<string,mixed> $input */
    public function handle(string $action, array $input): ActionResult
    {
        return match ($action) {
            'purge_tokens' => new ActionResult(
                true,
                $this->maintenance->purgeExpiredTokens(new \DateTimeImmutable()) . ' expired or used tokens removed.',
            ),
            'purge_submissions' => $this->purgeOlderThan(
                fn (\DateTimeImmutable $cutoff): int => $this->maintenance->purgeSubmissionsOlderThan($cutoff),
                $input,
                'submission log entries',
            ),
            'purge_abuse' => $this->purgeOlderThan(
                fn (\DateTimeImmutable $cutoff): int => $this->maintenance->purgeAbuseEventsOlderThan($cutoff),
                $input,
                'abuse log entries',
            ),
            'forget_reputation' => $this->forgetReputation($input),
            'reset_reputation' => $this->resetReputation(),
            default => new ActionResult(false, "Unknown action \"{$action}\"."),
        };
    }

    /**
     * @param callable(\DateTimeImmutable): int $purge
     * @param array<string,mixed> $input
     */
    private function purgeOlderThan(callable $purge, array $input, string $label): ActionResult
    {
        $days = max(0, (int) ($input['days'] ?? 0));
        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days");
        $removed = $purge($cutoff);

        return new ActionResult(true, "{$removed} {$label} older than {$days} days removed.");
    }

    /** @param array<string,mixed> $input */
    private function forgetReputation(array $input): ActionResult
    {
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            return new ActionResult(false, 'No reputation subject given.');
        }

        $this->maintenance->forgetReputation($subject);

        return new ActionResult(true, "Reputation for {$subject} cleared.");
    }

    private function resetReputation(): ActionResult
    {
        $this->maintenance->resetAllReputation();

        return new ActionResult(true, 'All reputation scores cleared.');
    }
}
