<?php

use App\Helpers\Utils;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Filters\PurchaseFilter;
use Modules\Inventory\Models\Purchase;

it('turns a typed Jalali date into a Gregorian one', function (mixed $typed, ?string $date) {
    expect(Utils::jalaliToDate($typed))->toBe($date);
})->with([
    'plain' => ['1404/01/01', '2025-03-21'],
    'persian digits' => ['۱۴۰۴/۰۱/۰۱', '2025-03-21'],
    'dashes' => ['1404-01-01', '2025-03-21'],
    'padded with spaces' => [' 1404/01/01 ', '2025-03-21'],
    'empty' => ['', null],
    'half typed' => ['1404/0', null],
    'not a date' => ['abc', null],
    'not padded' => ['1404/1/5', null],
    'null' => [null, null],
    'array' => [['1404/01/01'], null],
]);

it('filters purchases by a valid date and ignores a bad one instead of failing', function () {
    $sql = fn (array $filters) => (new PurchaseFilter($filters))->apply(Purchase::query())->toRawSql();

    // A date bound shows up as a date function on the column, which the negative checks rely on:
    // strftime() on SQLite, date() on MySQL.
    $dateFunction = DB::connection()->getDriverName() === 'sqlite' ? 'strftime' : 'date(';

    expect($sql(['ordered_from' => '۱۴۰۴/۰۱/۰۱']))->toContain($dateFunction, '2025-03-21')
        ->and($sql(['ordered_to' => '1404/01/01']))->toContain('2025-03-21');

    foreach (['1404/0', 'abc', '', null] as $bad) {
        expect($sql(['ordered_from' => $bad, 'ordered_to' => $bad]))->not->toContain($dateFunction);
    }
    expect($sql([]))->not->toContain($dateFunction);
});
