<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

/**
 * The contract a framework connector package (contact-warden-ci4,
 * contact-warden-laravel, ...) implements to render the admin UI and process
 * its actions. Rendering only: routing, authentication, and CSRF are the
 * host app's/connector's own controller layer, already handled by the time
 * these methods are called.
 *
 * Render methods return a plain string, not a framework Response — a
 * connector renders with its own framework's view engine internally and
 * hands back the resulting HTML, so this interface never depends on any
 * framework's HTTP types. Implementations take their AdminDataSource (and
 * whatever view engine they need) via their own constructor rather than a
 * parameter here, same as SignalInterface implementations do today.
 *
 * Settings/config editing is deliberately not part of this contract — this
 * package has no admin-settings machinery (see ContactWardenConfig's
 * docblock), so adjusting weights/thresholds stays entirely a host-app
 * concern, outside anything a connector standardizes.
 */
interface AdminConnectorInterface
{
    public function renderDashboard(): string;

    public function renderSubmissionsList(SubmissionQuery $query): string;

    public function renderSubmissionDetail(int $submissionId): string;

    public function renderAbuseLog(AbuseQuery $query): string;

    public function renderReputationList(ReputationQuery $query): string;

    /**
     * Generic action dispatch rather than one named method per action: a
     * connector can support its own action vocabulary beyond what core
     * anticipates. The conventions core itself enables, backed by
     * AdminMaintenance: `purge_tokens`, `purge_submissions` / `purge_abuse`
     * (both take `['days' => int]`), `forget_reputation` (takes
     * `['subject' => string]`), and `reset_reputation` (no input — clears
     * every subject). MaintenanceActions implements exactly this vocabulary —
     * a connector's handleAction() should normally just delegate to it, first
     * handling any extra actions of its own if it has them.
     *
     * @param array<string,mixed> $input
     */
    public function handleAction(string $action, array $input): ActionResult;
}
