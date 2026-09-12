<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Paths below are root-relative ("/…") rather than relative to this file,
// because this example is meant to be served with the package root as the
// docroot (see examples/plain-php/bootstrap.php's neighbors and the repo's
// own root index.php shim). Adjust if you deploy this folder elsewhere.
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Contact Worx Demo</title>
<style>
    body { font-family: sans-serif; max-width: 480px; margin: 40px auto; }
    label { display: block; margin-top: 12px; }
    input, textarea { width: 100%; box-sizing: border-box; padding: 6px; font: inherit; }
    button { margin-top: 16px; padding: 8px 16px; }
    /* Off-screen rather than display:none — some bots skip fields hidden that way. */
    .cw-hp { position: absolute; left: -9999px; top: -9999px; }
</style>
</head>
<body>
<h1>Contact us</h1>
<form method="post" action="/examples/plain-php/submit.php" id="cw-form">
    <label>Name
        <input type="text" name="name" class="cw-field" required>
    </label>
    <label>Email
        <input type="email" name="email" class="cw-field" required>
    </label>
    <label>Message
        <textarea name="message" class="cw-field" rows="4" required></textarea>
    </label>
    <div class="cw-hp" aria-hidden="true">
        <label>Leave this field blank
            <input type="text" name="website" class="cw-field" tabindex="-1" autocomplete="off">
        </label>
    </div>
    <input type="hidden" name="_interaction_count" class="cw-field" value="0">
    <input type="hidden" name="_cw_token" value="">
    <button type="submit">Send</button>
</form>
<script src="/js/contact-warden.js" data-init-url="/examples/plain-php/init.php"></script>
</body>
</html>
