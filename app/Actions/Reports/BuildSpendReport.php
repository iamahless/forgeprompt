<?php

namespace App\Actions\Reports;

use App\Billing\Usd;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\Client;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\Rates;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * @phpstan-type SpendRow array{day: string, user_id: int|null, generations: int, charged_nanos: int, provider_cost_nanos: int}
 */
class BuildSpendReport
{
    private const int DAYS = 30;

    private const int LATEST_GENERATIONS = 50;

    private const array NO_SPEND = [
        'generations' => 0,
        'charged_nanos' => 0,
        'provider_cost_nanos' => 0,
        'profit_nanos' => 0,
        'users' => [],
    ];

    public function __construct(private Client $pixelforge) {}

    /**
     * Spend, provider cost and profit per day and per user, next to what Pixelforge reports for the same days.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $to = today('UTC');
        $from = $to->subDays(self::DAYS - 1);

        $rows = $this->spend($from, $to);
        $users = User::query()->findMany($rows->pluck('user_id')->filter()->unique())->keyBy('id');
        $usage = $this->pixelforgeUsage($from, $to);

        $days = $rows->groupBy('day')->map(fn (Collection $dayRows): array => [
            ...$this->totals($dayRows),
            'users' => $dayRows->map(fn (array $row): array => [
                'user' => $users->get($row['user_id'])?->only(['id', 'name', 'email']),
                ...$this->totals(collect([$row])),
            ])->sortByDesc('charged_nanos')->values(),
        ]);

        // A day only Pixelforge knows about is exactly the mismatch this report exists to catch.
        $dates = $days->keys()->merge($usage?->keys())->unique()->sortDesc()->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $dates->map(fn (int|string $date): array => [
                'date' => $date,
                ...($days->get($date) ?? self::NO_SPEND),
                'pixelforge' => $usage?->get($date),
            ]),
            'users' => $rows->groupBy('user_id')->map(fn (Collection $userRows, int|string $userId): array => [
                'user' => $users->get($userId)?->only(['id', 'name', 'email']),
                ...$this->totals($userRows),
            ])->sortByDesc('charged_nanos')->values(),
            'totals' => $this->totals($rows),
            'generations' => $this->latestGenerations($from, $to),
            'pixelforgeTotal' => $usage === null ? null : [
                'generations' => $usage->sum('generations'),
                'cost_nanos' => $usage->sum('cost_nanos'),
            ],
        ];
    }

    /**
     * Succeeded generations grouped by the UTC day they completed, which is the day Pixelforge bills them, and by user.
     *
     * @return Collection<int, SpendRow>
     */
    private function spend(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(completed_at) as day, user_id, COUNT(*) as generations')
            ->selectRaw('SUM(price_nanos) as charged_nanos, SUM(provider_cost_nanos) as provider_cost_nanos')
            ->groupByRaw('DATE(completed_at), user_id')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'day' => (string) $row->day,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'generations' => (int) $row->generations,
                'charged_nanos' => (int) $row->charged_nanos,
                'provider_cost_nanos' => (int) $row->provider_cost_nanos,
            ]);
    }

    /**
     * The newest generations Pixelforge has a record of, so any single one can be checked against its dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latestGenerations(CarbonInterface $from, CarbonInterface $to): array
    {
        return Generation::query()
            ->with('user:id,name,email')
            ->whereNotNull('provider_id')
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->latest('completed_at')
            ->limit(self::LATEST_GENERATIONS)
            ->get()
            ->map(fn (Generation $generation): array => [
                'provider_id' => $generation->provider_id,
                'status' => $generation->status,
                'user' => $generation->user?->only(['id', 'name', 'email']),
                'completed_at' => $generation->completed_at,
                'usage' => $generation->status === GenerationStatus::Succeeded ? $generation->only(Rates::TOKEN_TYPES) : null,
                'provider_cost_nanos' => $generation->provider_cost_nanos,
                'charged_nanos' => $generation->price_nanos,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, SpendRow>  $rows
     * @return array{generations: int, charged_nanos: int, provider_cost_nanos: int, profit_nanos: int}
     */
    private function totals(Collection $rows): array
    {
        return [
            'generations' => $rows->sum('generations'),
            'charged_nanos' => $rows->sum('charged_nanos'),
            'provider_cost_nanos' => $rows->sum('provider_cost_nanos'),
            'profit_nanos' => $rows->sum('charged_nanos') - $rows->sum('provider_cost_nanos'),
        ];
    }

    /**
     * What Pixelforge says it billed this API key on each day it billed anything, or null if it can't be reached.
     *
     * @return Collection<array-key, array{generations: int, cost_nanos: int}>|null
     */
    private function pixelforgeUsage(CarbonInterface $from, CarbonInterface $to): ?Collection
    {
        try {
            $usage = $this->pixelforge->usage($from, $to);
        } catch (PixelforgeException) {
            return null;
        }

        $days = [];

        foreach ($usage['data'] as $day) {
            if ($day['generations'] > 0) {
                $days[$day['date']] = [
                    'generations' => (int) $day['generations'],
                    'cost_nanos' => Usd::toNanos($day['cost_usd']),
                ];
            }
        }

        return collect($days);
    }
}
