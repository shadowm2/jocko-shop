<?php

namespace Modules\Order\Livewire\Forms;

use App\Helpers\Utils;
use App\Rules\JalaliDate;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Modules\Order\Enums\ChequeStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\Payment;
use Modules\Order\Services\PaymentService;
use Morilog\Jalali\Jalalian;

class PaymentForm extends Form
{
    public ?Payment $payment = null;

    public string $order = '';

    public string $amount = '';

    public string $method = PaymentMethod::CASH->value;

    public string $paid_at = '';

    public string $paid_at_gregorian = '';

    public ?string $description = null;

    public ?string $cheque_number = null;

    public ?string $cheque_bank = null;

    /** The cheque's due day (Jalali, as typed) and the same day for the date picker. */
    public ?string $cheque_due_at = null;

    public string $cheque_due_at_gregorian = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'order' => 'required|string|exists:orders,slug',
            'amount' => ['required', 'numeric', 'gt:0', $this->withinBalance(...)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'string', new JalaliDate],
            'description' => 'nullable|string',
            'cheque_number' => ['nullable', 'string', 'max:40', Rule::requiredIf($this->needsChequeDetails(...))],
            'cheque_bank' => ['nullable', 'string', 'max:60'],
            'cheque_due_at' => [Rule::requiredIf($this->needsChequeDetails(...)), 'nullable', new JalaliDate],
        ];
    }

    public function isCheque(): bool
    {
        return $this->method === PaymentMethod::CHEQUE->value;
    }

    /**
     * A cheque is tracked by its number and due date. One recorded before cheques were
     * tracked has neither, and may still be edited without them.
     */
    private function needsChequeDetails(): bool
    {
        return $this->isCheque() && ! ($this->payment !== null && $this->payment->cheque_status === null);
    }

    /**
     * A payment can't exceed what the order still owes, not counting this
     * payment itself when it is being edited.
     */
    private function withinBalance(string $attribute, mixed $value, Closure $fail): void
    {
        $order = Order::where('slug', $this->order)->first();
        if (! $order) {
            return;
        }

        $payments = $order->payments();
        if ($this->payment) {
            $payments->whereKeyNot($this->payment->id);
        }
        $paid = $payments->sum('amount');
        $balance = round((float) $order->items()->sum('total') - (float) $paid, 2);

        if (round((float) $value, 2) > $balance) {
            $fail(__('order::messages.Payment Exceeds Balance', ['balance' => Utils::pDigits(Utils::formatQuantity($balance, 2, ','))]));
        }
    }

    /**
     * New payments are dated today unless the user picks another day.
     */
    public function setDefaults(): void
    {
        $this->paid_at = Utils::pDigits(Jalalian::now()->format('Y/m/d')) ?? '';
        $this->paid_at_gregorian = now()->format('Y/m/d');
    }

    public function setPayment(Payment $payment): void
    {
        $this->payment = $payment;
        $this->order = $payment->order->slug;
        $this->amount = Utils::formatQuantity($payment->amount, 2, '');
        $this->method = $payment->method->value;
        $this->paid_at = Utils::pDigits($payment->paid_at_jalali->format('Y/m/d')) ?? '';
        $this->paid_at_gregorian = $payment->paid_at->format('Y/m/d');
        $this->description = $payment->description;
        $this->cheque_number = $payment->cheque_number;
        $this->cheque_bank = $payment->cheque_bank;
        $this->cheque_due_at = $payment->cheque_due_at === null ? null : Utils::pDigits(Jalalian::fromDateTime($payment->cheque_due_at)->format('Y/m/d'));
        $this->cheque_due_at_gregorian = $payment->cheque_due_at?->format('Y/m/d') ?? '';
    }

    public function save(): Payment
    {
        $this->amount = (string) $this->number($this->amount);

        $data = $this->validate();
        $data['paid_at'] = Jalalian::fromFormat('Y/m/d', Utils::eDigits($data['paid_at']))->toCarbon();

        if ($this->isCheque() && filled($data['cheque_due_at'] ?? null)) {
            $data['cheque_due_at'] = Jalalian::fromFormat('Y/m/d', Utils::eDigits(str_replace('-', '/', $data['cheque_due_at'])))->toCarbon()->toDateString();
            // A new cheque waits to be cashed; an edited one keeps the state the shop gave it.
            $data['cheque_status'] = $this->currentChequeStatus()->value;
        } else {
            $data = [...$data, 'cheque_number' => null, 'cheque_bank' => null, 'cheque_due_at' => null, 'cheque_status' => null];
        }

        $service = resolve(PaymentService::class);

        if ($this->payment) {
            // A payment stays with the order it was recorded for.
            unset($data['order']);
            $service->update($this->payment, $data);

            return $this->payment;
        }

        return $service->create($data);
    }

    private function currentChequeStatus(): ChequeStatus
    {
        $current = $this->payment instanceof Payment ? $this->payment->cheque_status : null;

        return $current ?? ChequeStatus::PENDING;
    }

    /**
     * Accept Persian digits and separators as typed.
     */
    protected function number(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['٫', '٬', ','], ['.', '', ''], Utils::eDigits($value));
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'order' => __('order::attributes.Order Number'),
            'amount' => __('order::attributes.Amount'),
            'method' => __('order::attributes.Payment Method'),
            'paid_at' => __('order::attributes.Paid At'),
            'cheque_number' => __('order::attributes.Cheque Number'),
            'cheque_bank' => __('order::attributes.Cheque Bank'),
            'cheque_due_at' => __('order::attributes.Cheque Due At'),
        ];
    }
}
