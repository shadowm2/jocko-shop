<?php

use Modules\Inventory\Models\Warehouse;
use Modules\User\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the sidebar opens every group above the current page', function (string $route) {
    $this->actingAs(User::factory()->create());

    // With one warehouse there is nothing to transfer between and no warehouses menu; this is about several.
    Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);

    $html = $this->get(route($route))->getContent();

    // The outer group plus the page's own group (Flux renders that one twice).
    expect(preg_match_all('/<ui-disclosure[^>]*\sopen[\s=>]/', $html))->toBe(3);
})->with([
    'warehouses.index',
    'stock-movements.index',
    'stock-movements.create',
    'stock-movements.transfer',
    'payments.index',
    'payments.create',
]);

test('the sidebar marks only the current page', function (string $route) {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route($route))->getContent();
    preg_match_all('/<a href="([^"]+)" data-current="data-current"/', $html, $current);

    expect(array_unique($current[1]))->toBe([route($route)]);
})->with(['dashboard', 'items.index', 'warehouses.items.low-stock', 'payments.create', 'system-settings.edit']);

// Blade keeps the first of two identical attributes, so a leftover one is dead code.
test('no sidebar group declares its heading twice', function () {
    $view = file_get_contents(base_path('Modules/Dashboard/resources/views/components/layouts/sidebar.blade.php'));
    preg_match_all('/<flux:sidebar\.group\b.*?\n\s*>/s', $view, $groups);

    expect($groups[0])->not->toBeEmpty();

    foreach ($groups[0] as $tag) {
        expect(substr_count($tag, ':heading='))->toBeLessThanOrEqual(1);
    }
});

test('the sidebar styles are imported by the app stylesheet', function () {
    $app = file_get_contents(resource_path('css/app.css'));
    $sidebar = file_get_contents(resource_path('css/sidebar.css'));

    expect($app)->toContain("@import './sidebar.css'")
        ->and($sidebar)->toContain('[data-current]')
        ->toContain('.dark ui-sidebar');
});
