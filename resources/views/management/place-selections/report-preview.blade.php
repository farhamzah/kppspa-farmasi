<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pemantauan Pemilihan Tempat KP</title>
    <style>
        body { color: #0f172a; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .subtitle { color: #475569; font-size: 13px; margin-bottom: 20px; }
        .toolbar { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
        .toolbar button, .toolbar a { border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; padding: 8px 12px; text-decoration: none; }
        .summary { display: grid; gap: 8px; grid-template-columns: repeat(4, 1fr); margin: 18px 0; }
        .box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; }
        .box span { color: #64748b; display: block; font-size: 11px; text-transform: uppercase; }
        .box strong { display: block; font-size: 18px; margin-top: 4px; }
        table { border-collapse: collapse; font-size: 12px; width: 100%; }
        th, td { border: 1px solid #dbe3ef; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; color: #475569; font-size: 11px; text-transform: uppercase; }
        .meta { color: #475569; font-size: 12px; line-height: 1.7; }
        @media print {
            body { margin: 16mm; }
            .toolbar { display: none; }
            a { color: inherit; text-decoration: none; }
            .summary { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body @if($printMode) onload="window.print()" @endif>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        <a href="{{ url()->previous() }}">Kembali</a>
    </div>

    <h1>Pemantauan Pemilihan Tempat KP</h1>
    <div class="subtitle">SI-KP Farmasi UBP</div>

    <div class="meta">
        @foreach($filters as $label => $value)
            <div><strong>{{ $label }}:</strong> {{ $value }}</div>
        @endforeach
    </div>

    <div class="summary">
        @foreach([['Terverifikasi',$stats['verified']],['Sudah Memilih',$stats['selected']],['Belum Memilih',$stats['not_selected']],['Daftar Tunggu',$stats['waiting']],['Total Kuota',$stats['total_quota']],['Sisa Kuota',$stats['remaining_quota']],['Tempat Penuh',$stats['full_places']]] as [$label,$value])
            <div class="box"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach(array_keys($rows->first() ?? ['No' => '', 'Mahasiswa' => '', 'NIM' => '', 'Periode' => '', 'Tempat KP' => '', 'Waktu Pilih' => '', 'Status' => '']) as $heading)
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
