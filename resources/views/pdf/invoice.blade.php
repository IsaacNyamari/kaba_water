<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1e293b;
            margin: 0;
            padding: 40px;
        }

        .header {
            width: 100%;
            margin-bottom: 30px;
        }

        .header td {
            vertical-align: top;
        }

        .brand-name {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0 0 6px;
        }

        .brand-meta {
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 30px;
            letter-spacing: 3px;
            margin: 0;
            color: #1e3a8a;
            text-transform: uppercase;
        }

        .doc-title .number {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 14px;
            color: #475569;
            margin-top: 4px;
        }

        .doc-title .badge {
            display: inline-block;
            padding: 4px 12px;
            margin-top: 8px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-draft {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-sent {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-paid {
            background: #d1fae5;
            color: #047857;
        }

        .badge-overdue {
            background: #fee2e2;
            color: #b91c1c;
        }

        .parties {
            width: 100%;
            margin-bottom: 30px;
        }

        .parties td {
            vertical-align: top;
            width: 50%;
            padding-right: 20px;
        }

        .parties .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .parties .name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .parties .line {
            font-size: 11px;
            color: #475569;
            line-height: 1.6;
        }

        .meta-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .meta-bar table {
            width: 100%;
        }

        .meta-bar td {
            width: 33.33%;
        }

        .meta-bar .k {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .meta-bar .v {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 3px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        table.items th {
            text-align: left;
            background: #1e3a8a;
            color: #ffffff;
            padding: 10px 12px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        table.items th.right,
        table.items td.right {
            text-align: right;
        }

        table.items td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }

        table.items td.desc {
            color: #0f172a;
        }

        table.items td.mono {
            font-family: DejaVu Sans Mono, monospace;
            color: #475569;
        }

        .totals {
            width: 45%;
            margin-left: 55%;
        }

        .totals table {
            width: 100%;
        }

        .totals td {
            padding: 6px 0;
            font-size: 12px;
        }

        .totals td:last-child {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        .totals .grand {
            border-top: 2px solid #1e3a8a;
            padding-top: 10px;
            font-size: 15px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .notes {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 40px;
            right: 40px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <table class="header">
        <tr>
            <td style="width:60%">
                @php
                    // Embedded as data so dompdf never has to fetch a URL.
                    $logoFile = $invoice->vendor->logo_path ? storage_path('app/public/' . $invoice->vendor->logo_path) : null;
                    $logoData = $logoFile && is_readable($logoFile)
                        ? 'data:' . mime_content_type($logoFile) . ';base64,' . base64_encode(file_get_contents($logoFile))
                        : null;
                @endphp
                @if ($logoData)
                    <img src="{{ $logoData }}" alt="" style="max-height:56px; max-width:220px; margin-bottom:8px;">
                @endif
                <div class="brand-name">{{ $invoice->vendor->name }}</div>
                <div class="brand-meta">
                    {{ $invoice->vendor->email }}<br>
                    @if ($invoice->vendor->phone)
                        {{ $invoice->vendor->phone }}<br>
                    @endif
                    {{ $invoice->vendor->currency }} · Powered by {{ config('app.name') }}
                </div>
            </td>
            <td class="doc-title">
                <h1>Invoice</h1>
                <div class="number">{{ $invoice->number }}</div>
                <div>
                    <span class="badge badge-{{ $invoice->status->value }}">
                        {{ $invoice->status->label() }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Parties --}}
    <table class="parties">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <div class="name">{{ $invoice->client->name }}</div>
                <div class="line">
                    Account: {{ $invoice->client->account_no }}<br>
                    @if ($invoice->client->phone)
                        {{ $invoice->client->phone }}<br>
                    @endif
                    @if ($invoice->client->email)
                        {{ $invoice->client->email }}<br>
                    @endif
                    @if ($invoice->client->address)
                        {{ $invoice->client->address }}
                    @endif
                </div>
            </td>
            <td>
                @if ($invoice->unit && ! $invoice->reading)
                    <div class="label">House</div>
                    <div class="name">{{ $invoice->unit->house_number }}</div>
                    <div class="line">
                        @if ($invoice->unit->property)
                            {{ $invoice->unit->property->name }}<br>
                        @endif
                        @if ($invoice->billing_period)
                            For {{ \App\Actions\BillUnit::periodLabel($invoice->billing_period) }}
                        @endif
                    </div>
                @else
                <div class="label">Meter</div>
                @if ($invoice->reading?->meter)
                    <div class="name">{{ $invoice->reading->meter->serial }}</div>
                    <div class="line">
                        @if ($invoice->reading->meter->location)
                            {{ $invoice->reading->meter->location }}<br>
                        @endif
                        Reading taken: {{ $invoice->reading->read_at->format('d M Y') }}<br>
                        Previous: {{ number_format($invoice->reading->previous_reading ?? 0) }} m³<br>
                        Current: {{ number_format($invoice->reading->reading) }} m³
                    </div>
                    @if ($invoice->unit)
                        <div class="line">House {{ $invoice->unit->fullLabel() }}</div>
                    @endif
                @else
                    <div class="line">—</div>
                @endif
                @endif
            </td>
        </tr>
    </table>

    {{-- Meta bar --}}
    <div class="meta-bar">
        <table>
            <tr>
                <td>
                    <div class="k">Issued</div>
                    <div class="v">{{ $invoice->issued_at->format('d M Y') }}</div>
                </td>
                <td>
                    <div class="k">Due</div>
                    <div class="v">{{ $invoice->due_at->format('d M Y') }}</div>
                </td>
                <td>
                    @if ($invoice->reading)
                        <div class="k">Consumption</div>
                        <div class="v">
                            {{ number_format((float) ($invoice->reading->consumption ?? 0)) }} m³
                        </div>
                    @elseif ($invoice->billing_period)
                        <div class="k">Period</div>
                        <div class="v">{{ \App\Actions\BillUnit::periodLabel($invoice->billing_period) }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width:15%">{{ $invoice->reading ? 'Qty (m³)' : 'Qty' }}</th>
                <th class="right" style="width:20%">Unit price</th>
                <th class="right" style="width:20%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="desc">{{ $item->description }}</td>
                    <td class="right mono">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td class="right mono">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right mono">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td>{{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
            @if ((float) $invoice->tax > 0)
                <tr>
                    <td>Tax</td>
                    <td>{{ number_format((float) $invoice->tax, 2) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td>Total {{ $invoice->vendor->currency }}</td>
                <td>{{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($invoice->notes)
        <div class="notes">
            <strong>Notes:</strong> {{ $invoice->notes }}
        </div>
    @endif

    <div class="footer">
        {{ $invoice->number }} · {{ $invoice->vendor->name }} · Generated {{ now()->format('d M Y H:i') }}
    </div>

</body>

</html>
