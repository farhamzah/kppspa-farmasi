@extends('layouts.app')

@section('title', 'Dokumen PKPA')

@section('content')
<div class="space-y-6">
    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-bold uppercase tracking-wide text-cyan-700">Dokumen PKPA</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Dokumen yang diterbitkan untuk Anda</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Dokumen di halaman ini adalah Dokumen Internal MY PKPA atau draft terbit sesuai statusnya. File akademik tetap disimpan privat.</p>
    </section>

    <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
        @forelse($documents as $document)
            <article class="mb-3 rounded-2xl border border-slate-100 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="font-black text-slate-900">{{ $document->title }}</p>
                        <p class="text-sm text-slate-500">{{ $document->type?->name }} - {{ $document->document_number ?: 'tanpa nomor' }} - {{ optional($document->published_at)->format('d M Y H:i') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($document->versions as $version)
                            <a href="{{ route('student.pkpa-documents.download', $version) }}" class="rounded-xl bg-cyan-700 px-3 py-2 text-xs font-bold text-white">{{ strtoupper($version->output_format) }}</a>
                        @endforeach
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-slate-50 p-5 text-sm font-bold text-slate-600">Belum ada dokumen PKPA yang diterbitkan untuk akun Anda.</p>
        @endforelse
    </section>
</div>
@endsection
