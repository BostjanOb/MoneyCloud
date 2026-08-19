<?php

test('application layout loads inter font', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap', false);
});

test('frontend components no longer use font mono utility classes', function () {
    expect(file_get_contents(resource_path('js/components/ui/table/TableCell.vue')))
        ->not->toContain('font-mono');

    expect(file_get_contents(resource_path('js/components/ui/table/TableHead.vue')))
        ->not->toContain('font-mono');

    expect(file_get_contents(resource_path('js/components/TwoFactorRecoveryCodes.vue')))
        ->not->toContain('font-mono');
});

test('application branding exposes the MoneyCloud logo assets', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('/favicon.svg', false);

    expect(file_get_contents(public_path('favicon.svg')))
        ->toContain('<title>MoneyCloud</title>');

    expect(file_get_contents(public_path('logo.svg')))
        ->toContain('<title>MoneyCloud</title>');

    expect(json_decode(file_get_contents(public_path('site.webmanifest')), true))
        ->toMatchArray([
            'name' => config('app.name'),
            'short_name' => config('app.name'),
        ]);

    expect(file_get_contents(resource_path('js/components/AppLogo.vue')))
        ->toContain('/logo-light.svg')
        ->toContain('/logo-dark.svg')
        ->toContain('/mark-light.svg')
        ->toContain('/mark-dark.svg');
});

test('the sidebar logo has a light and a dark file for both sidebar states', function () {
    $variants = ['logo-light.svg', 'logo-dark.svg', 'mark-light.svg', 'mark-dark.svg'];

    foreach ($variants as $variant) {
        expect(file_exists(public_path($variant)))->toBeTrue("missing public/{$variant}");
    }

    $logo = file_get_contents(resource_path('js/components/AppLogo.vue'));

    // The theme swap rides the `dark` class rather than JavaScript, and the
    // sidebar state is decided on the wrapper so no element has two utilities
    // fighting over `display`.
    expect(substr_count($logo, 'dark:hidden'))->toBe(2);
    expect(substr_count($logo, 'dark:block'))->toBe(2);
    expect($logo)
        ->toContain('group-data-[collapsible=icon]:hidden')
        ->toContain('group-data-[collapsible=icon]:flex');
});

test('the mark ships as outlines, never as live type', function () {
    $sources = [
        public_path('favicon.svg'),
        public_path('mark-light.svg'),
        public_path('mark-dark.svg'),
        resource_path('js/components/AppLogoIcon.vue'),
    ];

    foreach ($sources as $source) {
        expect(file_get_contents($source))
            ->toContain('<path')
            ->not->toContain('<text')
            ->not->toMatch('/font-family|@font-face|fonts\.googleapis/');
    }
});

test('the logo lockups set their strapline in a system stack, not a webfont', function () {
    $lockups = ['logo.svg', 'logo-light.svg', 'logo-dark.svg'];

    foreach ($lockups as $lockup) {
        $contents = file_get_contents(public_path($lockup));

        // The medallion and the wordmark are outlines; only the sans-serif
        // strapline stays a live text node, so it must not pull in a webfont.
        expect(substr_count($contents, '<text'))->toBe(1, "in {$lockup}");

        expect($contents)
            ->toContain('system-ui')
            ->not->toMatch('/@font-face|fonts\.googleapis|Instrument\+?%?20?Serif/');
    }

    // The sidebar lockups are rendered through <img>, so their intrinsic width
    // must not depend on whichever font the SVG resolves on its own.
    foreach (['logo-light.svg', 'logo-dark.svg'] as $lockup) {
        expect(file_get_contents(public_path($lockup)))
            ->toContain('textLength=')
            ->toContain('lengthAdjust="spacing"');
    }
});

test('the medallion inverts on dark surfaces', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('--brand-mark: var(--brand-ink);')
        ->toContain('--brand-mark: var(--brand-cream);');

    expect(file_get_contents(resource_path('js/components/AppLogoIcon.vue')))
        ->toContain('fill-brand-mark')
        ->toContain('inverted');
});
