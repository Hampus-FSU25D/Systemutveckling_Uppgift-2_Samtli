<?php

declare(strict_types=1);

use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$templates = new TemplateRenderer(dirname(__DIR__, 2) . '/templates');

$guestHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => null,
]);
assertContains($guestHome, 'home-hero', 'guest home uses hero layout');
assertContains($guestHome, 'Find your community.', 'guest home uses Stitch welcome headline');
assertContains($guestHome, 'Photography', 'guest home includes visual group collage content');
assertContains($guestHome, 'Create account', 'guest home includes account CTA');
assertContains($guestHome, 'class="brand-logo"', 'header renders the supplied logo image');
assertContains($guestHome, '/assets/images/brand/samtli-wordmark.png', 'header uses the project-owned Samtli wordmark');
assertNotContains($guestHome, 'brand-mark', 'header does not render an icon logo mark');
assertNotContains($guestHome, '>About</a>', 'guest header does not render a dead-end about navigation item');
assertNotContains($guestHome, '<a href="/">Privacy</a>', 'footer does not render placeholder privacy links');
assertNotContains($guestHome, '<a href="/">Terms</a>', 'footer does not render placeholder terms links');
assertNotContains($guestHome, 'PHP environment is running', 'guest home does not render bootstrap placeholder copy');
assertNotContains($guestHome, 'bootstrap-panel', 'guest home does not use placeholder panel');

$authenticatedHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => 42,
]);
assertContains($authenticatedHome, 'home-feed', 'authenticated home uses feed layout');
assertContains($authenticatedHome, 'Welcome back', 'authenticated home uses personalized welcome surface');
assertContains($authenticatedHome, 'Your Groups', 'authenticated home includes groups overview');
assertContains($authenticatedHome, 'Latest Threads', 'authenticated home includes thread overview');
assertContains($authenticatedHome, 'href="/"', 'authenticated header exposes explicit home navigation');
assertContains($authenticatedHome, 'href="/groups"', 'authenticated header exposes explicit explore navigation');
assertContains($authenticatedHome, 'href="/account"', 'authenticated shell exposes account navigation');
assertNotContains($authenticatedHome, '>About</a>', 'authenticated header does not render public about navigation');
assertNotContains($authenticatedHome, '>Profile</a>', 'mobile navigation does not render a missing profile route');
assertContains($authenticatedHome, '<a href="/account"', 'mobile navigation links to real account route');
assertNotContains($authenticatedHome, 'public-actions" aria-label="Account">
                                    <a href="/groups">Discover</a>', 'authenticated account actions do not duplicate explore navigation');
assertNotContains($authenticatedHome, 'You are logged in.', 'authenticated home does not render login status placeholder');

echo "Home design verification passed.\n";

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function assertNotContains(string $haystack, string $needle, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}
