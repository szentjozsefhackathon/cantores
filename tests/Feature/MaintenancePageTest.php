<?php

use Illuminate\Support\Facades\Artisan;

test('maintenance mode shows the branded 503 page', function () {
    Artisan::call('down');

    try {
        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Cantores.hu', false);
    } finally {
        Artisan::call('up');
    }
});
