<?php

test('application layout loads inter font', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap', false);
});
