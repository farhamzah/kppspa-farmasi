@extends('layouts.app')
@section('title','Rekap KP - '.config('app.name'))
@section('page_title','Rekap KP')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-black uppercase tracking-widest text-cyan-700">Pusat Rekap</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Rekap, Pemantauan, dan Ekspor KP</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Gunakan halaman ini untuk membaca ringkasan kemajuan KP, pratinjau cetak, cetak, dan mengunduh data penting dalam format Word, Excel, atau PDF.</p>
    </section>
    <section class="grid gap-4 md:grid-cols-3">
        @foreach($summary as $label => $value)
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">{{ str_replace('_',' ',ucfirst($label)) }}</p>
                <p class="mt-3 text-3xl font-black text-cyan-700">{{ $value }}</p>
            </div>
        @endforeach
    </section>
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach([
            'students' => ['Mahasiswa KP','Rekap status mahasiswa, pendaftaran, pembimbing, sidang, dan nilai.'],
            'placements' => ['Penempatan KP','Rekap tempat, pembimbing, dan status penempatan.'],
            'logbooks' => ['Logbook','Rekap jumlah dan status logbook per mahasiswa.'],
            'exams' => ['Sidang','Rekap jadwal dan status sidang KP.'],
            'scores' => ['Nilai','Rekap nilai per sumber penilai dan grade akhir.'],
        ] as $type => [$title, $desc])
            <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h3 class="font-black text-slate-950">{{ $title }}</h3>
                <p class="mt-2 min-h-16 text-sm leading-6 text-slate-500">{{ $desc }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('management.recaps.'.$type) }}" class="rounded-2xl border border-cyan-200 px-3 py-2 text-xs font-bold text-cyan-700">Lihat</a>
                    <a href="{{ route('management.recaps.preview', $type) }}" target="_blank" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">Pratinjau</a>
                    <a href="{{ route('management.recaps.download', ['type' => $type, 'format' => 'excel']) }}" class="rounded-2xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">Excel</a>
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection
