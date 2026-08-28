<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { color: #0f172a; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .subtitle { color: #475569; font-size: 13px; margin-bottom: 20px; }
        .toolbar { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
        .toolbar button, .toolbar a { border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; padding: 8px 12px; text-decoration: none; }
        .meta { color: #475569; font-size: 12px; line-height: 1.7; margin-bottom: 18px; }
        table { border-collapse: collapse; font-size: 11px; width: 100%; }
        th, td { border: 1px solid #dbe3ef; padding: 7px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; color: #475569; font-size: 10px; text-transform: uppercase; }
        @media print {
            body { margin: 14mm; }
            .toolbar { display: none; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body @if($printMode) onload="window.print()" @endif>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('management.recaps.'.$type, request()->only(['period', 'status', 'q'])) }}">Kembali</a>
    </div>

    <h1>{{ $title }}</h1>
    <div class="subtitle">MY PKPA Farmasi UBP</div>

    <div class="meta">
        @foreach($filters as $label => $value)
            <div><strong>{{ $label }}:</strong> {{ $value }}</div>
        @endforeach
        <div><strong>Total data:</strong> {{ $rows->count() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                @foreach(array_keys($rows->first() ?? ['Data' => '']) as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows->values() as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="20">Belum ada data sesuai filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
