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
    <div class="page-heading">
        <p class="eyebrow">Group</p>
        <div class="page-heading__row">
            <div>
                <h1 id="page-title"><?php echo Html::escape((string) ($group['name'] ?? 'Your group is ready.')); ?></h1>
                <p><?php echo Html::escape((string) ($group['description'] ?? 'You are this group administrator.')); ?></p>
            </div>
            <a class="button button--primary button--inline" href="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/create">Start discussion</a>
        </div>
    </div>

    <?php if (($isAdministrator ?? true) === true): ?>
        <div class="admin-link-row">
            <a href="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/join-requests">Review join requests</a>
        </div>
    <?php endif; ?>

    <?php if (($discussions ?? []) === []): ?>
        <div class="empty-state">
            <h2>No discussions yet</h2>
            <p>Start the first thread for this group.</p>
        </div>
    <?php else: ?>
        <div class="discussion-list" role="list">
            <?php foreach (($discussions ?? []) as $discussion): ?>
                <a class="discussion-row" role="listitem" href="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/<?php echo Html::escape((string) $discussion['id']); ?>">
                    <div>
                        <h2><?php echo Html::escape((string) $discussion['subject']); ?></h2>
                        <p><?php echo Html::escape((string) $discussion['first_name'] . ' ' . (string) $discussion['last_name']); ?></p>
                    </div>
                    <span><?php echo Html::escape((string) $discussion['reply_count']); ?> replies</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
