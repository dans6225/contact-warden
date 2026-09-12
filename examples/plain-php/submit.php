<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use ContactWarden\Http\RequestContext;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$context = RequestContext::fromGlobals($_SERVER, $_POST, $config->trustedProxies);
$decision = $engine->handle($context);

// ACCEPT and REJECT deliberately render the same page (silent-drop, the
// config's default rejectResponseMode) so a rejected bot gets no feedback
// to iterate against. CHALLENGE is the only visibly different outcome.
$message = $decision->isChallenge()
    ? "Thanks — we need to double-check your message before it's delivered."
    : 'Thanks — your message has been sent!';

?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Contact Worx Demo</title></head>
<body>
<p><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
<p><a href="index.php">Back to the form</a></p>
<?php if (($_ENV['CW_DEBUG'] ?? '') === '1'): ?>
    <hr>
    <pre><?= htmlspecialchars(sprintf(
        "decision=%s score=%d\nevidence=%s",
        $decision->result,
        $decision->score,
        json_encode($decision->evidence, JSON_PRETTY_PRINT),
    ), ENT_QUOTES) ?></pre>
<?php endif; ?>
</body>
</html>
