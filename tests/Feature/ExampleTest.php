<?php

declare(strict_types=1);

test('the root application redirects guests to the office login', function () {
    $this->get('/')->assertRedirectToRoute('filament.admin.auth.login');
});
