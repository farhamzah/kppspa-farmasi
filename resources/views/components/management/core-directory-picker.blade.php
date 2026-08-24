@props([
    'fieldName' => 'core_user_id',
    'fieldLabel' => 'Pilih Pengguna Core',
    'searchUrl',
    'placeholder' => 'Ketik nama, email, atau ID',
    'helper' => null,
    'required' => false,
    'value' => null,
    'displayValue' => null,
    'extraFields' => [],
    'queryFields' => [],
    'requiredContextFields' => [],
])

@php
    $pickerId = 'core-picker-'.md5($fieldName.'|'.$searchUrl.'|'.$placeholder);
    $encodedExtraFields = json_encode($extraFields, JSON_THROW_ON_ERROR);
    $encodedQueryFields = json_encode($queryFields, JSON_THROW_ON_ERROR);
    $encodedRequiredContextFields = json_encode($requiredContextFields, JSON_THROW_ON_ERROR);
    $displayFieldName = $fieldName.'_display';
    $resolvedDisplayValue = old($displayFieldName, $displayValue ?? $value);
@endphp

<div
    class="core-directory-picker"
    data-picker-id="{{ $pickerId }}"
    data-search-url="{{ $searchUrl }}"
    data-hidden-name="{{ $fieldName }}"
    data-display-name="{{ $displayFieldName }}"
    data-required="{{ $required ? 'true' : 'false' }}"
    data-extra-fields='{{ $encodedExtraFields }}'
    data-query-fields='{{ $encodedQueryFields }}'
    data-required-context-fields='{{ $encodedRequiredContextFields }}'
>
    <label for="{{ $pickerId }}-search" class="text-xs font-black uppercase tracking-widest text-slate-500">{{ $fieldLabel }}</label>
    <input type="hidden" name="{{ $fieldName }}" value="{{ $value }}">
    <input type="hidden" name="{{ $displayFieldName }}" value="{{ $resolvedDisplayValue }}">
    <div class="relative mt-1">
        <input
            id="{{ $pickerId }}-search"
            type="text"
            value="{{ $resolvedDisplayValue }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
        >
        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
            </svg>
        </div>
        <div class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-200/70" data-results></div>
    </div>
    @if($helper)
        <p class="mt-2 text-xs text-slate-500">{{ $helper }}</p>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const pickers = document.querySelectorAll('.core-directory-picker');
                if (!pickers.length) {
                    return;
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

                const debounce = (callback, wait = 250) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = window.setTimeout(() => callback(...args), wait);
                    };
                };

                const escapeHtml = (value) => {
                    const div = document.createElement('div');
                    div.textContent = value ?? '';
                    return div.innerHTML;
                };

                pickers.forEach((root) => {
                    const searchUrl = root.dataset.searchUrl;
                    const hiddenName = root.dataset.hiddenName;
                    const displayName = root.dataset.displayName;
                    const required = root.dataset.required === 'true';
                    const hiddenInput = root.querySelector(`input[type="hidden"][name="${hiddenName}"]`);
                    const hiddenDisplayInput = root.querySelector(`input[type="hidden"][name="${displayName}"]`);
                    const searchInput = root.querySelector('input[type="text"]');
                    const resultsBox = root.querySelector('[data-results]');
                    const extraFields = JSON.parse(root.dataset.extraFields || '{}');
                    const queryFields = JSON.parse(root.dataset.queryFields || '{}');
                    const requiredContextFields = JSON.parse(root.dataset.requiredContextFields || '{}');
                    let requestCounter = 0;
                    let cachedItems = [];

                    const setExtraFields = (item) => {
                        Object.entries(extraFields).forEach(([fieldName, sourceKey]) => {
                            const target = root.closest('form')?.querySelector(`[name="${fieldName}"]`);
                            if (target) {
                                target.value = item?.[sourceKey] ?? '';
                            }
                        });
                    };

                    const hideResults = () => {
                        resultsBox.classList.add('hidden');
                        resultsBox.innerHTML = '';
                    };

                    const selectItem = (item) => {
                        hiddenInput.value = item.core_user_id ?? '';
                        searchInput.value = [item.name, item.email].filter(Boolean).join(' - ') || item.core_user_id || '';
                        if (hiddenDisplayInput) {
                            hiddenDisplayInput.value = searchInput.value;
                        }
                        setExtraFields(item);
                        hideResults();
                    };

                    const renderResults = (items) => {
                        if (!items.length) {
                            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-2 text-sm text-slate-500">Data tidak ditemukan di Core.</div>';
                            resultsBox.classList.remove('hidden');
                            return;
                        }

                        resultsBox.innerHTML = items.map((item) => {
                            const secondary = [item.email, item.student_number, item.identifier, item.study_program].filter(Boolean).join(' • ');
                            const meta = [item.role_snapshot, item.cohort].filter(Boolean).join(' • ');

                            return `
                                <button type="button" class="block w-full rounded-xl px-3 py-3 text-left transition hover:bg-sky-50" data-core-item='${JSON.stringify(item).replace(/'/g, '&#39;')}'>
                                    <span class="block font-black text-slate-900">${escapeHtml(item.name ?? item.label ?? item.core_user_id ?? '-')}</span>
                                    <span class="mt-1 block text-xs text-slate-500">${escapeHtml(secondary || item.core_user_id || '')}</span>
                                    ${meta ? `<span class="mt-1 block text-[11px] font-bold text-cyan-700">${escapeHtml(meta)}</span>` : ''}
                                </button>
                            `;
                        }).join('');

                        resultsBox.classList.remove('hidden');
                        resultsBox.querySelectorAll('[data-core-item]').forEach((button) => {
                            button.addEventListener('click', () => {
                                selectItem(JSON.parse(button.dataset.coreItem));
                            });
                        });
                    };

                    const filterCachedItems = (query) => {
                        const keyword = (query || '').trim().toLowerCase();
                        if (keyword === '') {
                            return cachedItems.slice(0, 10);
                        }

                        return cachedItems.filter((item) => {
                            const haystacks = [
                                item.name,
                                item.label,
                                item.email,
                                item.student_number,
                                item.identifier,
                                item.core_user_id,
                            ].filter(Boolean).map((value) => String(value).toLowerCase());

                            return haystacks.some((value) => value.includes(keyword));
                        }).slice(0, 10);
                    };

                    const missingContextLabels = () => {
                        return Object.entries(requiredContextFields)
                            .filter(([, fieldName]) => {
                                const target = root.closest('form')?.querySelector(`[name="${fieldName}"]`);
                                const value = target?.value?.trim?.() ?? target?.value ?? '';

                                return value === '';
                            })
                            .map(([label]) => label);
                    };

                    const fetchResults = debounce(async (query) => {
                        const missingLabels = missingContextLabels();
                        if (missingLabels.length) {
                            resultsBox.innerHTML = `<div class="rounded-xl px-3 py-2 text-sm text-slate-500">Pilih ${escapeHtml(missingLabels.join(', '))} terlebih dahulu.</div>`;
                            resultsBox.classList.remove('hidden');
                            return;
                        }

                        requestCounter += 1;
                        const currentRequest = requestCounter;
                        const params = new URLSearchParams();
                        if (query.trim() !== '') {
                            params.set('q', query.trim());
                        }
                        params.set('limit', '10');
                        Object.entries(queryFields).forEach(([queryKey, fieldName]) => {
                            const target = root.closest('form')?.querySelector(`[name="${fieldName}"]`);
                            const value = target?.value?.trim?.() ?? target?.value ?? '';
                            if (value !== '') {
                                params.set(queryKey, value);
                            }
                        });

                        resultsBox.innerHTML = '<div class="rounded-xl px-3 py-2 text-sm text-slate-500">Memuat data Core...</div>';
                        resultsBox.classList.remove('hidden');

                        try {
                            const response = await fetch(`${searchUrl}?${params.toString()}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}`);
                            }

                            const payload = await response.json();
                            if (currentRequest !== requestCounter) {
                                return;
                            }

                            const items = Array.isArray(payload.data) ? payload.data : [];
                            if (items.length) {
                                cachedItems = items;
                                renderResults(items);
                                return;
                            }

                            const fallbackItems = filterCachedItems(query);
                            renderResults(fallbackItems);
                        } catch (error) {
                            if (currentRequest !== requestCounter) {
                                return;
                            }

                            const fallbackItems = filterCachedItems(query);
                            if (fallbackItems.length) {
                                renderResults(fallbackItems);
                                return;
                            }

                            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-2 text-sm text-rose-600">Pencarian Core belum tersedia. Coba lagi sebentar.</div>';
                            resultsBox.classList.remove('hidden');
                        }
                    }, 250);

                    searchInput.addEventListener('focus', () => fetchResults(searchInput.value));
                    searchInput.addEventListener('input', () => {
                        hiddenInput.value = '';
                        if (hiddenDisplayInput) {
                            hiddenDisplayInput.value = searchInput.value;
                        }
                        setExtraFields(null);
                        fetchResults(searchInput.value);
                    });
                    searchInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            hideResults();
                        }
                    });

                    document.addEventListener('click', (event) => {
                        if (!root.contains(event.target)) {
                            hideResults();
                        }
                    });

                    root.closest('form')?.addEventListener('submit', (event) => {
                        if (required && !hiddenInput.value) {
                            event.preventDefault();
                            searchInput.focus();
                            resultsBox.innerHTML = '<div class="rounded-xl px-3 py-2 text-sm text-rose-600">Pilih data dari daftar Core terlebih dahulu.</div>';
                            resultsBox.classList.remove('hidden');
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
