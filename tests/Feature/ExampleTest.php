<?php

test('guests are redirected to login from the application root', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
