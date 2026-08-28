@extends('layouts.app')

@section('title', 'Daftar PKPA - '.config('app.name'))
@section('page_title', 'Daftar PKPA')

@section('content')
<div class="mx-auto max-w-3xl">
    <form method="POST" action="{{ route('student.kp-registrations.store') }}" class="si-page">
        @csrf

        <x-ui.page-header
            eyebrow="Pendaftaran PKPA"
            title="Buat Pendaftaran PKPA"
            subtitle="Pilih periode yang tersedia dan tambahkan catatan bila ada informasi pendukung untuk koordinator."
        />

        <x-ui.card large>
            @if($errors->any())
                <div class="si-alert mb-5 border-rose-200 bg-rose-50 text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="space-y-5">
                <div>
                    <label for="kp_period_id" class="si-label">Program / Periode PKPA</label>
                    <select id="kp_period_id" name="kp_period_id" class="si-input">
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Hanya periode yang sedang membuka pendaftaran yang dapat dipilih.</p>
                </div>

                <div>
                    <label for="notes" class="si-label">Catatan</label>
                    <textarea id="notes" name="notes" rows="4" class="si-input" placeholder="Opsional, tuliskan catatan singkat untuk koordinator">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('student.kp-registrations.index') }}" class="si-btn si-btn-secondary">Batal</a>
                <button class="si-btn si-btn-primary" type="submit">Buat Pendaftaran</button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
