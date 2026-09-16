<?php

declare(strict_types=1);

use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkTarget;
use App\Domains\Links\ValueObjects\LinkUrl;
use Tests\Support\Links\LinkFixtures;

it('pairs the platform with the url the caller chose for it', function () {
    $target = new LinkTarget(LinkPlatform::Instagram, LinkUrl::restore(LinkFixtures::INSTAGRAM_URL));

    expect($target->platform)->toBe(LinkPlatform::Instagram)
        ->and($target->url->value)->toBe(LinkFixtures::INSTAGRAM_URL);
});

it('is the pair a validated url was built from', function () {
    $target = new LinkTarget(
        LinkPlatform::Website,
        LinkUrl::forPlatform(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
    );

    expect($target->url->value)->toBe(LinkFixtures::WEBSITE_URL);
});

it('holds both halves for good, so a target cannot be repointed behind a caller back', function () {
    expect((new ReflectionClass(LinkTarget::class))->isReadOnly())->toBeTrue();
});

it('does not check the pair itself, because the url is what refuses a foreign host', function () {
    $target = new LinkTarget(LinkPlatform::Instagram, LinkUrl::restore('https://facebook.com/ada.salon'));

    expect($target->url->value)->toBe('https://facebook.com/ada.salon');
});
