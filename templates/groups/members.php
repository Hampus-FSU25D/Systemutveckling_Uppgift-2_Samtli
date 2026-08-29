<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int $groupId */
/** @var int $currentUserId */
/** @var string $csrfToken */
/** @var list<array{user_id: int|string, first_name: string, last_name: string, email: string, role: string, joined_at: string}> $members */
/** @var string|null $successMessage */
/** @var string|null $errorMessage */

ob_start();
?>
<section class="page-shell" aria-labelledby="members-title">
    <div class="page-heading">
        <p class="eyebrow">Manage</p>
        <div class="page-heading__row">
            <div>
                <h1 id="members-title">Members</h1>
                <p>Review group members and update their per-group role.</p>
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

    <div class="member-list" role="list">
        <?php foreach ($members as $member): ?>
            <?php $memberUserId = (int) $member['user_id']; ?>
            <article class="member-row" role="listitem">
                <div class="avatar" aria-hidden="true">
                    <?php echo Html::escape(strtoupper(substr((string) $member['first_name'], 0, 1) . substr((string) $member['last_name'], 0, 1))); ?>
                </div>
                <div class="member-row__body">
                    <h2><?php echo Html::escape((string) $member['first_name'] . ' ' . (string) $member['last_name']); ?></h2>
                    <p><?php echo Html::escape((string) $member['email']); ?></p>
                </div>
                <span class="membership-badge"><?php echo Html::escape($member['role'] === 'administrator' ? 'Administrator' : 'Member'); ?></span>
                <?php if ($memberUserId !== $currentUserId): ?>
                    <form class="member-row__action" action="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/members/<?php echo Html::escape((string) $memberUserId); ?>/role" method="post">
                        <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">
                        <label class="sr-only" for="role_<?php echo Html::escape((string) $memberUserId); ?>">Role</label>
                        <select class="form-input member-role-select" id="role_<?php echo Html::escape((string) $memberUserId); ?>" name="role">
                            <option value="member" <?php echo $member['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                            <option value="administrator" <?php echo $member['role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        <button class="button button--primary button--inline" type="submit">Update role</button>
                    </form>
                <?php else: ?>
                    <span class="member-row__self">You</span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
