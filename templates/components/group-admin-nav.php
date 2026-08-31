<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int|string $groupId */
/** @var string $activeAdminNav */

$adminItems = [
    'overview' => [
        'label' => 'Overview',
        'href' => "/groups/{$groupId}",
    ],
    'join-requests' => [
        'label' => 'Join requests',
        'href' => "/groups/{$groupId}/admin/join-requests",
    ],
    'members' => [
        'label' => 'Members',
        'href' => "/groups/{$groupId}/admin/members",
    ],
    'invitations' => [
        'label' => 'Invitations',
        'href' => "/groups/{$groupId}/admin/invitations",
    ],
];
?>
<nav class="group-admin-nav" aria-label="Group administration">
    <?php foreach ($adminItems as $key => $item): ?>
        <?php $isActive = $activeAdminNav === $key; ?>
        <a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo Html::escape($item['href']); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
            <?php echo Html::escape($item['label']); ?>
        </a>
    <?php endforeach; ?>
</nav>
