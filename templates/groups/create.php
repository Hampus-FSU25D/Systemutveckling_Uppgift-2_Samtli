<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string $csrfToken */
/** @var array<string, list<string>> $errors */
/** @var array<string, string> $old */

$fieldError = static fn (string $field): ?string => $errors[$field][0] ?? null;
$fieldClass = static fn (string $field): string => isset($errors[$field]) ? 'form-input is-invalid' : 'form-input';

ob_start();
?>
<section class="form-card group-create-card" aria-labelledby="group-create-title">
    <div class="form-card__symbol" aria-hidden="true">ooo</div>

    <div class="form-card__heading">
        <h1 id="group-create-title">Create group</h1>
        <p>Gather people around a shared interest, project, or local community.</p>
    </div>

    <?php if (isset($errors['_form'])): ?>
        <div class="form-alert" role="alert">
            <?php echo Html::escape($errors['_form'][0]); ?>
        </div>
    <?php endif; ?>

    <form class="stack-form" action="/groups" method="post" novalidate>
        <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">

        <div class="form-field">
            <label for="name">Group name <span class="required-mark">*</span></label>
            <input class="<?php echo $fieldClass('name'); ?>" id="name" name="name" type="text" value="<?php echo Html::escape($old['name'] ?? ''); ?>" placeholder="e.g. Nordic Architecture Enthusiasts" required maxlength="120" <?php echo $fieldError('name') ? 'aria-invalid="true" aria-describedby="name_error"' : ''; ?>>
            <?php if ($fieldError('name')): ?>
                <p class="field-error" id="name_error"><?php echo Html::escape((string) $fieldError('name')); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="description">Description <span class="required-mark">*</span></label>
            <textarea class="<?php echo $fieldClass('description'); ?> form-textarea" id="description" name="description" placeholder="What is this group about? Who is it for?" required rows="5" <?php echo $fieldError('description') ? 'aria-invalid="true" aria-describedby="description_error"' : ''; ?>><?php echo Html::escape($old['description'] ?? ''); ?></textarea>
            <?php if ($fieldError('description')): ?>
                <p class="field-error" id="description_error"><?php echo Html::escape((string) $fieldError('description')); ?></p>
            <?php endif; ?>
        </div>

        <div class="info-note">
            <span aria-hidden="true">i</span>
            <p>The person creating the group automatically becomes its administrator.</p>
        </div>

        <div class="form-actions">
            <a class="button button--secondary" href="/">Cancel</a>
            <button class="button button--primary button--inline" type="submit">Create group -></button>
        </div>
    </form>
</section>
<?php
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/layouts/public.php';
