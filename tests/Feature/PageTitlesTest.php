<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Models\User;

/**
 * Every page that needs no record to open: the Livewire pages of the app.
 *
 * @return array<int, string>
 */
function titledPageRoutes(): array
{
    return collect(Route::getRoutes()->getRoutesByMethod()['GET'] ?? [])
        ->filter(fn ($route) => $route->getName() !== null
            && ! str_contains($route->uri(), '{')
            && str_contains($route->getActionName(), 'LivewirePageController')
            && $route->getName() !== 'login.otp')
        ->map(fn ($route) => $route->getName())
        ->values()
        ->all();
}

function pageTitle(string $html): string
{
    preg_match('#<title>(.*?)</title>#s', $html, $match);

    return trim(html_entity_decode($match[1] ?? ''));
}

test('there are pages to check', function () {
    expect(titledPageRoutes())->toContain('items.index', 'items.create', 'suppliers.create', 'warehouses.create');
});

test('every page has a real, translated title in both languages', function (string $locale) {
    $this->actingAs(User::factory()->create());
    app()->setLocale($locale);

    $problems = [];

    foreach (titledPageRoutes() as $name) {
        $title = pageTitle($this->get(route($name))->getContent());

        if ($title === '' || $title === config('app.name') || str_contains($title, '::') || str_contains($title, 'strings.')) {
            $problems[$name] = $title;
        }
    }

    expect($problems)->toBe([]);
})->with(['fa', 'en']);

test('no two pages share a title, so a copied title is caught', function (string $locale) {
    $this->actingAs(User::factory()->create());
    app()->setLocale($locale);

    $byTitle = [];

    foreach (titledPageRoutes() as $name) {
        $byTitle[pageTitle($this->get(route($name))->getContent())][] = $name;
    }

    $shared = array_filter($byTitle, fn (array $routes) => count($routes) > 1);

    expect($shared)->toBe([]);
})->with(['fa', 'en']);
