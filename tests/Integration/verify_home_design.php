<?php

declare(strict_types=1);

use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$templates = new TemplateRenderer(dirname(__DIR__, 2) . '/templates');

$guestHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => null,
    'homeFeaturedGroups' => [
        ['id' => 1, 'name' => 'Real Photo Group', 'description' => 'Actual stored group.', 'member_count' => 2],
        ['id' => 2, 'name' => 'Book Club', 'description' => 'Actual stored group.', 'member_count' => 1],
    ],
]);
assertContains($guestHome, 'home-hero', 'guest home uses hero layout');
assertContains($guestHome, 'Find your community.', 'guest home uses Stitch welcome headline');
assertContains($guestHome, 'Real Photo Group', 'guest home renders supplied featured community data');
assertContains($guestHome, 'Book Club', 'guest home renders multiple supplied featured communities');
assertContains($guestHome, '3 members already connecting', 'guest home renders real featured member total');
assertContains($guestHome, 'home-featured-panel', 'guest home uses a content-driven featured groups panel');
assertContains($guestHome, 'Create account', 'guest home includes account CTA');
assertContains($guestHome, 'class="brand-logo"', 'header renders the supplied logo image');
assertContains($guestHome, '/assets/images/brand/samtli-wordmark.png', 'header uses the project-owned Samtli wordmark');
assertContains($guestHome, 'width="100" height="34"', 'header logo has fixed intrinsic dimensions');
assertContains($guestHome, 'height:34px;max-width:178px', 'header logo has inline defensive sizing');
assertContains($guestHome, '/assets/css/base.css?v=', 'stylesheet URL is cache-busted for deployment');
assertContains($guestHome, 'href="/favicon-32x32.png"', 'head links the 32px favicon');
assertContains($guestHome, 'href="/site.webmanifest"', 'head links the web app manifest');
assertNotContains($guestHome, 'brand-mark', 'header does not render an icon logo mark');
assertNotContains($guestHome, '>About</a>', 'guest header does not render a dead-end about navigation item');
assertNotContains($guestHome, '<a href="/">Privacy</a>', 'footer does not render placeholder privacy links');
assertNotContains($guestHome, '<a href="/">Terms</a>', 'footer does not render placeholder terms links');
assertNotContains($guestHome, '4.2k members', 'guest home does not render fake member counts');
assertNotContains($guestHome, '>Web Dev<', 'guest home does not render fake featured communities');
assertNotContains($guestHome, '>Golf<', 'guest home does not render fake featured communities');
assertNotContains($guestHome, 'home-collage-card', 'guest home does not use image-collage group cards without group image data');
assertNotContains($guestHome, 'images.unsplash.com', 'guest home does not render placeholder remote group imagery');
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
assertContains($authenticatedHome, 'class="account-menu__trigger"', 'authenticated account menu uses an avatar trigger');
assertContains($authenticatedHome, 'aria-label="Open account menu"', 'authenticated account menu trigger is accessible');
assertContains($authenticatedHome, '<a href="/groups">Explore groups</a>', 'account menu exposes real explore route');
assertNotContains($authenticatedHome, 'account-menu__name', 'authenticated header does not expose profile text beside the avatar');
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
