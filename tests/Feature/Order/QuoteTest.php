<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Facades\Sms;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Enums\QuoteStatus;
use Modules\Order\Livewire\OrderCreate;
use Modules\Order\Livewire\QuotePrint;
use Modules\Order\Livewire\Quotes;
use Modules\Order\Models\Order;
use Modules\Order\Models\Quote;
use Modules\Order\Services\QuoteService;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->piece = Unit::forceCreate(['name' => 'Piece', 'symbol' => 'pc', 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_base' => true, 'slug' => 'piece']);
    $this->oil = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->labour = Item::forceCreate(['name' => 'Labour', 'slug' => 'labour', 'unit_group_id' => $group->id, 'category_id' => 1, 'is_service' => true]);
    $this->warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->stock = WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $this->warehouse->id, 'item_id' => $this->oil->id, 'quantity' => 30]);

    $this->customer = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Rezaei', 'mobile' => '09121234567']);
    $this->customer->forceFill(['type' => UserType::Customer])->save();
    $car = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => 1]);
    $this->userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $this->customer->id, 'car_id' => $car->id, 'color_id' => 1]);

    $this->fill = fn ($component) => $component
        ->set('form.user_id', $this->customer->id)
        ->set('form.user_car_id', $this->userCar->id)
        ->set('form.warehouse', 'main')
        ->set('form.selected', ['main-oil'])
        ->set('form.items', ['main-oil' => ['quantity' => 4, 'unit_price' => 50000, 'unit_id' => $this->piece->id]])
        ->set('form.services', ['labour' => ['quantity' => 1, 'unit_price' => 200000, 'mechanic_id' => null]]);
    $this->quote = function (array $attributes = []): Quote {
        static $n = 0;
        $n++;
        $quote = Quote::forceCreate($attributes + [
            'slug' => "q{$n}", 'quote_number' => "QT-0000{$n}", 'user_id' => $this->customer->id, 'user_car_id' => $this->userCar->id,
            'warehouse_id' => $this->warehouse->id, 'status' => QuoteStatus::DRAFT, 'valid_until' => now()->addDays(7)->toDateString(),
        ]);
        $quote->items()->forceCreate(['slug' => "qi{$n}", 'quote_id' => $quote->id, 'item_id' => $this->oil->id, 'unit_id' => $this->piece->id, 'quantity' => 2, 'unit_price' => 1000, 'total' => 2000]);

        return $quote;
    };
});

it('keeps the order form as a quote without touching stock or sales', function () {
    ($this->fill)(Livewire::test(OrderCreate::class))->call('saveQuote')->assertHasNoErrors()->assertRedirect(route('quotes.index'));

    $quote = Quote::sole();
    expect($quote->quote_number)->toBe('QT-00001')
        ->and($quote->status)->toBe(QuoteStatus::DRAFT)
        ->and($quote->user_id)->toBe($this->customer->id)
        ->and($quote->valid_until->toDateString())->toBe(now()->addDays(QuoteService::VALID_DAYS)->toDateString())
        ->and($quote->items()->count())->toBe(2)
        ->and(resolve(QuoteService::class)->total($quote))->toBe(400000.0)
        ->and(Order::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and((float) $this->stock->fresh()->quantity)->toBe(30.0);
});

it('validates the form before keeping a quote', function () {
    Livewire::test(OrderCreate::class)->call('saveQuote')->assertHasErrors(['form.user_id', 'form.user_car_id']);

    expect(Quote::count())->toBe(0);
});

it('gives the quote back to the order form as it was, saying what could not come along', function () {
    $quote = ($this->quote)();
    $quote->items()->forceCreate(['slug' => 'qs', 'quote_id' => $quote->id, 'item_id' => $this->labour->id, 'mechanic_id' => null, 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000]);
    $gone = Item::forceCreate(['name' => 'Gone', 'slug' => 'gone', 'unit_group_id' => $this->oil->unit_group_id, 'category_id' => 1]);
    $quote->items()->forceCreate(['slug' => 'qg', 'quote_id' => $quote->id, 'item_id' => $gone->id, 'quantity' => 1, 'unit_price' => 1, 'total' => 1]);

    $form = resolve(QuoteService::class)->toOrderForm($quote);

    expect($form['form']['warehouse'])->toBe('main')
        ->and($form['form']['items'])->toBe(['main-oil' => ['quantity' => 2.0, 'unit_price' => 1000.0, 'unit_id' => $this->piece->id]])
        ->and($form['form']['services'])->toBe(['labour' => ['quantity' => 1.0, 'unit_price' => 5000.0, 'mechanic_id' => null]])
        ->and($form['dropped'])->toBe(1);
});

it('opens a quote in the order form and marks it converted once the order is saved', function () {
    $quote = ($this->quote)();

    Livewire::test(Quotes::class)->call('openInOrderForm', $quote->id)->assertRedirect(route('orders.create'));

    $page = Livewire::test(OrderCreate::class)
        ->assertSet('quoteId', $quote->id)
        ->assertSet('form.user_id', $this->customer->id)
        ->assertSet('form.user_car_id', $this->userCar->id)
        ->assertSet('form.selected', ['main-oil'])
        ->assertSet('form.items.main-oil.quantity', 2.0);

    $page->call('save')->assertHasNoErrors();

    $order = Order::sole();
    expect($quote->fresh()->status)->toBe(QuoteStatus::CONVERTED)
        ->and($quote->fresh()->order_id)->toBe($order->id)
        ->and((float) $this->stock->fresh()->quantity)->toBe(28.0);

    // Taken once: a fresh form starts empty.
    Livewire::test(OrderCreate::class)->assertSet('quoteId', null)->assertSet('form.selected', []);
});

it('updates the same quote when it is saved again from the form', function () {
    $quote = ($this->quote)();
    session()->put('order_from_quote', $quote->id);

    Livewire::test(OrderCreate::class)
        ->set('form.items', ['main-oil' => ['quantity' => 7, 'unit_price' => 1500, 'unit_id' => $this->piece->id]])
        ->call('saveQuote')
        ->assertHasNoErrors();

    expect(Quote::count())->toBe(1)
        ->and($quote->items()->count())->toBe(1)
        ->and((float) $quote->items()->sole()->total)->toBe(10500.0);
});

it('does not change or reopen a quote that was answered', function () {
    $converted = ($this->quote)(['status' => QuoteStatus::CONVERTED]);
    $rejected = ($this->quote)(['status' => QuoteStatus::REJECTED]);

    expect(fn () => resolve(QuoteService::class)->save(['user_id' => 1, 'user_car_id' => 1, 'warehouse' => 'main', 'items' => [], 'services' => []], $converted))
        ->toThrow(InvalidArgumentException::class);

    Livewire::test(Quotes::class)->call('openInOrderForm', $converted->id)->assertNoRedirect()->call('openInOrderForm', $rejected->id)->assertNoRedirect();
    expect(session('order_from_quote'))->toBeNull();
});

it('records the customer answer, but never moves a converted quote', function () {
    $quote = ($this->quote)();
    $done = ($this->quote)(['status' => QuoteStatus::CONVERTED]);

    Livewire::test(Quotes::class)->call('setStatus', $quote->id, 'accepted')->call('setStatus', $done->id, 'rejected')->call('setStatus', $quote->id, 'nonsense');

    expect($quote->fresh()->status)->toBe(QuoteStatus::ACCEPTED)
        ->and($done->fresh()->status)->toBe(QuoteStatus::CONVERTED);
});

it('shows a quote that ran out of time as expired, but not an answered one', function () {
    ($this->quote)(['valid_until' => now()->subDay()->toDateString()]);
    ($this->quote)(['valid_until' => now()->subDay()->toDateString(), 'status' => QuoteStatus::ACCEPTED]);

    Livewire::test(Quotes::class)->assertSeeHtmlInOrder(['data-quote="QT-00', 'data-quote-expired'])->assertSee('data-quote-expired', false);

    expect(Quote::where('status', 'accepted')->sole()->isExpired())->toBeFalse();
});

it('filters and searches the list', function () {
    ($this->quote)();
    ($this->quote)(['status' => QuoteStatus::REJECTED]);

    Livewire::test(Quotes::class)
        ->set('status', 'rejected')
        ->assertViewHas('quotes', fn ($quotes) => $quotes->count() === 1)
        ->set('status', '')
        ->set('search', 'Rezaei')
        ->assertViewHas('quotes', fn ($quotes) => $quotes->count() === 2)
        ->set('search', 'nobody')
        ->assertSee('data-no-quotes', false);
});

it('texts the quote to the customer and marks it sent', function () {
    $quote = ($this->quote)();
    Sms::shouldReceive('send')->once()->withArgs(fn (string $to, string $text) => $to === '09121234567' && str_contains($text, 'QT-0000') && str_contains($text, 'Pride'));

    Livewire::test(Quotes::class)->call('sendSms', $quote->id);

    expect($quote->fresh()->status)->toBe(QuoteStatus::SENT);
});

it('does not text a customer who has no mobile', function () {
    $this->customer->forceFill(['mobile' => ''])->save();
    $quote = ($this->quote)();
    Sms::shouldReceive('send')->never();

    Livewire::test(Quotes::class)->call('sendSms', $quote->id);

    expect($quote->fresh()->status)->toBe(QuoteStatus::DRAFT);
});

it('deletes a quote unless it already became an order', function () {
    $open = ($this->quote)();
    $done = ($this->quote)(['status' => QuoteStatus::CONVERTED]);

    Livewire::test(Quotes::class)->call('delete', $open->id)->call('delete', $done->id);

    expect(Quote::find($open->id))->toBeNull()->and(Quote::find($done->id))->not->toBeNull();
});

it('prints the quote with its lines and total', function () {
    $quote = ($this->quote)();

    $this->get(route('quotes.print', $quote))->assertOk();

    Livewire::test(QuotePrint::class, ['quote' => $quote])->assertSee($quote->quote_number)->assertSee('Oil')->assertSee(App\Helpers\Utils::formatQuantity(2000, 2, ','));
});

it('is reachable from the menu', function () {
    $this->get(route('quotes.index'))->assertOk()->assertSee(route('quotes.index'), false);
});
