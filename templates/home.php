<?php

declare(strict_types=1);

/** @var int|null $authenticatedUserId */

ob_start();
?>
<section class="bootstrap-panel" aria-labelledby="page-title">
    <p class="eyebrow">Samtli</p>
    <?php if (($authenticatedUserId ?? null) !== null): ?>
        <h1 id="page-title">You are logged in.</h1>
        <p>Create groups, request membership, join discussions and manage invitations from your Samtli account.</p>
        <p class="note"><a href="/groups">Discover groups</a> or <a href="/groups/create">create a group</a> to start shaping a community.</p>
    <?php else: ?>
        <h1 id="page-title">Find your next discussion group</h1>
        <p>Samtli is a server-rendered community platform for interest-based groups, memberships and discussions.</p>
        <p class="note"><a href="/register">Create an account</a> or <a href="/login">log in</a> to start participating.</p>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layouts/public.php';
