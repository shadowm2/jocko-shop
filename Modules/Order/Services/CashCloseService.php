<?php

namespace Modules\Order\Services;

use Carbon\CarbonInterface;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\CashClose;
use Modules\Order\Models\Payment;

/**
 * Closing the till: what a day's payments add up to, and the cash counted against it.
 */
class CashCloseService
{
    /**
     * What was collected on a day, per method (a refund is a negative payment, so it nets
     * out).
     *
     * @return array{methods: array<string, array{total: float, count: int}>, total: float, count: int, cash: float}
     */
    public function totalsFor(CarbonInterface $day): array
    {
        $rows = Payment::query()
            ->whereDate('paid_at', $day->toDateString())
            ->selectRaw('method, sum(amount) as total, count(*) as payments')
            ->groupBy('method')
            ->get();

        $methods = [];
        foreach (PaymentMethod::cases() as $method) {
            $row = $rows->first(fn (Payment $payment): bool => $payment->method === $method);
            $methods[$method->value] = ['total' => round((float) $row?->getAttribute('total'), 2), 'count' => (int) $row?->getAttribute('payments')];
        }

        return [
            'methods' => $methods,
            'total' => round(array_sum(array_column($methods, 'total')), 2),
            'count' => array_sum(array_column($methods, 'count')),
            'cash' => $methods[PaymentMethod::CASH->value]['total'],
        ];
    }

    /**
     * Close the day, replacing an earlier close of the same day.
     */
    public function close(CarbonInterface $day, float $counted, ?string $note, ?int $closedBy): CashClose
    {
        $totals = $this->totalsFor($day);

        return CashClose::query()->updateOrCreate(
            ['business_date' => $day->toDateString()],
            [
                'totals' => array_map(fn (array $method): float => $method['total'], $totals['methods']),
                'expected_cash' => $totals['cash'],
                'counted_cash' => round($counted, 2),
                'difference' => round($counted - $totals['cash'], 2),
                'note' => filled($note) ? $note : null,
                'closed_by' => $closedBy,
            ],
        );
    }
}
