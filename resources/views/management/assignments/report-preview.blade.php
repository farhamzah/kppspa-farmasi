<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Penempatan KP</title>
    <style>
        body { color: #0f172a; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .subtitle { color: #475569; font-size: 13px; margin-bottom: 20px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
        .toolbar button, .toolbar a { border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; padding: 8px 12px; text-decoration: none; }
        .meta { color: #475569; display: grid; font-size: 12px; gap: 6px; grid-template-columns: repeat(4, 1fr); line-height: 1.5; margin: 18px 0; }
        .meta div { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; }
        table { border-collapse: collapse; font-size: 12px; width: 100%; }
        th, td { border: 1px solid #dbe3ef; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; color: #475569; font-size: 11px; text-transform: uppercase; }
        @media print {
            body { margin: 16mm; }
            .toolbar { display: none; }
            a { color: inherit; text-decoration: none; }
            .meta { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            body { margin: 18px; }
            .meta { grid-template-columns: 1fr; }
            table { font-size: 11px; }
        }
    </style>
</head>
<body @if($printMode) onload="window.print()" @endif>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        <a href="{{ url()->previous() }}">Kembali</a>
    </div>

    <h1>Penempatan KP</h1>
    <div class="subtitle">MY PKPA Farmasi UBP</div>

    <div class="meta">
        @foreach($filters as $label => $value)
            <div><strong>{{ $label }}:</strong><br>{{ $value }}</div>
        @endforeach
        <div><strong>Total data:</strong><br>{{ $rows->count() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach(array_keys($rows->first() ?? ['No' => '', 'Mahasiswa' => '', 'NIM' => '', 'Periode' => '', 'Tempat KP' => '', 'Pembimbing Dalam' => '', 'Preseptor' => '', 'Status' => '']) as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="12">Belum ada data sesuai filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
