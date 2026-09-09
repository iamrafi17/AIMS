<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px 34px; }
        body { color: #1f2937; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
        .header { border-bottom: 3px solid #7d001d; margin-bottom: 14px; padding-bottom: 10px; }
        .brand { color: #7d001d; font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { color: #690016; font-size: 20px; margin: 4px 0; }
        .subtitle, .metadata { color: #64748b; }
        .metadata { margin-top: 5px; }
        .summary { margin: 0 0 14px; width: 100%; }
        .summary td { background: #f8fafc; border: 1px solid #e2e8f0; padding: 7px 8px; width: 25%; }
        .summary .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .summary .value { color: #690016; font-size: 13px; font-weight: bold; margin-top: 3px; }
        table.data { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .data thead { display: table-header-group; }
        .data th { background: #7d001d; color: white; font-size: 7px; padding: 6px 4px; text-align: left; text-transform: uppercase; }
        .data td { border-bottom: 1px solid #e2e8f0; overflow-wrap: anywhere; padding: 5px 4px; vertical-align: top; }
        .data tr:nth-child(even) td { background: #fafafa; }
        .empty { color: #64748b; padding: 24px !important; text-align: center; }
        .footer { bottom: -20px; color: #94a3b8; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">Marinduque State University &middot; Academic Internship Monitoring System</div>
        <h1>{{ $report['title'] }}</h1>
        <div class="subtitle">{{ $report['subtitle'] }}</div>
        <div class="metadata"><strong>Scope:</strong> {{ $report['scope'] }} &nbsp; | &nbsp; <strong>Period:</strong> {{ $report['period'] }} &nbsp; | &nbsp; <strong>Generated:</strong> {{ $report['generated_at'] }} &nbsp; | &nbsp; <strong>Prepared by:</strong> {{ $report['generated_by'] ?? 'Authorized AIMS user' }}</div>
    </header>

    <table class="summary">
        @foreach(array_chunk($report['summary'], 4, true) as $summaryRow)
            <tr>
                @foreach($summaryRow as $label => $value)
                    <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
                @endforeach
                @for($index = count($summaryRow); $index < 4; $index++)<td></td>@endfor
            </tr>
        @endforeach
    </table>

    <table class="data">
        <thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td class="empty" colspan="{{ count($report['headers']) }}">No records were found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">Generated directly from authorized AIMS database records. This report is read-only.</div>
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text({{ $landscape ? 755 : 510 }}, {{ $landscape ? 565 : 815 }}, "Page {PAGE_NUM} of {PAGE_COUNT}", null, 7, array(0.58, 0.64, 0.72));
        }
    </script>
</body>
</html>
