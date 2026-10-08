<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Laporan harian')]
class Reports extends Component
{
    public string $from = '';

    public string $to = '';

    #[Locked]
    public string $appliedFrom = '';

    #[Locked]
    public string $appliedTo = '';

    public function mount(): void
    {
        $this->authorizeRead();
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        $this->apply();
    }

    private function authorizeRead(): void
    {
        $actor = User::find(auth()->id());
        Gate::allowIf($actor && $actor->role->managesWorkshop());
    }

    /** @return array<string, list<string>> */
    public static function dateRules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']];
    }

    public function apply(): void
    {
        $this->authorizeRead();
        $this->validate(self::dateRules());
        $this->appliedFrom = $this->from;
        $this->appliedTo = $this->to;
    }

    /**
     * Cash flow: gross on paid_at, reversal on reversed_at; active is current unreversed receipts.
     *
     * @return Collection<int, array{date: string, gross: string, reversed: string, net: string, active: string, services: int, stock_in: int, stock_out: int}>
     */
    public static function daily(string $from, string $to): Collection
    {
        Validator::make(compact('from', 'to'), self::dateRules())->validate();
        $days = [];
        $blank = fn (string $date): array => ['date' => $date, 'gross' => '0.00', 'reversed' => '0.00', 'net' => '0.00', 'active' => '0.00', 'services' => 0, 'stock_in' => 0, 'stock_out' => 0];
        foreach (Payment::query()->select('id', 'amount', 'paid_at', 'reversed_at')
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])->cursor() as $payment) {
            $date = substr($payment->getRawOriginal('paid_at'), 0, 10);
            $days[$date] ??= $blank($date);
            $days[$date]['gross'] = bcadd($days[$date]['gross'], self::amount($payment), 2);
            if ($payment->reversed_at === null) {
                $days[$date]['active'] = bcadd($days[$date]['active'], self::amount($payment), 2);
            }
        }
        foreach (Payment::query()->select('id', 'amount', 'reversed_at')
            ->whereBetween('reversed_at', [$from.' 00:00:00', $to.' 23:59:59'])->cursor() as $payment) {
            $date = substr($payment->getRawOriginal('reversed_at'), 0, 10);
            $days[$date] ??= $blank($date);
            $days[$date]['reversed'] = bcadd($days[$date]['reversed'], self::amount($payment), 2);
        }
        foreach (ServiceOrder::query()->selectRaw('DATE(completed_at) AS day, COUNT(*) AS total')
            ->whereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupByRaw('DATE(completed_at)')->get() as $service) {
            $date = (string) $service->getAttribute('day');
            $days[$date] ??= $blank($date);
            $days[$date]['services'] = (int) $service->getAttribute('total');
        }
        foreach (StockMovement::query()->select('id', 'quantity', 'created_at')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->cursor() as $movement) {
            $date = $movement->created_at->format('Y-m-d');
            $days[$date] ??= $blank($date);
            $key = $movement->quantity > 0 ? 'stock_in' : 'stock_out';
            $days[$date][$key] += abs($movement->quantity);
        }
        ksort($days);
        foreach ($days as &$day) {
            $day['net'] = bcsub($day['gross'], $day['reversed'], 2);
        }
        unset($day);

        return collect(array_values($days));
    }

    /** @return numeric-string */
    private static function amount(Payment $payment): string
    {
        $amount = $payment->getAttribute('amount');
        if (! is_string($amount) || ! is_numeric($amount)) {
            throw new \LogicException('Payment amount must be a fixed decimal string.');
        }

        return $amount;
    }

    public function render(): View
    {
        $this->authorizeRead();
        $days = self::daily($this->appliedFrom, $this->appliedTo);
        $totals = ['gross' => '0.00', 'reversed' => '0.00', 'net' => '0.00', 'active' => '0.00'];
        foreach ($days as $day) {
            foreach (array_keys($totals) as $key) {
                if (! is_numeric($day[$key])) {
                    throw new \LogicException('Report total must be decimal.');
                }
                $totals[$key] = bcadd($totals[$key], $day[$key], 2);
            }
        }

        return view('livewire.reports', compact('days', 'totals'));
    }
}
