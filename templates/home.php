<?php

declare(strict_types=1);

/** @var int|null $authenticatedUserId */

ob_start();
?>
<section class="bootstrap-panel" aria-labelledby="page-title">
    <p class="eyebrow">Samtli</p>
    <?php if (($authenticatedUserId ?? null) !== null): ?>
        <h1 id="page-title">You are logged in.</h1>
        <p>The authenticated session is active. Group features will be added in upcoming branches.</p>
        <p class="note"><a href="/groups/create">Create a group</a> to start shaping a community.</p>
    <?php else: ?>
        <h1 id="page-title">PHP environment is running</h1>
        <p>The project foundation is ready for server-rendered community features.</p>
        <p class="note"><a href="/register">Create an account</a> or <a href="/login">log in</a> to try the authentication flow.</p>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layouts/public.php';
