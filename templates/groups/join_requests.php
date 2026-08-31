<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var int $groupId */
/** @var string $csrfToken */
/** @var list<array{id: int|string, user_id: int|string, first_name: string, last_name: string, email: string, created_at: string}> $requests */
/** @var string|null $successMessage */
/** @var string|null $errorMessage */

ob_start();
?>
<section class="page-shell" aria-labelledby="requests-title">
    <div class="page-heading">
        <p class="eyebrow">Manage</p>
        <div class="page-heading__row">
            <div>
                <h1 id="requests-title">Pending requests</h1>
                <p>Review membership requests for this group. Approved users join as members.</p>
            </div>
        </div>
    </div>

    <?php
    $activeAdminNav = 'join-requests';
    require dirname(__DIR__) . '/components/group-admin-nav.php';
    ?>

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

    <?php if ($requests === []): ?>
        <div class="empty-state">
            <h2>No pending requests</h2>
            <p>New membership requests will appear here for administrators to review.</p>
        </div>
    <?php else: ?>
        <div class="request-list" role="list">
            <?php foreach ($requests as $request): ?>
                <article class="request-row" role="listitem">
                    <div class="avatar" aria-hidden="true">
                        <?php echo Html::escape(strtoupper(substr((string) $request['first_name'], 0, 1) . substr((string) $request['last_name'], 0, 1))); ?>
                    </div>
                    <div class="request-row__body">
                        <h2><?php echo Html::escape((string) $request['first_name'] . ' ' . (string) $request['last_name']); ?></h2>
                        <p><?php echo Html::escape((string) $request['email']); ?></p>
                    </div>
                    <form class="request-row__action" action="/groups/<?php echo Html::escape((string) $groupId); ?>/admin/join-requests/<?php echo Html::escape((string) $request['id']); ?>/approve" method="post">
                        <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">
                        <button class="button button--primary button--inline" type="submit">Approve</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
