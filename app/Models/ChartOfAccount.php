<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NormalSide;
use App\Models\Concerns\BelongsToVendor;
use App\Support\Money\MoneyMath;
use Brick\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $vendor_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property NormalSide $normal_side
 * @property bool $is_system
 * @property bool $is_active
 */
class ChartOfAccount extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'vendor_id', 'code', 'name', 'type', 'normal_side',
        'parent_id', 'is_active', 'is_system', 'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'normal_side' => NormalSide::class,
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeByType(Builder $q, AccountType|string $type): Builder
    {
        return $q->where('type', $type instanceof AccountType ? $type->value : $type);
    }

    /**
     * Balance on posted entries up to and including $date, expressed on the
     * account's NORMAL side (a positive number means "a normal balance":
     * debit-heavy for assets/expenses, credit-heavy for the rest).
     */
    public function balanceAsOf(CarbonInterface $date): Money
    {
        $row = JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $this->id)
            ->whereNotNull('je.posted_at')
            ->whereDate('je.date', '<=', $date->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as dr, COALESCE(SUM(journal_lines.credit),0) as cr')
            ->first();

        $currency = $this->vendor?->currency ?? config('accounting.currency', 'KES');
        $dr = MoneyMath::of($row->dr ?? 0, $currency);
        $cr = MoneyMath::of($row->cr ?? 0, $currency);

        return $this->normal_side === NormalSide::Debit ? $dr->minus($cr) : $cr->minus($dr);
    }

    public function label(): string
    {
        return "{$this->code} · {$this->name}";
    }
}
