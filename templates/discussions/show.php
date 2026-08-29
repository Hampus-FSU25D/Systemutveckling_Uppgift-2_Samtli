<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int $groupId */
/** @var array{id: int|string, group_id: int|string, subject: string, first_name: string, last_name: string, posts: list<array{id: int|string, content: string, first_name: string, last_name: string, created_at: string}>} $discussion */
/** @var string $replyCsrfToken */
/** @var array<string, list<string>> $replyErrors */
/** @var array<string, string> $replyOld */

$replyFieldError = $replyErrors['content'][0] ?? null;

ob_start();
?>
<section class="page-shell" aria-labelledby="discussion-title">
    <div class="page-heading">
        <p class="eyebrow">Discussion</p>
        <div class="page-heading__row">
            <div>
                <h1 id="discussion-title"><?php echo Html::escape((string) $discussion['subject']); ?></h1>
                <p>Started by <?php echo Html::escape((string) $discussion['first_name'] . ' ' . (string) $discussion['last_name']); ?></p>
            </div>
            <a class="button button--secondary button--inline" href="/groups/<?php echo Html::escape((string) $groupId); ?>">Back to group</a>
        </div>
    </div>

    <div class="post-list" role="list">
        <?php foreach ($discussion['posts'] as $post): ?>
            <article class="post-card" role="listitem">
                <div class="avatar" aria-hidden="true">
                    <?php echo Html::escape(strtoupper(substr((string) $post['first_name'], 0, 1) . substr((string) $post['last_name'], 0, 1))); ?>
                </div>
                <div class="post-card__body">
                    <h2><?php echo Html::escape((string) $post['first_name'] . ' ' . (string) $post['last_name']); ?></h2>
                    <p><?php echo nl2br(Html::escape((string) $post['content'])); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <section class="reply-composer" aria-labelledby="reply-title">
        <h2 id="reply-title">Reply to discussion</h2>

        <?php if (isset($replyErrors['_form'])): ?>
            <div class="form-alert" role="alert">
                <?php echo Html::escape($replyErrors['_form'][0]); ?>
            </div>
        <?php endif; ?>

        <form class="stack-form" action="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/<?php echo Html::escape((string) $discussion['id']); ?>/replies" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo Html::escape($replyCsrfToken); ?>">

            <div class="form-field">
                <label for="content">Reply <span class="required-mark">*</span></label>
                <textarea class="<?php echo $replyFieldError ? 'form-input form-textarea is-invalid' : 'form-input form-textarea'; ?>" id="content" name="content" rows="5" required <?php echo $replyFieldError ? 'aria-invalid="true" aria-describedby="content_error"' : ''; ?>><?php echo Html::escape($replyOld['content'] ?? ''); ?></textarea>
                <?php if ($replyFieldError): ?>
                    <p class="field-error" id="content_error"><?php echo Html::escape((string) $replyFieldError); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="button button--primary button--inline" type="submit">Post reply</button>
            </div>
        </form>
    </section>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
