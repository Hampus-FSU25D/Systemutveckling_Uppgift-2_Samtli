<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int $groupId */
/** @var array{id: int|string, name: string, description: string|null}|null $group */
/** @var list<array{id: int|string, subject: string, created_at: string, first_name: string, last_name: string, reply_count: int|string}>|null $discussions */
/** @var bool|null $isAdministrator */

ob_start();
?>
<section class="page-shell" aria-labelledby="page-title">
    <div class="group-hero">
        <div class="group-hero__mark" aria-hidden="true">
            <?php echo Html::escape(strtoupper(substr((string) ($group['name'] ?? 'S'), 0, 1)) ?: 'S'); ?>
        </div>
        <div class="group-hero__content">
            <p class="group-kicker"><?php echo ($isAdministrator ?? false) === true ? 'Administrator' : 'Member group'; ?></p>
            <h1 id="page-title"><?php echo Html::escape((string) ($group['name'] ?? 'Your group is ready.')); ?></h1>
            <p><?php echo Html::escape((string) ($group['description'] ?? 'You are this group administrator.')); ?></p>
        </div>
        <?php if (($discussions ?? []) !== []): ?>
            <div class="group-actions">
                <a class="button button--primary button--inline button--icon button--start-discussion" href="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/create">Start discussion</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (($isAdministrator ?? true) === true): ?>
        <div class="group-admin-nav" aria-label="Group administration">
            <a href="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/join-requests">Review join requests</a>
            <a href="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/members">Manage members</a>
            <a href="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/invitations">Invitations</a>
        </div>
    <?php endif; ?>

    <?php if (($discussions ?? []) === []): ?>
        <div class="empty-state empty-state--centered">
            <div class="empty-state__icon" aria-hidden="true">#</div>
            <h2>No discussions yet</h2>
            <p>Start the first conversation in this group.</p>
            <a class="button button--primary button--inline button--icon button--start-discussion" href="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/create">Start discussion</a>
        </div>
    <?php else: ?>
        <div class="section-heading section-heading--compact">
            <h2>Recent discussions</h2>
        </div>
        <div class="discussion-list" role="list">
            <?php foreach (($discussions ?? []) as $discussion): ?>
                <?php $replyCount = (int) $discussion['reply_count']; ?>
                <a class="discussion-row" role="listitem" href="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/<?php echo Html::escape((string) $discussion['id']); ?>">
                    <span class="discussion-row__avatar" aria-hidden="true">
                        <?php echo Html::escape(strtoupper(substr((string) $discussion['first_name'], 0, 1) . substr((string) $discussion['last_name'], 0, 1))); ?>
                    </span>
                    <div class="discussion-row__body">
                        <h2><?php echo Html::escape((string) $discussion['subject']); ?></h2>
                        <p><?php echo Html::escape((string) $discussion['first_name'] . ' ' . (string) $discussion['last_name']); ?> <span aria-hidden="true">&middot;</span> <?php echo Html::escape((string) $replyCount . ' ' . ($replyCount === 1 ? 'reply' : 'replies')); ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
