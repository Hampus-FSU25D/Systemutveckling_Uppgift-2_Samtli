<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var int $groupId */
/** @var string $csrfToken */
/** @var array<string, list<string>> $errors */
/** @var array<string, string> $old */

$fieldError = static fn (string $field): ?string => $errors[$field][0] ?? null;
$fieldClass = static fn (string $field): string => isset($errors[$field]) ? 'form-input is-invalid' : 'form-input';

ob_start();
?>
<section class="form-card group-create-card" aria-labelledby="discussion-create-title">
    <div class="form-card__heading">
        <h1 id="discussion-create-title">Start discussion</h1>
        <p>Open a focused thread for this group with a clear subject and first post.</p>
    </div>

    <?php if (isset($errors['_form'])): ?>
        <div class="form-alert" role="alert">
            <?php echo Html::escape($errors['_form'][0]); ?>
        </div>
    <?php endif; ?>

    <form class="stack-form" action="/groups/<?php echo Html::escape((string) $groupId); ?>/discussions/create" method="post" novalidate>
        <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">

        <div class="form-field">
            <label for="subject">Subject <span class="required-mark">*</span></label>
            <input class="<?php echo $fieldClass('subject'); ?>" id="subject" name="subject" type="text" value="<?php echo Html::escape($old['subject'] ?? ''); ?>" placeholder="What should the group discuss?" required maxlength="180" <?php echo $fieldError('subject') ? 'aria-invalid="true" aria-describedby="subject_error"' : ''; ?>>
            <?php if ($fieldError('subject')): ?>
                <p class="field-error" id="subject_error"><?php echo Html::escape((string) $fieldError('subject')); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="content">First post <span class="required-mark">*</span></label>
            <textarea class="<?php echo $fieldClass('content'); ?> form-textarea" id="content" name="content" placeholder="Write the first message in the discussion." required rows="7" <?php echo $fieldError('content') ? 'aria-invalid="true" aria-describedby="content_error"' : ''; ?>><?php echo Html::escape($old['content'] ?? ''); ?></textarea>
            <?php if ($fieldError('content')): ?>
                <p class="field-error" id="content_error"><?php echo Html::escape((string) $fieldError('content')); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <a class="button button--secondary" href="/groups/<?php echo Html::escape((string) $groupId); ?>">Cancel</a>
            <button class="button button--primary button--inline" type="submit">Start discussion</button>
        </div>
    </form>
</section>
<?php
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/layouts/public.php';
