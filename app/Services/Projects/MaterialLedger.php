<?php

namespace App\Services\Projects;

use App\Models\ProjectMaterial;
use App\Models\ProjectMaterialMovement;
use App\Support\Money\MoneyMath;
use App\Support\Notifier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project materials ledger.
 *
 *  - ordered   : a purchase order — committed cost, not stock and not cost.
 *  - received  : into site stock at a unit cost. Buying is NOT a project cost.
 *  - consumed  : used on the borehole → recognised cost at weighted-average cost.
 *  - wasted    : damaged / lost → recognised cost (and reported as wastage).
 *  - returned  : back to the supplier or transferred out → leaves stock, no cost.
 *
 * Stock on hand = received − consumed − returned − wasted, and can never go negative.
 */
class MaterialLedger
{
    /**
     * @return array{ordered: string, received: string, consumed: string, returned: string, wasted: string,
     *     on_hand: string, avg_cost: string, purchased_value: string, cost: string, wastage_cost: string,
     *     stock_value: string, committed: string, low_stock: bool}
     */
    public function summary(ProjectMaterial $material): array
    {
        $qty = array_fill_keys(array_keys(ProjectMaterialMovement::TYPES), BigDecimal::zero());
        $receivedValue = BigDecimal::zero();
        $orderedValue = BigDecimal::zero();

        $movements = $material->relationLoaded('movements') ? $material->movements : $material->movements()->get();

        foreach ($movements as $m) {
            $q = BigDecimal::of((string) $m->quantity);
            $qty[$m->type] = $qty[$m->type]->plus($q);

            if ($m->type === 'received') {
                $receivedValue = $receivedValue->plus($q->multipliedBy((string) ($m->unit_cost ?? '0')));
            } elseif ($m->type === 'ordered') {
                $orderedValue = $orderedValue->plus($q->multipliedBy((string) ($m->unit_cost ?? '0')));
            }
        }

        // Weighted-average unit cost of everything received.
        $avg = $qty['received']->isZero()
            ? BigDecimal::zero()
            : $receivedValue->dividedBy($qty['received'], 4, RoundingMode::HalfUp);
        $orderedAvg = $qty['ordered']->isZero()
            ? $avg
            : $orderedValue->dividedBy($qty['ordered'], 4, RoundingMode::HalfUp);

        $onHand = $qty['received']->minus($qty['consumed'])->minus($qty['returned'])->minus($qty['wasted']);
        $outstandingOrder = $qty['ordered']->minus($qty['received']);
        if ($outstandingOrder->isNegative()) {
            $outstandingOrder = BigDecimal::zero();
        }

        $money = fn (BigDecimal $v) => MoneyMath::str((string) $v->toScale(2, RoundingMode::HalfUp));
        $reorder = BigDecimal::of((string) $material->reorder_level);

        return [
            'ordered' => (string) $qty['ordered'],
            'received' => (string) $qty['received'],
            'consumed' => (string) $qty['consumed'],
            'returned' => (string) $qty['returned'],
            'wasted' => (string) $qty['wasted'],
            'on_hand' => (string) $onHand,
            'avg_cost' => $money($avg),
            'purchased_value' => $money($receivedValue),
            'cost' => $money($qty['consumed']->plus($qty['wasted'])->multipliedBy($avg)),
            'wastage_cost' => $money($qty['wasted']->multipliedBy($avg)),
            'stock_value' => $money($onHand->multipliedBy($avg)),
            'committed' => $money($outstandingOrder->multipliedBy($orderedAvg)),
            'low_stock' => $reorder->isPositive() && $onHand->isLessThanOrEqualTo($reorder),
        ];
    }

    /**
     * Record a movement. Stock is locked while checking so two people issuing at
     * the same time can never take the same stock twice.
     *
     * @param  array{type: string, quantity: string, unit_cost?: ?string, supplier_id?: ?int, reference?: ?string, movement_date: string, notes?: ?string, attachment_path?: ?string}  $data
     */
    public function record(ProjectMaterial $material, array $data): ProjectMaterialMovement
    {
        $movement = DB::transaction(function () use ($material, $data) {
            ProjectMaterial::query()->whereKey($material->id)->lockForUpdate()->first();

            $type = $data['type'];
            $quantity = BigDecimal::of((string) $data['quantity']);

            if (! $quantity->isPositive()) {
                throw ValidationException::withMessages(['quantity' => 'Quantity must be more than zero.']);
            }

            if ($type === 'received' && ($data['unit_cost'] ?? null) === null) {
                throw ValidationException::withMessages(['unit_cost' => 'Enter the unit cost of what was received.']);
            }

            if (in_array($type, ['consumed', 'returned', 'wasted'], true)) {
                $onHand = BigDecimal::of($this->summary($material->fresh())['on_hand']);
                if ($quantity->isGreaterThan($onHand)) {
                    throw ValidationException::withMessages([
                        'quantity' => "Only {$onHand} {$material->unit} in stock — you cannot {$this->verb($type)} more than that.",
                    ]);
                }
            }

            $movement = $material->movements()->create([
                'type' => $type,
                'quantity' => (string) $quantity,
                'unit_cost' => in_array($type, ['received', 'ordered'], true) ? ($data['unit_cost'] ?? null) : null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'movement_date' => $data['movement_date'],
                'recorded_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
            ]);

            $material->project->log('material', ProjectMaterialMovement::TYPES[$type].": {$quantity} {$material->unit} of {$material->name}", [
                'material_id' => $material->id, 'movement_id' => $movement->id, 'type' => $type, 'quantity' => (string) $quantity,
            ]);

            return $movement;
        });

        $summary = $this->summary($material->fresh());
        if ($summary['low_stock'] && in_array($movement->type, ['consumed', 'returned', 'wasted'], true)) {
            $project = $material->project;
            Notifier::send(
                ProjectAlerts::stakeholders($project, 'projects.materials.manage'),
                "Low stock: {$material->name}",
                "{$project->reference} — {$summary['on_hand']} {$material->unit} left (reorder at {$material->reorder_level}).",
                route('projects.show', ['project' => $project, 'tab' => 'materials']),
                'cube',
                'project_low_stock',
            );
        }

        app(ProjectAlerts::class)->checkBudget($material->project);

        return $movement;
    }

    private function verb(string $type): string
    {
        return ['consumed' => 'use', 'returned' => 'return', 'wasted' => 'write off'][$type];
    }
}
