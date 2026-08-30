<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int $groupId */
/** @var string $csrfToken */
/** @var string|null $inviteUrl */
/** @var list<array{id: int|string, expires_at: string, used_at: string|null, used_by: int|string|null, created_at: string}> $invitations */
/** @var string|null $successMessage */
/** @var string|null $errorMessage */

ob_start();
?>
<section class="page-shell" aria-labelledby="invitations-title">
    <div class="page-heading">
        <p class="eyebrow">Manage</p>
        <div class="page-heading__row">
            <div>
                <h1 id="invitations-title">Invitations</h1>
                <p>Create 24-hour links that let one person join without approval.</p>
            </div>
            <a class="button button--secondary button--inline" href="/groups/<?php echo Html::escape((string) $groupId); ?>">Back to group</a>
        </div>
    </div>

    <?php if ($successMessage !== null): ?>
        <div class="form-alert form-alert--success" role="status">
            <?php echo Html::escape($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== null): ?>
        <div class="form-alert" role="alert">
            <?php echo Html::escape($errorMessage); ?>
        </div>
    <?php endif; ?>

    <div class="invitation-panel">
        <form method="post" action="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/invitations">
            <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">
            <button class="button button--primary button--inline" type="submit">Create invitation link</button>
        </form>

        <?php if ($inviteUrl !== null): ?>
            <div class="invite-link-box" role="status">
                <span>New invitation</span>
                <code><?php echo Html::escape($inviteUrl); ?></code>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($invitations === []): ?>
        <div class="empty-state">
            <h2>No invitations yet</h2>
            <p>Create a link when you want one person to join directly.</p>
        </div>
    <?php else: ?>
        <div class="invitation-list" role="list">
            <?php foreach ($invitations as $invitation): ?>
                <?php
                $used = $invitation['used_at'] !== null;
                $expired = !$used && strtotime((string) $invitation['expires_at']) <= time();
                $status = $used ? 'Used' : ($expired ? 'Expired' : 'Active');
                ?>
                <article class="invitation-row" role="listitem">
                    <div>
                        <h2><?php echo Html::escape($status); ?> invitation</h2>
                        <p>Created <?php echo Html::escape((string) $invitation['created_at']); ?></p>
                    </div>
                    <span class="membership-badge"><?php echo Html::escape($status); ?></span>
                    <p>Expires <?php echo Html::escape((string) $invitation['expires_at']); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
