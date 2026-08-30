<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $token */
/** @var array{id: int|string, group_id: int|string, name: string, description: string|null, expires_at: string} $group */
/** @var string $csrfToken */
/** @var int|null $authenticatedUserId */
/** @var string|null $errorMessage */

ob_start();
?>
<section class="page-shell page-shell--narrow" aria-labelledby="invitation-title">
    <div class="page-heading">
        <p class="eyebrow">Invitation</p>
        <h1 id="invitation-title">Join <?php echo Html::escape((string) $group['name']); ?></h1>
        <p><?php echo Html::escape((string) ($group['description'] ?? 'Accept this invitation to join the group.')); ?></p>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="form-alert" role="alert">
            <?php echo Html::escape($errorMessage); ?>
        </div>
    <?php endif; ?>

    <div class="invitation-card">
        <span class="membership-badge">Single use</span>
        <p>This link expires at <?php echo Html::escape((string) $group['expires_at']); ?> and can only be used once.</p>

        <?php if ($authenticatedUserId === null): ?>
            <a class="button button--primary" href="/login">Log in to accept</a>
        <?php else: ?>
            <form method="post" action="/invitations/<?php echo Html::escape($token); ?>/accept">
                <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">
                <button class="button button--primary" type="submit">Accept invitation</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
