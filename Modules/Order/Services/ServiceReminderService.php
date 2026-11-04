<?php

namespace Modules\Order\Services;

use App\Helpers\Utils;
use Illuminate\Database\Eloquent\Collection;
use Modules\Dashboard\Exceptions\SmsException;
use Modules\Dashboard\Facades\Sms;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Morilog\Jalali\Jalalian;

/**
 * Texts customers whose car is due back for service, going by the date the shop
 * entered on the car's latest order.
 */
class ServiceReminderService
{
    /**
     * Orders whose service date is near (or only just passed), not yet reminded, and
     * not superseded by a later visit of the same car.
     *
     * @return Collection<int, Order>
     */
    public function due(): Collection
    {
        $before = (int) config('service.remind_days_before');

        if ($before < 0) {
            return new Collection;
        }

        return Order::query()
            ->whereNotNull('next_service_at')
            ->whereNull('service_reminded_at')
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->whereDate('next_service_at', '<=', today()->addDays($before))
            ->whereDate('next_service_at', '>=', today()->subDays((int) config('service.overdue_days')))
            ->whereNotExists(fn ($later) => $later->from('orders as later')
                ->whereColumn('later.user_car_id', 'orders.user_car_id')
                ->whereColumn('later.id', '>', 'orders.id')
                ->where('later.status', '!=', OrderStatus::CANCELLED->value)
                ->whereNull('later.deleted_at'))
            ->with(['user', 'userCar.car'])
            ->orderBy('next_service_at')
            ->get();
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function remindDue(): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($this->due() as $order) {
            $customer = $order->user;
            $car = $order->userCar?->car;
            $dueAt = $order->next_service_at;
            $mobile = trim((string) $customer?->mobile);

            if ($customer === null || $car === null || $dueAt === null || $mobile === '') {
                $result['skipped']++;

                continue;
            }

            try {
                Sms::send($mobile, __('order::strings.Service Reminder Sms', [
                    'shop' => config('app.name'),
                    'name' => $customer->fullName(),
                    'car' => $car->name,
                    'date' => Utils::pDigits(Jalalian::fromDateTime($dueAt)->format('Y/m/d')),
                ]));
            } catch (SmsException) {
                // Left unreminded, so the next run tries again while the date is still in range.
                $result['failed']++;

                continue;
            }

            $order->forceFill(['service_reminded_at' => now()])->save();
            $result['sent']++;
        }

        return $result;
    }
}
