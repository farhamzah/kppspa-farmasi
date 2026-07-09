@if($logbook->hasEvidenceFile() || $logbook->hasEvidenceLink())
    <div class="flex flex-wrap gap-2">
        @if($logbook->hasEvidenceFile())
            <a href="{{ route($downloadRoute, $logbook) }}" class="inline-flex rounded-lg border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700">Download File</a>
        @endif
        @if($logbook->hasEvidenceLink())
            <a href="{{ $logbook->evidence_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-sky-200 px-4 py-2 text-sm font-semibold text-sky-700">Preview Link</a>
            <a href="{{ $logbook->evidenceExternalDownloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Download/Buka Link</a>
        @endif
    </div>
@endif
