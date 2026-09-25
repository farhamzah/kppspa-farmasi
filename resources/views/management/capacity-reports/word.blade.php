<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page Section1 { size: 29.7cm 21cm; margin: 1.2cm; }
        body { color: #0f172a; font-family: Arial, sans-serif; font-size: 9pt; }
        div.Section1 { page: Section1; }
        h1 { font-size: 18pt; margin: 0 0 3pt; }
        .subtitle { color: #475569; font-size: 10pt; margin: 0 0 12pt; }
        table { border-collapse: collapse; width: 100%; }
        .meta { margin: 10pt 0 14pt; }
        .meta td, .report th, .report td { border: 1px solid #dbe3ef; padding: 5pt; vertical-align: top; }
        .meta td { width: 20%; }
        .report { table-layout: fixed; }
        .report th { background: #f1f5f9; color: #334155; font-size: 8pt; text-transform: uppercase; }
        .report td { word-wrap: break-word; }
    </style>
</head>
<body><div class="Section1">
    <h1>{{ $title }}</h1>
    <p class="subtitle">MY PKPA Farmasi UBP</p>
    <table class="meta">
        @foreach(array_chunk($filters + ['Total data' => $rows->count()], 5, true) as $filterRow)
            <tr>
                @foreach($filterRow as $label => $value)<td><strong>{{ $label }}:</strong><br>{{ $value }}</td>@endforeach
                @for($i = count($filterRow); $i < 5; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
    <table class="report">
        <thead><tr>@foreach(array_keys($rows->first() ?? ['No' => '', 'Data' => '']) as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty<tr><td colspan="20">Belum ada data sesuai filter.</td></tr>@endforelse
        </tbody>
    </table>
</div></body>
</html>
