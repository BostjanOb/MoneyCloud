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
