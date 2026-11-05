<?php

namespace Modules\Order\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Modules\Dashboard\Exceptions\SmsException;
use Modules\Dashboard\Facades\Sms;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\PaymentReminder;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

/**
 * What a customer owes: per order, in total, who owes the most, and a text to remind them.
 * Cancelled orders are not owed.
 *
 * @phpstan-type Debtor array{user: User, balance: float, open_orders: int, oldest_at: ?CarbonInterface, last_reminder_at: ?CarbonImmutable, aging: list<float>}
 */
class CustomerBalanceService
{
    /** An order's debt is aged from its creation: up to 30 days, 31-60, 61-90, and over 90. */
    public const AGING_DAYS = [30, 60, 90];

    /**
     * The customer's orders (not cancelled), newest first, each with its total and what was paid.
     *
     * @return EloquentCollection<int, Order>
     */
    public function orders(User $customer): EloquentCollection
    {
        return $this->liveOrders()->where('user_id', $customer->getKey())->latest('id')->get();
    }

    /**
     * @return array{total: float, paid: float, balance: float, open_orders: int}
     */
    public function summary(User $customer): array
    {
        return $this->sum($this->orders($customer));
    }

    /**
     * Customers who owe something, the most first.
     *
     * @return Collection<int, Debtor>
     */
    public function debtors(): Collection
    {
        $owing = $this->liveOrders()->get()->filter(fn (Order $order): bool => $this->owed($order) > 0)->groupBy('user_id');

        $users = User::query()->whereKey($owing->keys())->get()->keyBy('id');
        $reminded = PaymentReminder::query()->where('status', 'sent')->whereIn('user_id', $owing->keys())
            ->selectRaw('user_id, max(created_at) as last_at')->groupBy('user_id')->pluck('last_at', 'user_id');

        /** @var list<Debtor> $rows */
        $rows = [];

        foreach ($owing as $userId => $orders) {
            $user = $users->get($userId);

            if ($user === null) {
                continue;
            }

            $rows[] = [
                'user' => $user,
                'balance' => round((float) $orders->sum(fn (Order $order): float => $this->owed($order)), 2),
                'open_orders' => $orders->count(),
                'oldest_at' => $this->oldest($orders),
                'aging' => $this->aging($orders),
                'last_reminder_at' => isset($reminded[$userId]) ? Date::parse($reminded[$userId]) : null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['balance'] <=> $a['balance']);

        return collect($rows);
    }

    /**
     * What is owed in each age bracket, added up over a list of debtors.
     *
     * @param  Collection<int, Debtor>  $debtors
     * @return list<float>
     */
    public function agingTotals(Collection $debtors): array
    {
        return array_map(
            fn (int $bracket): float => round((float) $debtors->sum(fn (array $row): float => $row['aging'][$bracket]), 2),
            array_keys($this->emptyAging()),
        );
    }

    /**
     * Text one customer about what they owe.
     *
     * @throws InvalidArgumentException when there is nothing to say, nobody to say it to, or it was said too recently
     * @throws SmsException when the text could not be sent (it is recorded as failed)
     */
    public function remind(User $customer, ?int $by = null, bool $force = false): PaymentReminder
    {
        $mobile = trim((string) $customer->mobile);
        $summary = $this->summary($customer);

        if ($mobile === '') {
            throw new InvalidArgumentException(__('order::strings.Customer Has No Mobile'));
        }

        if ($summary['balance'] <= 0) {
            throw new InvalidArgumentException(__('order::strings.Nothing Owed'));
        }

        $days = (int) config('payments.reminder_min_days');
        $last = PaymentReminder::query()->where('user_id', $customer->getKey())->where('status', 'sent')->latest('id')->first();

        if (! $force && $last !== null && $last->created_at->addDays($days)->isFuture()) {
            throw new InvalidArgumentException(__('order::strings.Reminded Recently', ['days' => $days]));
        }

        // Two clicks at once must not text twice.
        if (! Cache::add("payment-reminder:{$customer->getKey()}", true, 30)) {
            throw new InvalidArgumentException(__('order::strings.Reminded Recently', ['days' => $days]));
        }

        $record = ['user_id' => $customer->getKey(), 'sent_by' => $by, 'amount' => $summary['balance'], 'orders_count' => $summary['open_orders']];

        try {
            Sms::send($mobile, __('order::strings.Reminder Sms', [
                'shop' => config('app.name'),
                'name' => $customer->fullName(),
                'amount' => \App\Helpers\Utils::pDigits(\App\Helpers\Utils::formatQuantity($summary['balance'], 0, ',')),
                'count' => \App\Helpers\Utils::pDigits((string) $summary['open_orders']),
            ]));
        } catch (SmsException $e) {
            PaymentReminder::query()->create($record + ['status' => 'failed', 'error' => $e->getMessage()]);
            Cache::forget("payment-reminder:{$customer->getKey()}");

            throw $e;
        }

        return PaymentReminder::query()->create($record + ['status' => 'sent']);
    }

    /**
     * Remind every customer who owes, skipping those reminded recently or without a mobile.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function remindAll(?int $by = null): array
    {
        $sent = $skipped = $failed = 0;

        foreach ($this->debtors() as $row) {
            try {
                $this->remind($row['user'], $by);
                $sent++;
            } catch (InvalidArgumentException) {
                $skipped++;
            } catch (SmsException) {
                $failed++;
            }
        }

        return compact('sent', 'skipped', 'failed');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    private function liveOrders()
    {
        return Order::query()
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->whereHas('user', fn ($user) => $user->where('type', UserType::Customer))
            ->withSum('items', 'total')
            ->withSum('payments', 'amount');
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function oldest(Collection $orders): ?CarbonInterface
    {
        $at = $orders->sortBy('created_at')->first()?->getAttribute('created_at');

        return $at instanceof CarbonInterface ? $at : null;
    }

    /**
     * What these orders owe, split by how old each order is.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<float>
     */
    private function aging(Collection $orders): array
    {
        $brackets = $this->emptyAging();

        foreach ($orders as $order) {
            $owed = $this->owed($order);

            if ($owed <= 0) {
                continue;
            }

            $days = (int) $order->created_at->diffInDays(now());
            $bracket = count(array_filter(self::AGING_DAYS, fn (int $limit): bool => $days > $limit));
            $brackets[$bracket] += $owed;
        }

        return array_values(array_map(fn (float $amount): float => round($amount, 2), $brackets));
    }

    /**
     * @return list<float>
     */
    private function emptyAging(): array
    {
        return array_fill(0, count(self::AGING_DAYS) + 1, 0.0);
    }

    private function owed(Order $order): float
    {
        return round((float) ($order->items_sum_total ?? 0) - (float) ($order->payments_sum_amount ?? 0), 2);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{total: float, paid: float, balance: float, open_orders: int}
     */
    private function sum(Collection $orders): array
    {
        $total = round((float) $orders->sum(fn (Order $order): float => (float) ($order->items_sum_total ?? 0)), 2);
        $paid = round((float) $orders->sum(fn (Order $order): float => (float) ($order->payments_sum_amount ?? 0)), 2);

        return [
            'total' => $total,
            'paid' => $paid,
            'balance' => round($total - $paid, 2),
            'open_orders' => $orders->filter(fn (Order $order): bool => $this->owed($order) > 0)->count(),
        ];
    }
}
