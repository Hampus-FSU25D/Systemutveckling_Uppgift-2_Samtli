<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string $csrfToken */
/** @var list<array{id: int|string, name: string, description: string|null, created_at: string, join_request_status: string|null, member_count?: int|string}> $groups */
/** @var string|null $successMessage */
/** @var string|null $errorMessage */

ob_start();
?>
<section class="page-shell" aria-labelledby="discover-title">
    <div class="discover-search" role="search">
        <span aria-hidden="true">search</span>
        <input type="search" placeholder="Search groups" aria-label="Search groups" data-group-search>
    </div>

    <div class="page-heading">
        <p class="eyebrow">Discover</p>
        <div class="page-heading__row">
            <div>
                <h1 id="discover-title">Discover groups</h1>
                <p>Find communities that match your interests and request access from the group administrators.</p>
            </div>
            <a class="button button--secondary button--inline" href="/groups/create">Create group</a>
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

    <?php if ($groups === []): ?>
        <div class="empty-state">
            <h2>No groups to discover yet</h2>
            <p>Groups you already belong to are hidden from this list.</p>
        </div>
    <?php else: ?>
        <div class="group-grid" role="list">
            <?php foreach ($groups as $index => $group): ?>
                <?php $status = $group['join_request_status'] ?? null; ?>
                <?php $memberCount = (int) ($group['member_count'] ?? 0); ?>
                <article
                    class="group-card group-card--discover"
                    role="listitem"
                    data-group-card
                    data-search-text="<?php echo Html::escape(strtolower((string) $group['name'] . ' ' . (string) ($group['description'] ?? ''))); ?>"
                >
                    <div class="group-card__icon" aria-hidden="true"><?php echo Html::escape(strtoupper(substr((string) $group['name'], 0, 1)) ?: 'S'); ?></div>
                    <div class="group-card__body">
                        <div class="group-card__title-row">
                            <h2><?php echo Html::escape((string) $group['name']); ?></h2>
                            <?php if ($status === 'pending'): ?>
                                <span class="membership-badge membership-badge--pending">Request pending</span>
                            <?php endif; ?>
                        </div>
                        <p class="group-card__meta"><?php echo Html::escape((string) $memberCount . ' ' . ($memberCount === 1 ? 'member' : 'members')); ?></p>
                        <p><?php echo Html::escape((string) (($group['description'] ?? '') !== '' ? $group['description'] : 'No description yet.')); ?></p>
                    </div>
                    <div class="group-card__actions">
                        <?php if ($status === 'pending'): ?>
                            <button class="button button--secondary button--inline" type="button" disabled>Request pending</button>
                        <?php else: ?>
                            <form action="/groups/<?php echo Html::escape((string) $group['id']); ?>/join-requests" method="post">
                                <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">
                                <button class="button button--primary button--inline" type="submit">Request to join</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="empty-state group-filter-empty" data-group-filter-empty hidden>
            <h2>No matching groups</h2>
            <p>Try a different search term.</p>
        </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector('[data-group-search]');
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-group-card]'));
    var empty = document.querySelector('[data-group-filter-empty]');

    if (input === null || cards.length === 0) {
        return;
    }

    input.addEventListener('input', function () {
        var query = input.value.trim().toLowerCase();
        var visibleCount = 0;

        cards.forEach(function (card) {
            var matches = query === '' || (card.dataset.searchText || '').indexOf(query) !== -1;
            card.hidden = !matches;

            if (matches) {
                visibleCount += 1;
            }
        });

        if (empty !== null) {
            empty.hidden = visibleCount !== 0;
        }
    });
});
</script>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
