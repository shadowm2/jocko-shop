<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

// deferForeignKeys() turns constraint checks off for the rest of a test; put them back
// so a test that did it cannot leak into the next one on a connection that is reused.
afterEach(function () {
    Schema::enableForeignKeyConstraints();
});

/**
 * Let a test build bare rows (an order without its customer, car and so on)
 * without the foreign keys objecting. Tests run inside a transaction, where
 * SQLite can only defer the check and MySQL can only switch it off.
 */
function deferForeignKeys(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA defer_foreign_keys = ON');

        return;
    }

    Schema::disableForeignKeyConstraints();
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
