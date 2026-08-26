@extends('layouts.app')
@section('title', 'Edit Wahana PKPA - '.config('app.name'))
@section('page_title', 'Edit Wahana PKPA')
@section('content')
<div class="space-y-4">
    @if($domain->canBeDeleted())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Wahana ini bisa dihapus. Untuk legacy Puskesmas, sistem akan lebih dulu memindahkan data aktif ke <strong>Pemerintahan &gt; Puskesmas</strong>.
        </div>
    @endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('management.pkpa-practice-domains.update', $domain) }}">@method('PUT')@include('management.pkpa-practice-domains._form')</form>
    </div>
</div>
@endsection
