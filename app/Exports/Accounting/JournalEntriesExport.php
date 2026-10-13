<?php

namespace App\Exports\Accounting;

use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** One row per journal line — the format accountants import elsewhere. */
class JournalEntriesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected int $vendorId,
        protected string $from,
        protected string $to,
        protected string $search = '',
        protected string $source = '',
    ) {}

    public function title(): string
    {
        return 'Journal Entries';
    }

    public function query(): Builder
    {
        return JournalLine::query()
            ->with(['account', 'entry'])
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->where('je.vendor_id', $this->vendorId)
            ->whereNotNull('je.posted_at')
            ->whereDate('je.date', '>=', $this->from)
            ->whereDate('je.date', '<=', $this->to)
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('je.reference', 'like', "%{$this->search}%")
                ->orWhere('je.memo', 'like', "%{$this->search}%")))
            ->when($this->source === 'manual', fn ($q) => $q->whereNull('je.source_type'))
            ->when($this->source && $this->source !== 'manual', fn ($q) => $q->where('je.source_type', $this->source))
            ->orderBy('je.date')->orderBy('je.id')->orderBy('journal_lines.id')
            ->select('journal_lines.*');
    }

    public function headings(): array
    {
        return ['Entry #', 'Date', 'Reference', 'Memo', 'Account code', 'Account name', 'Debit', 'Credit', 'Line description'];
    }

    /** @param JournalLine $line */
    public function map($line): array
    {
        return [
            $line->journal_entry_id,
            $line->entry?->date?->format('Y-m-d'),
            $line->entry?->reference,
            $line->entry?->memo,
            $line->account?->code,
            $line->account?->name,
            (string) $line->debit,
            (string) $line->credit,
            $line->description,
        ];
    }
}
