<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Dashboard\Services\SettingService;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentAttemptStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Livewire\OrderPay;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\Order\Models\PaymentAttempt;
use Modules\Order\Payments\GatewayException;
use Modules\Order\Services\PaymentAttemptService;
use Modules\User\Models\User;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
    Cache::flush();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());

    config([
        'payments.currency' => 'IRT',
        'payments.callback_base_url' => 'https://shop.example.test',
        'payments.zarinpal.merchant_id' => '11111111-2222-4333-8444-555555555555',
        'payments.zarinpal.sandbox' => false,
        'payments.nextpay.api_key' => 'b11ee9c3-d23d-414e-8b6e-f2370baac97b',
        'payments.zibal.merchant' => 'zibal',
    ]);

    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => 1, 'user_car_id' => 1, 'warehouse_id' => $warehouse->id,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING,
    ]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => 1, 'unit_id' => 1, 'quantity' => 2, 'unit_price' => 50000, 'total' => 100000]);

    $this->service = fn () => resolve(PaymentAttemptService::class);
    $this->sent = fn (string $path) => collect(Http::recorded())->map(fn (array $pair) => $pair[0])->filter(fn (Request $r) => str_contains($r->url(), $path))->values();
});

// ---------------------------------------------------------------------------
// Zarinpal
// ---------------------------------------------------------------------------

function fakeZarinpal(array $verify = ['data' => ['code' => 100, 'message' => 'Verified', 'ref_id' => 201, 'card_pan' => '502229******5995'], 'errors' => []], array $request = ['data' => ['code' => 100, 'message' => 'Success', 'authority' => 'A0000000000000000000000000000wwOGYpd'], 'errors' => []]): void
{
    Http::fake([
        '*/pg/v4/payment/request.json' => Http::response($request),
        '*/pg/v4/payment/verify.json' => Http::response($verify),
    ]);
}

it('asks Zarinpal for a payment in rials and sends the customer to StartPay', function () {
    fakeZarinpal();

    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $request = ($this->sent)('request.json')->sole();
    expect($request['merchant_id'])->toBe('11111111-2222-4333-8444-555555555555')
        ->and($request['amount'])->toBe(400000, 'toman are sent as rials')
        ->and($request['callback_url'])->toBe("https://shop.example.test/payments/online/{$attempt->public_id}/return")
        ->and($request['description'])->toContain('ORD-00001')
        ->and($request->url())->toStartWith('https://payment.zarinpal.com/')
        ->and($attempt->fresh()->reference)->toBe('A0000000000000000000000000000wwOGYpd')
        ->and($attempt->fresh()->pay_url)->toBe('https://payment.zarinpal.com/pg/StartPay/A0000000000000000000000000000wwOGYpd')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::PENDING)
        ->and(Payment::count())->toBe(0);
});

it('uses the sandbox host when asked to', function () {
    config(['payments.zarinpal.sandbox' => true]);
    fakeZarinpal();

    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 1000);

    expect(($this->sent)('request.json')->sole()->url())->toStartWith('https://sandbox.zarinpal.com/')
        ->and($attempt->fresh()->pay_url)->toStartWith('https://sandbox.zarinpal.com/pg/StartPay/');
});

it('records the payment once the customer returns and Zarinpal confirms it', function () {
    fakeZarinpal();
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $attempt = ($this->service)()->applyReturn($attempt, ['Authority' => 'A0000000000000000000000000000wwOGYpd', 'Status' => 'OK']);

    $verify = ($this->sent)('verify.json')->sole();
    expect($verify['authority'])->toBe('A0000000000000000000000000000wwOGYpd')
        ->and($verify['amount'])->toBe(400000)
        ->and($attempt->status)->toBe(PaymentAttemptStatus::PAID)
        ->and($attempt->result_reference)->toBe('201')
        ->and($attempt->card_pan)->toBe('502229******5995');

    $payment = Payment::sole();
    expect((float) $payment->amount)->toBe(40000.0)
        ->and($payment->method)->toBe(PaymentMethod::ONLINE)
        ->and($payment->order_id)->toBe($this->order->id)
        ->and($payment->description)->toContain('Zarinpal')->toContain('#201')
        ->and($attempt->payment_id)->toBe($payment->id);
});

it('never records the same payment twice, however often the customer returns', function () {
    fakeZarinpal();
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);
    $params = ['Authority' => 'A0000000000000000000000000000wwOGYpd', 'Status' => 'OK'];

    ($this->service)()->applyReturn($attempt, $params);
    ($this->service)()->applyReturn($attempt->fresh(), $params);
    ($this->service)()->reconcile($attempt->fresh(), force: true);

    expect(Payment::count())->toBe(1);
});

it('treats Zarinpal\'s "already verified" answer as paid', function () {
    fakeZarinpal(['data' => ['code' => 101, 'message' => 'Verified', 'ref_id' => 77], 'errors' => []]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    expect(($this->service)()->applyReturn($attempt, ['Authority' => $attempt->fresh()->reference, 'Status' => 'OK'])->status)->toBe(PaymentAttemptStatus::PAID);
});

it('does not even ask Zarinpal when the customer came back having cancelled', function () {
    fakeZarinpal();
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $attempt = ($this->service)()->applyReturn($attempt, ['Authority' => $attempt->fresh()->reference, 'Status' => 'NOK']);

    expect($attempt->status)->toBe(PaymentAttemptStatus::CANCELLED)
        ->and(($this->sent)('verify.json'))->toHaveCount(0)
        ->and(Payment::count())->toBe(0);
});

it('keeps waiting while Zarinpal says the payment is not finished', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -51, 'message' => 'Session is not valid']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $attempt = ($this->service)()->reconcile($attempt);

    expect($attempt->status)->toBe(PaymentAttemptStatus::PENDING)->and(Payment::count())->toBe(0);
});

it('gives up on a payment that was never made once its time is up', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -51, 'message' => 'Session is not valid']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);
    $this->travel(31)->minutes();

    expect(($this->service)()->reconcile($attempt)->status)->toBe(PaymentAttemptStatus::EXPIRED);
});

it('still notices a payment made after the customer was given up on', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -51, 'message' => 'x']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);
    $this->travel(31)->minutes();
    ($this->service)()->reconcile($attempt);
    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::EXPIRED);

    fakeZarinpal();
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(['*/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 5], 'errors' => []])]);

    $attempt = ($this->service)()->applyReturn($attempt->fresh(), ['Authority' => $attempt->reference, 'Status' => 'OK']);

    expect($attempt->status)->toBe(PaymentAttemptStatus::PAID)->and(Payment::count())->toBe(1);
});

it('fails the payment when Zarinpal says the amount differs', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -50, 'message' => 'Amount differs']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $attempt = ($this->service)()->reconcile($attempt);

    expect($attempt->status)->toBe(PaymentAttemptStatus::FAILED)->and($attempt->failure)->toContain('-50');
});

it('marks the attempt failed and says why when Zarinpal refuses to create the payment', function () {
    fakeZarinpal(request: ['data' => [], 'errors' => ['code' => -10, 'message' => 'Terminal is not valid']]);

    expect(fn () => ($this->service)()->startOnline($this->order, 'zarinpal', 40000))
        ->toThrow(GatewayException::class, '-10');

    $attempt = PaymentAttempt::sole();
    expect($attempt->status)->toBe(PaymentAttemptStatus::FAILED)->and($attempt->pay_url)->toBeNull()->and($attempt->failure)->toContain('-10');
});

it('ignores a return that carries another payment\'s reference', function () {
    fakeZarinpal();
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $attempt = ($this->service)()->applyReturn($attempt, ['Authority' => 'A-SOMEONE-ELSE', 'Status' => 'OK']);

    expect($attempt->status)->toBe(PaymentAttemptStatus::PENDING)->and(($this->sent)('verify.json'))->toHaveCount(0);
});

it('leaves the payment open when the gateway cannot be reached', function () {
    fakeZarinpal();
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('Bad gateway', 502)]);

    $attempt = ($this->service)()->reconcile($attempt);

    expect($attempt->status)->toBe(PaymentAttemptStatus::PENDING)->and(Payment::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// NextPay
// ---------------------------------------------------------------------------

it('creates a NextPay token with a form and verifies it by trans_id', function () {
    Http::fake([
        '*/nx/gateway/token' => Http::response(['code' => -1, 'trans_id' => 'f7c07568-c6d1-4bee-87b1-4a9e5ed2e4c1']),
        '*/nx/gateway/verify' => Http::response(['code' => 0, 'amount' => 40000, 'order_id' => 'x', 'Shaparak_Ref_Id' => '990011', 'card_holder' => '5022-29**-****-5020']),
    ]);

    $attempt = ($this->service)()->startOnline($this->order, 'nextpay', 40000);

    $token = ($this->sent)('/token')->sole();
    expect($token->isForm())->toBeTrue()
        ->and($token['api_key'])->toBe('b11ee9c3-d23d-414e-8b6e-f2370baac97b')
        ->and($token['order_id'])->toBe($attempt->public_id)
        ->and((int) $token['amount'])->toBe(40000)
        ->and($token['currency'])->toBe('IRT')
        ->and($token['callback_uri'])->toBe("https://shop.example.test/payments/online/{$attempt->public_id}/return")
        ->and($attempt->fresh()->pay_url)->toBe('https://nextpay.org/nx/gateway/payment/f7c07568-c6d1-4bee-87b1-4a9e5ed2e4c1');

    // NextPay says nothing about success in the return: it is always verified.
    $attempt = ($this->service)()->applyReturn($attempt, ['trans_id' => 'f7c07568-c6d1-4bee-87b1-4a9e5ed2e4c1', 'order_id' => $attempt->public_id, 'amount' => '40000']);

    $verify = ($this->sent)('/verify')->sole();
    expect($verify['trans_id'])->toBe('f7c07568-c6d1-4bee-87b1-4a9e5ed2e4c1')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::PAID)
        ->and($attempt->result_reference)->toBe('990011')
        ->and(Payment::sole()->description)->toContain('NextPay')->toContain('#990011');
});

it('keeps waiting on NextPay codes that mean "not yet" and fails on the ones that mean "never"', function () {
    Http::fake([
        '*/nx/gateway/token' => Http::response(['code' => -1, 'trans_id' => 'f7c07568-c6d1-4bee-87b1-4a9e5ed2e4c1']),
        '*/nx/gateway/verify' => Http::sequence()->push(['code' => -1])->push(['code' => -3])->push(['code' => -2]),
    ]);
    $attempt = ($this->service)()->startOnline($this->order, 'nextpay', 40000);

    expect(($this->service)()->reconcile($attempt)->status)->toBe(PaymentAttemptStatus::PENDING)
        ->and(($this->service)()->reconcile($attempt)->status)->toBe(PaymentAttemptStatus::PENDING)
        ->and(($this->service)()->reconcile($attempt)->status)->toBe(PaymentAttemptStatus::FAILED);
});

// ---------------------------------------------------------------------------
// Zibal
// ---------------------------------------------------------------------------

it('creates a Zibal payment in rials and verifies it by trackId', function () {
    Http::fake([
        '*/v1/request' => Http::response(['trackId' => 3456789, 'result' => 100, 'message' => 'success']),
        '*/v1/verify' => Http::response(['result' => 100, 'status' => 1, 'amount' => 400000, 'refNumber' => 998877, 'cardNumber' => '62198610****1234', 'message' => 'success']),
    ]);

    $attempt = ($this->service)()->startOnline($this->order, 'zibal', 40000);

    $request = ($this->sent)('/v1/request')->sole();
    expect($request['merchant'])->toBe('zibal')
        ->and($request['amount'])->toBe(400000)
        ->and($request['callbackUrl'])->toBe("https://shop.example.test/payments/online/{$attempt->public_id}/return")
        ->and($attempt->fresh()->pay_url)->toBe('https://gateway.zibal.ir/start/3456789');

    $attempt = ($this->service)()->applyReturn($attempt, ['trackId' => '3456789', 'success' => '1', 'status' => '2']);

    expect(($this->sent)('/v1/verify')->sole()['trackId'])->toBe(3456789)
        ->and($attempt->status)->toBe(PaymentAttemptStatus::PAID)
        ->and($attempt->result_reference)->toBe('998877');
});

it('refuses a Zibal confirmation for a different amount than was asked', function () {
    Http::fake([
        '*/v1/request' => Http::response(['trackId' => 1, 'result' => 100, 'message' => 'success']),
        '*/v1/verify' => Http::response(['result' => 100, 'amount' => 1000, 'refNumber' => 1]),
    ]);
    $attempt = ($this->service)()->startOnline($this->order, 'zibal', 40000);

    expect(($this->service)()->reconcile($attempt)->status)->toBe(PaymentAttemptStatus::FAILED)->and(Payment::count())->toBe(0);
});

it('does not ask Zibal when the customer returned unsuccessful, and waits on result 202', function () {
    Http::fake([
        '*/v1/request' => Http::response(['trackId' => 1, 'result' => 100, 'message' => 'success']),
        '*/v1/verify' => Http::response(['result' => 202, 'message' => 'not paid']),
    ]);
    $attempt = ($this->service)()->startOnline($this->order, 'zibal', 40000);

    expect(($this->service)()->reconcile($attempt)->status)->toBe(PaymentAttemptStatus::PENDING);

    $attempt = ($this->service)()->applyReturn($attempt, ['trackId' => '1', 'success' => '0', 'status' => '3']);

    expect($attempt->status)->toBe(PaymentAttemptStatus::CANCELLED)->and(($this->sent)('/v1/verify'))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Rules for any payment
// ---------------------------------------------------------------------------

it('refuses amounts that are zero, more than the order owes, or for a cancelled order', function () {
    fakeZarinpal();

    foreach ([0, -5, 100001] as $amount) {
        expect(fn () => ($this->service)()->startOnline($this->order, 'zarinpal', $amount))->toThrow(InvalidArgumentException::class);
    }

    $this->order->update(['status' => OrderStatus::CANCELLED]);
    expect(fn () => ($this->service)()->startOnline($this->order->fresh(), 'zarinpal', 1000))->toThrow(InvalidArgumentException::class, 'cancelled');

    expect(PaymentAttempt::count())->toBe(0)->and(Http::recorded())->toHaveCount(0);
});

it('takes the amount already paid off what can be collected', function () {
    fakeZarinpal();
    Payment::forceCreate(['slug' => 'p1', 'order_id' => $this->order->id, 'amount' => 70000, 'method' => PaymentMethod::CASH, 'paid_at' => now()]);

    expect(($this->service)()->balance($this->order))->toBe(30000.0)
        ->and(fn () => ($this->service)()->startOnline($this->order, 'zarinpal', 30001))->toThrow(InvalidArgumentException::class);
});

it('allows one payment in progress per kind and a gateway only when it is set up', function () {
    fakeZarinpal();
    ($this->service)()->startOnline($this->order, 'zarinpal', 10000);

    expect(fn () => ($this->service)()->startOnline($this->order, 'zarinpal', 10000))->toThrow(InvalidArgumentException::class, 'in progress');

    config(['payments.nextpay.api_key' => null]);
    expect(fn () => ($this->service)()->startOnline($this->order, 'nextpay', 10000))->toThrow(InvalidArgumentException::class, 'not set up')
        ->and(fn () => ($this->service)()->startOnline($this->order, 'nope', 10000))->toThrow(InvalidArgumentException::class);
});

it('lets the cashier cancel, unless the customer already paid', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -51, 'message' => 'x']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    expect(($this->service)()->cancel($attempt)->status)->toBe(PaymentAttemptStatus::CANCELLED);

    Http::swap(new Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A2'], 'errors' => []]),
        '*/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 9], 'errors' => []]),
    ]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    expect(($this->service)()->cancel($attempt)->status)->toBe(PaymentAttemptStatus::PAID)->and(Payment::count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Card terminal
// ---------------------------------------------------------------------------

it('records a card terminal payment once the cashier confirms it with the terminal\'s reference', function () {
    $attempt = ($this->service)()->startPos($this->order, 'manual', 25000);

    expect($attempt->status)->toBe(PaymentAttemptStatus::PENDING)->and(Http::recorded())->toHaveCount(0);

    $attempt = ($this->service)()->confirmPos($attempt, ' 123456 ', '4111 1111 1111 5678');

    $payment = Payment::sole();
    expect($payment->method)->toBe(PaymentMethod::POS)
        ->and((float) $payment->amount)->toBe(25000.0)
        ->and($payment->description)->toContain('#123456')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::PAID)
        ->and($attempt->card_pan)->toBe('**** 5678')
        ->and($attempt->result_reference)->toBe('123456');
});

it('needs a terminal reference, and never confirms twice', function () {
    $attempt = ($this->service)()->startPos($this->order, 'manual', 25000);

    expect(fn () => ($this->service)()->confirmPos($attempt, '   '))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ($this->service)()->confirmPos($attempt, str_repeat('9', 51)))->toThrow(InvalidArgumentException::class);

    ($this->service)()->confirmPos($attempt, '1');

    expect(fn () => ($this->service)()->confirmPos($attempt->fresh(), '1'))->toThrow(InvalidArgumentException::class)
        ->and(Payment::count())->toBe(1);
});

it('records nothing when the terminal declined or the cashier cancelled', function () {
    $attempt = ($this->service)()->startPos($this->order, 'manual', 25000);
    expect(($this->service)()->declinePos($attempt)->status)->toBe(PaymentAttemptStatus::FAILED);

    $attempt = ($this->service)()->startPos($this->order, 'manual', 25000);
    expect(($this->service)()->cancel($attempt)->status)->toBe(PaymentAttemptStatus::CANCELLED)->and(Payment::count())->toBe(0);
});

it('has no automatic card terminal, so only the manual one is offered', function () {
    expect(array_keys(resolve(Modules\Order\Payments\PaymentDrivers::class)->terminals()))->toBe(['manual'])
        ->and(fn () => ($this->service)()->startPos($this->order, 'some-bank-terminal', 1000))->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// Finding payments nobody came back for
// ---------------------------------------------------------------------------

it('finds a customer who paid but never came back, and expires the ones who never paid', function () {
    Http::fake([
        '*/request.json' => Http::sequence()
            ->push(['data' => ['code' => 100, 'authority' => 'PAID-ONE'], 'errors' => []]),
        '*/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 31], 'errors' => []]),
    ]);
    ($this->service)()->startOnline($this->order, 'zarinpal', 40000);
    ($this->service)()->startPos($this->order, 'manual', 10000);
    $this->travel(31)->minutes();

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(PaymentAttempt::where('kind', 'gateway')->sole()->status)->toBe(PaymentAttemptStatus::PAID)
        ->and(PaymentAttempt::where('kind', 'pos')->sole()->status)->toBe(PaymentAttemptStatus::EXPIRED)
        ->and(Payment::count())->toBe(1);
});

it('runs the reconcile command every minute', function () {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'payments:reconcile'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});

// ---------------------------------------------------------------------------
// What the customer's browser sees
// ---------------------------------------------------------------------------

it('shows the customer the outcome from the gateway, over GET and POST, with no login', function () {
    fakeZarinpal();
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);
    auth()->logout();

    $this->get("/payments/online/{$attempt->public_id}/return?Authority={$attempt->fresh()->reference}&Status=OK")
        ->assertOk()
        ->assertSee('data-attempt-status="paid"', false)
        ->assertSee('ORD-00001')
        ->assertDontSee('A0000000000000000000000000000wwOGYpd')
        ->assertDontSee('<meta http-equiv="refresh"', false);

    $this->post("/payments/online/{$attempt->public_id}/return", ['Authority' => $attempt->reference, 'Status' => 'OK'])
        ->assertOk()->assertSee('data-attempt-status="paid"', false);

    expect(Payment::count())->toBe(1);
});

it('shows a waiting page that reloads itself while the gateway has not confirmed', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -51, 'message' => 'x']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $this->get("/payments/online/{$attempt->public_id}/return?Authority={$attempt->reference}&Status=OK")
        ->assertOk()
        ->assertSee('data-attempt-status="pending"', false)
        ->assertSee('<meta http-equiv="refresh"', false);
});

it('does not believe a forged return', function () {
    fakeZarinpal(['data' => [], 'errors' => ['code' => -51, 'message' => 'x']]);
    $attempt = ($this->service)()->startOnline($this->order, 'zarinpal', 40000);

    $this->get("/payments/online/{$attempt->public_id}/return?Authority={$attempt->reference}&Status=OK&paid=1&success=1")->assertOk();

    expect(Payment::count())->toBe(0);
});

it('knows nothing of an unknown payment', function () {
    $this->get('/payments/online/'.Illuminate\Support\Str::uuid().'/return')->assertNotFound();
    $this->get('/payments/online/not-a-uuid/return')->assertNotFound();
});

// ---------------------------------------------------------------------------
// The cashier's screen
// ---------------------------------------------------------------------------

it('offers only the gateways that are set up', function () {
    config(['payments.nextpay.api_key' => null, 'payments.zibal.merchant' => null]);

    $this->get(route('orders.pay', $this->order))
        ->assertOk()
        ->assertSee('data-gateway="zarinpal"', false)
        ->assertDontSee('data-gateway="nextpay"', false)
        ->assertDontSee('data-gateway="zibal"', false)
        ->assertSee('data-terminal="manual"', false);
});

it('tells the cashier where to set a gateway up when there is none', function () {
    config(['payments.zarinpal.merchant_id' => null, 'payments.nextpay.api_key' => null, 'payments.zibal.merchant' => null]);

    $this->get(route('orders.pay', $this->order))->assertOk()->assertSee(__('order::strings.No Gateway Configured'))->assertSee(route('system-settings.edit'), false);
});

it('starts an online payment from the screen and turns green when the customer has paid', function () {
    fakeZarinpal();

    $component = Livewire::test(OrderPay::class, ['order' => $this->order])
        ->assertSet('amount', '100000')
        ->set('amount', '40000')
        ->call('payOnline', 'zarinpal')
        ->assertSee('data-open-attempt="gateway"', false)
        ->assertSee('https://payment.zarinpal.com/pg/StartPay/A0000000000000000000000000000wwOGYpd');

    $component->call('refreshAttempt')
        ->assertDontSee('data-open-attempt', false)
        ->assertSet('attemptId', null)
        ->assertSet('amount', '60000');

    expect(Payment::sole()->method)->toBe(PaymentMethod::ONLINE);
});

it('shows the reason when a gateway refuses, and records nothing', function () {
    fakeZarinpal(request: ['data' => [], 'errors' => ['code' => -10, 'message' => 'x']]);

    Livewire::test(OrderPay::class, ['order' => $this->order])->set('amount', '40000')->call('payOnline', 'zarinpal')->assertSet('attemptId', null);

    expect(Payment::count())->toBe(0)->and(PaymentAttempt::sole()->status)->toBe(PaymentAttemptStatus::FAILED);
});

it('refuses an amount over the balance from the screen', function () {
    fakeZarinpal();

    Livewire::test(OrderPay::class, ['order' => $this->order])->set('amount', '999999')->call('payOnline', 'zarinpal')->assertSet('attemptId', null);

    expect(PaymentAttempt::count())->toBe(0);
});

it('confirms a card terminal payment from the screen', function () {
    Livewire::test(OrderPay::class, ['order' => $this->order])
        ->set('amount', '25000')
        ->call('payByCard', 'manual')
        ->assertSee('data-open-attempt="pos"', false)
        ->set('terminalReference', '778899')
        ->set('cardLastDigits', '1234')
        ->call('confirmCard')
        ->assertSet('attemptId', null)
        ->assertSet('amount', '75000');

    expect(Payment::sole()->method)->toBe(PaymentMethod::POS);
});

it('picks up an open payment when the cashier comes back to the order', function () {
    $attempt = ($this->service)()->startPos($this->order, 'manual', 25000);

    Livewire::test(OrderPay::class, ['order' => $this->order])->assertSet('attemptId', $attempt->public_id)->assertSee('data-open-attempt="pos"', false);
});

it('does not offer to collect money for a cancelled order', function () {
    $this->order->update(['status' => OrderStatus::CANCELLED]);

    $this->get(route('orders.pay', $this->order))->assertNotFound();
});

it('adds the collect payment button to the orders list for unpaid orders only', function () {
    // The list renders every order's customer and car, so keep only one with both.
    $this->order->delete();
    $customer = User::factory()->create();
    $customer->forceFill(['slug' => 'cust9'])->save();
    $car = Modules\Car\Models\Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $userCar = Modules\Car\Models\UserCar::forceCreate(['slug' => 'uc9', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $order = Order::forceCreate([
        'slug' => 'o9', 'user_id' => $customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $this->order->warehouse_id,
        'order_number' => 'ORD-00009', 'status' => OrderStatus::PENDING,
    ]);
    OrderItem::forceCreate(['slug' => 'oi9', 'order_id' => $order->id, 'item_id' => 1, 'unit_id' => 1, 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000]);

    $this->get(route('orders.index'))->assertOk()->assertSee(route('orders.pay', $order), false);

    Payment::forceCreate(['slug' => 'p9', 'order_id' => $order->id, 'amount' => 5000, 'method' => PaymentMethod::CASH, 'paid_at' => now()]);

    $this->get(route('orders.index'))->assertOk()->assertDontSee(route('orders.pay', $order), false);
});

it('keeps the gateway credentials in the encrypted settings', function () {
    $fields = resolve(SettingService::class)->fields();

    expect($fields['zarinpal.merchant_id']['secret'])->toBeTrue()
        ->and($fields['nextpay.api_key']['secret'])->toBeTrue()
        ->and($fields['zibal.merchant']['secret'])->toBeTrue()
        ->and($fields['zarinpal.merchant_id']['config'])->toBe('payments.zarinpal.merchant_id');
});

// ---------------------------------------------------------------------------
// QR code and SMS link
// ---------------------------------------------------------------------------

it('shows a QR code of the payment page while an online payment is open', function () {
    fakeZarinpal();

    $html = Livewire::test(OrderPay::class, ['order' => $this->order])
        ->set('amount', '40000')
        ->call('payOnline', 'zarinpal')
        ->assertSee('data-payment-qr', false)
        ->html();

    expect($html)->toContain('<svg')->not->toContain('<?xml');
});

it('draws the QR code from the payment link only and as a clean SVG', function () {
    $svg = Modules\Order\Payments\QrCode::svg('https://payment.zarinpal.com/pg/StartPay/A1');

    expect($svg)->toStartWith('<svg')->toEndWith('</svg>')->not->toContain('<script')
        ->and(Modules\Order\Payments\QrCode::svg('https://payment.zarinpal.com/pg/StartPay/A1'))->toBe($svg);
});

it('has no QR code for a card terminal payment or when nothing is open', function () {
    Livewire::test(OrderPay::class, ['order' => $this->order])->assertDontSee('data-payment-qr', false)
        ->set('amount', '25000')->call('payByCard', 'manual')->assertDontSee('data-payment-qr', false);
});

it('texts the customer the payment link, at most once every half minute', function () {
    fakeZarinpal();
    $customer = User::factory()->create(['mobile' => '09121234567']);
    $this->order->update(['user_id' => $customer->id]);

    Modules\Dashboard\Facades\Sms::shouldReceive('send')->once()->withArgs(fn (string $to, string $message) => $to === '09121234567'
        && str_contains($message, 'https://payment.zarinpal.com/pg/StartPay/A0000000000000000000000000000wwOGYpd')
        && str_contains($message, 'ORD-00001'));

    Livewire::test(OrderPay::class, ['order' => $this->order->fresh()])
        ->set('amount', '40000')
        ->call('payOnline', 'zarinpal')
        ->call('sendLink')
        ->call('sendLink');
});

it('says so instead of texting a customer who has no mobile', function () {
    fakeZarinpal();
    $customer = User::factory()->create(['mobile' => '']);
    $this->order->update(['user_id' => $customer->id]);
    Modules\Dashboard\Facades\Sms::shouldReceive('send')->never();

    Livewire::test(OrderPay::class, ['order' => $this->order->fresh()])->set('amount', '40000')->call('payOnline', 'zarinpal')->call('sendLink')->assertOk();
});

it('keeps the payment open and allows a retry when the SMS cannot be sent', function () {
    fakeZarinpal();
    $customer = User::factory()->create(['mobile' => '09121234567']);
    $this->order->update(['user_id' => $customer->id]);
    Modules\Dashboard\Facades\Sms::shouldReceive('send')->twice()->andThrow(new Modules\Dashboard\Exceptions\SmsException('no credit'));

    Livewire::test(OrderPay::class, ['order' => $this->order->fresh()])
        ->set('amount', '40000')
        ->call('payOnline', 'zarinpal')
        ->call('sendLink')
        ->call('sendLink')
        ->assertSee('data-open-attempt="gateway"', false);

    expect(PaymentAttempt::sole()->status)->toBe(PaymentAttemptStatus::PENDING);
});
