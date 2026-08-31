<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int|null $authenticatedUserId */
/** @var list<array{id: int|string, name: string, description: string|null, role?: string, joined_at?: string}>|null $homeGroups */
/** @var list<array{id: int|string, group_id: int|string, subject: string, created_at: string, updated_at: string, group_name: string, first_name: string, last_name: string, first_post_content: string|null, reply_count: int|string}>|null $homeDiscussions */
/** @var list<array{id: int|string, name: string, description: string|null, member_count: int|string}>|null $homeFeaturedGroups */

$homeGroups = $homeGroups ?? [];
$homeDiscussions = $homeDiscussions ?? [];
$homeFeaturedGroups = $homeFeaturedGroups ?? [];
$homeInitial = static function (string $name): string {
    $name = trim($name);

    return strtoupper(substr($name, 0, 1) ?: 'S');
};
$memberLabel = static function (int $count): string {
    return $count . ' ' . ($count === 1 ? 'member' : 'members');
};
$featuredMemberTotal = array_sum(array_map(
    static fn (array $group): int => (int) ($group['member_count'] ?? 0),
    $homeFeaturedGroups
));
$homeDate = static function (string $timestamp): string {
    $time = strtotime($timestamp);

    return $time === false ? $timestamp : date('M j, Y', $time);
};

ob_start();
?>
<?php if (($authenticatedUserId ?? null) !== null): ?>
    <section class="home-feed" aria-labelledby="page-title">
        <div class="home-feed__intro">
            <p class="eyebrow">Good morning</p>
            <h1 id="page-title">Welcome back.</h1>
            <p>Here is what is unfolding in your communities today.</p>
        </div>

        <section class="home-section" aria-labelledby="your-groups-title">
            <div class="home-section__heading">
                <h2 id="your-groups-title">Your Groups</h2>
                <a href="/groups">View all</a>
            </div>

            <?php if ($homeGroups === []): ?>
                <div class="home-empty-state">
                    <h3>No groups yet</h3>
                    <p>Explore communities or create the first space for your interests.</p>
                    <a href="/groups">Discover groups</a>
                </div>
            <?php else: ?>
                <div class="home-group-strip" role="list">
                    <?php foreach (array_slice($homeGroups, 0, 3) as $group): ?>
                        <a class="home-group-tile" role="listitem" href="/groups/<?php echo Html::escape((string) $group['id']); ?>">
                            <span class="home-group-tile__initial" aria-hidden="true"><?php echo Html::escape($homeInitial((string) $group['name'])); ?></span>
                            <span class="home-group-tile__content">
                                <strong><?php echo Html::escape((string) $group['name']); ?></strong>
                                <span><?php echo Html::escape(($group['role'] ?? '') === 'administrator' ? 'Administrator' : 'Member'); ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <a class="home-group-tile home-group-tile--discover" role="listitem" href="/groups">
                        <span class="home-group-tile__initial" aria-hidden="true">+</span>
                        <span class="home-group-tile__content">
                            <strong>Discover</strong>
                            <span>Find more groups</span>
                        </span>
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <section class="home-section" aria-labelledby="latest-threads-title">
            <div class="home-section__heading">
                <h2 id="latest-threads-title">Latest Threads</h2>
                <a href="/groups">Browse groups</a>
            </div>

            <?php if ($homeDiscussions === []): ?>
                <div class="home-empty-state">
                    <h3>No threads yet</h3>
                    <p>New discussions from your groups will appear here.</p>
                    <a href="<?php echo $homeGroups === [] ? '/groups/create' : '/groups/' . Html::escape((string) $homeGroups[0]['id']) . '/discussions/create'; ?>">
                        <?php echo $homeGroups === [] ? 'Create a group' : 'Start a discussion'; ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="home-thread-list">
                    <?php foreach ($homeDiscussions as $discussion): ?>
                        <a class="home-thread-card" href="/groups/<?php echo Html::escape((string) $discussion['group_id']); ?>/discussions/<?php echo Html::escape((string) $discussion['id']); ?>">
                            <div class="home-thread-card__meta">
                                <span><?php echo Html::escape((string) $discussion['group_name']); ?></span>
                                <span><?php echo Html::escape($homeDate((string) $discussion['updated_at'])); ?></span>
                            </div>
                            <h3><?php echo Html::escape((string) $discussion['subject']); ?></h3>
                            <p><?php echo Html::escape((string) ($discussion['first_post_content'] ?? '')); ?></p>
                            <div class="home-thread-card__footer">
                                <span><?php echo Html::escape((string) $discussion['first_name'] . ' ' . (string) $discussion['last_name']); ?></span>
                                <span><?php echo Html::escape((string) $discussion['reply_count']); ?> replies</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>
<?php else: ?>
    <section class="home-hero" aria-labelledby="page-title">
        <div class="home-hero__copy">
            <div>
                <p class="eyebrow">01</p>
                <h1 id="page-title">Find your community.</h1>
                <p>Join groups centered around shared interests and thoughtful discussion. A quiet room for public discourse.</p>
            </div>

            <div class="home-hero__actions">
                <a class="button button--primary button--inline" href="/register">Create account</a>
                <a class="button button--secondary button--inline" href="/groups">Explore Samtli</a>
            </div>

            <div class="home-hero__proof" aria-label="Community activity">
                <?php if ($homeFeaturedGroups !== []): ?>
                    <div class="home-avatar-stack" aria-hidden="true">
                        <?php foreach (array_slice($homeFeaturedGroups, 0, 3) as $group): ?>
                            <span><?php echo Html::escape($homeInitial((string) $group['name'])); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p><?php echo $featuredMemberTotal > 0 ? Html::escape($memberLabel($featuredMemberTotal) . ' already connecting around practical interests.') : 'Start the first community around a practical interest.'; ?></p>
            </div>
        </div>

        <div class="home-featured-panel" aria-label="Featured communities">
            <div class="home-featured-panel__heading">
                <span>Top communities</span>
                <a href="/groups">Explore all</a>
            </div>
            <?php if ($homeFeaturedGroups === []): ?>
                <div class="home-featured-empty">
                    <span aria-hidden="true">S</span>
                    <p>No communities yet</p>
                    <a href="/register">Create the first account</a>
                </div>
            <?php else: ?>
                <div class="home-featured-list" role="list">
                <?php foreach (array_slice($homeFeaturedGroups, 0, 3) as $group): ?>
                    <?php $memberCount = (int) ($group['member_count'] ?? 0); ?>
                    <a
                        class="home-featured-group"
                        href="/groups"
                        role="listitem"
                    >
                        <span class="home-featured-group__initial" aria-hidden="true"><?php echo Html::escape($homeInitial((string) $group['name'])); ?></span>
                        <div>
                            <strong><?php echo Html::escape((string) $group['name']); ?></strong>
                            <p><?php echo Html::escape((string) (($group['description'] ?? '') !== '' ? $group['description'] : 'No description yet.')); ?></p>
                        </div>
                        <span class="home-featured-group__meta"><?php echo Html::escape($memberLabel($memberCount)); ?></span>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();
$mainClass = ($authenticatedUserId ?? null) !== null ? 'home-main home-main--feed' : 'home-main';
$showFooter = ($authenticatedUserId ?? null) === null;
require __DIR__ . '/layouts/public.php';
