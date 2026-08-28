<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penempatan KP</title>
    <style>
        @page Section1 { size: 29.7cm 21cm; margin: 1.2cm; }
        body { color: #0f172a; font-family: Arial, sans-serif; font-size: 9pt; }
        div.Section1 { page: Section1; }
        h1 { font-size: 18pt; margin: 0 0 3pt; }
        .subtitle { color: #475569; font-size: 10pt; margin: 0 0 12pt; }
        .meta { border-collapse: collapse; margin: 10pt 0 14pt; width: 100%; }
        .meta td { border: 1px solid #dbe3ef; padding: 6pt; vertical-align: top; width: 25%; }
        .meta strong { color: #334155; }
        table.report { border-collapse: collapse; table-layout: fixed; width: 100%; }
        table.report th, table.report td { border: 1px solid #dbe3ef; padding: 5pt; vertical-align: top; word-wrap: break-word; }
        table.report th { background: #f1f5f9; color: #334155; font-size: 8pt; text-transform: uppercase; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
<div class="Section1">
    <h1>Penempatan KP</h1>
    <p class="subtitle">MY PKPA Farmasi UBP</p>

    <table class="meta">
        @foreach(array_chunk($filters + ['Total data' => $rows->count()], 4, true) as $filterRow)
            <tr>
                @foreach($filterRow as $label => $value)
                    <td><strong>{{ $label }}:</strong><br>{{ $value }}</td>
                @endforeach
                @for($i = count($filterRow); $i < 4; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>

    <table class="report">
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
</div>
</body>
</html>
