<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Pharmacy\Application\Support\PharmacyProfit;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use Illuminate\Support\Carbon;

final class PharmacyProfitQuery
{
    public function between(Carbon $from): float
    {
        $profit = 0.0;

        foreach ($this->sales($from) as $sale) {
            foreach ($sale->items as $item) {
                $profit += PharmacyProfit::onSold(
                    (float) $item->line_total,
                    (float) $sale->subtotal,
                    (float) $sale->total,
                    (float) $item->cost_total,
                );
            }
        }

        foreach ($this->returns($from) as $return) {
            foreach ($return->items as $line) {
                $item = $line->saleItem;
                $sale = $item?->sale;
                if ($item === null || $sale === null) {
                    continue;
                }

                $profit -= PharmacyProfit::onReturned(
                    (float) $item->line_total,
                    (float) $sale->subtotal,
                    (float) $sale->total,
                    (float) $item->cost_total,
                    (float) $item->quantity,
                    (float) $line->quantity,
                );
            }
        }

        return round($profit, 2);
    }

    /**
     * @return array<int, array{name: string, quantity: float, net: float, cost: float, profit: float}>
     */
    public function items(Carbon $from): array
    {
        /** @var array<string, array{name: string, quantity: float, net: float, cost: float}> $rows */
        $rows = [];

        $touch = function (string $key, string $name) use (&$rows): void {
            if (! isset($rows[$key])) {
                $rows[$key] = ['name' => $name, 'quantity' => 0.0, 'net' => 0.0, 'cost' => 0.0];
            }
        };

        foreach ($this->sales($from) as $sale) {
            foreach ($sale->items as $item) {
                $key = (string) ($item->medication_id ?? $item->name);
                $touch($key, (string) $item->name);
                $rows[$key]['quantity'] += (float) $item->quantity;
                $rows[$key]['net'] += PharmacyProfit::netOf(
                    (float) $item->line_total,
                    (float) $sale->subtotal,
                    (float) $sale->total,
                );
                $rows[$key]['cost'] += (float) $item->cost_total;
            }
        }

        foreach ($this->returns($from) as $return) {
            foreach ($return->items as $line) {
                $item = $line->saleItem;
                $sale = $item?->sale;
                if ($item === null || $sale === null || (float) $item->quantity <= 0) {
                    continue;
                }

                $share = min((float) $line->quantity / (float) $item->quantity, 1);
                $key = (string) ($item->medication_id ?? $item->name);
                $touch($key, (string) $item->name);
                $rows[$key]['quantity'] -= (float) $line->quantity;
                $rows[$key]['net'] -= PharmacyProfit::netOf(
                    (float) $item->line_total,
                    (float) $sale->subtotal,
                    (float) $sale->total,
                ) * $share;
                $rows[$key]['cost'] -= (float) $item->cost_total * $share;
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $quantity = round($row['quantity'], 2);
            if ($quantity === 0.0 && round($row['net'], 2) === 0.0) {
                continue;
            }

            $net = round($row['net'], 2);
            $cost = round($row['cost'], 2);
            $items[] = [
                'name' => $row['name'],
                'quantity' => $quantity,
                'net' => $net,
                'cost' => $cost,
                'profit' => round($net - $cost, 2),
            ];
        }

        usort($items, fn (array $left, array $right): int => $right['profit'] <=> $left['profit']);

        return array_slice($items, 0, 20);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PharmacySaleModel>
     */
    private function sales(Carbon $from)
    {
        return PharmacySaleModel::query()
            ->forCurrentHospital()
            ->where('sold_at', '>=', $from)
            ->with('items')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PharmacyReturnModel>
     */
    private function returns(Carbon $from)
    {
        return PharmacyReturnModel::query()
            ->forCurrentHospital()
            ->where('returned_at', '>=', $from)
            ->with(['items.saleItem.sale'])
            ->get();
    }
};
