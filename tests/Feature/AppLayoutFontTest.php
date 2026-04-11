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

test('application branding exposes the Money Cloud logo assets', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('/favicon.svg', false);

    expect(file_get_contents(public_path('favicon.svg')))
        ->toContain('<title>Money Cloud</title>')
        ->toContain('euro sign in the center');

    expect(file_get_contents(public_path('logo.svg')))
        ->toContain('<title>Money Cloud logo</title>')
        ->toContain('euro sign in the center');

    expect(file_get_contents(resource_path('js/components/AppLogo.vue')))
        ->toContain('Money Cloud')
        ->toContain('osebne finance');

    expect(file_get_contents(resource_path('js/components/AppLogoIcon.vue')))
        ->toContain('euro sign in the center');
});
