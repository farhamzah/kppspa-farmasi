<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pemantauan Penetapan Tempat PKPA</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        h1 { font-size: 18pt; margin-bottom: 4px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 6px; vertical-align: top; }
        th { background: #e8eef7; }
        .meta { margin: 12px 0; }
    </style>
</head>
<body>
    <h1>Pemantauan Penetapan Tempat PKPA</h1>
    <p>MY PKPA Farmasi UBP</p>

    <div class="meta">
        @foreach($filters as $label => $value)
            <div><strong>{{ $label }}:</strong> {{ $value }}</div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach(array_keys($rows->first() ?? ['No' => '', 'Mahasiswa' => '', 'NIM' => '', 'Periode' => '', 'Tempat PKPA' => '', 'Waktu Pilih' => '', 'Status' => '']) as $heading)
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
