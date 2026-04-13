@extends('layouts.base')
{{-- Legacy version kept temporarily for rollback while the refined layout is active below. --}}
@if (false)

@php
    $slideCount = count($slides);
@endphp

@push('styles')
    <style>
        .meeting-presentation-root {
            min-height: 100vh;
            width: 100%;
            background: #020617;
            color: #e2e8f0;
        }

        .meeting-presentation-nav.is-active {
            border-color: #38bdf8 !important;
            background: rgba(56, 189, 248, 0.12) !important;
        }

        .meeting-presentation-frame {
            width: 100%;
            height: 70vh;
            border: 0;
            border-radius: 1rem;
            background: #ffffff;
        }

        .meeting-presentation-image {
            width: 100%;
            max-height: 70vh;
            object-fit: contain;
            display: block;
        }
    </style>
@endpush

@section('main')
    <div class="meeting-presentation-root" id="meeting-presentation-app">
        <div class="grid min-h-screen lg:grid-cols-[340px_minmax(0,1fr)]">
            <aside class="border-r border-slate-800 bg-slate-950/90 p-4 lg:p-5">
                <div class="grid gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Mode Presentasi</div>
                        <div class="mt-2 text-lg font-semibold text-white">{{ $meeting->title ?? '-' }}</div>
                        <div class="mt-2 text-sm text-slate-300">
                            {{ $typeOptions[$meeting->meeting_type] ?? ($meeting->meeting_type ?? '-') }}
                        </div>
                        <div class="text-sm text-slate-400">
                            {{ $meeting->meeting_at ? $meeting->meeting_at->format('d/m/Y H:i') : '-' }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-900 p-3 text-xs text-slate-300">
                        Navigasi: panah kiri/kanan, `Home`, `End`, `F` untuk fullscreen.
                    </div>

                    <div class="max-h-[70vh] space-y-2 overflow-y-auto pe-1">
                        @foreach ($slides as $index => $slide)
                            <button type="button"
                                class="meeting-presentation-nav js-presentation-nav w-full rounded-xl border border-slate-800 bg-slate-900 p-3 text-left transition"
                                data-index="{{ $index }}">
                                <div class="text-[11px] uppercase tracking-wide text-sky-300">
                                    Agenda {{ $slide['agenda_no'] }} • Bahan {{ $slide['material_no'] }}
                                </div>
                                <div class="mt-1 font-semibold text-white">
                                    {{ $slide['agenda_title'] }}
                                </div>
                                <div class="mt-1 truncate text-xs text-slate-400">
                                    {{ $slide['material_name'] }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <section class="flex min-h-screen flex-col">
                <header class="border-b border-slate-800 bg-slate-900/80 px-4 py-3 lg:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div id="presentation-current-counter"
                                class="text-xs uppercase tracking-[0.2em] text-slate-400">
                                Slide 1 / {{ $slideCount }}
                            </div>
                            <div id="presentation-current-label" class="mt-1 text-lg font-semibold text-white">
                                {{ $slides[0]['agenda_title'] ?? '-' }}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="presentation-prev" class="btn btn-sm btn-light">
                                Sebelumnya
                            </button>
                            <button type="button" id="presentation-next" class="btn btn-sm btn-primary">
                                Berikutnya
                            </button>
                            <button type="button" id="presentation-fullscreen" class="btn btn-sm btn-warning">
                                Fullscreen
                            </button>
                            <a href="{{ route('meeting.show', $meeting) }}" class="btn btn-sm btn-light">
                                Kembali ke Detail
                            </a>
                        </div>
                    </div>
                </header>

                <div class="flex-1 p-4 lg:p-6">
                    @foreach ($slides as $index => $slide)
                        <article class="js-presentation-slide {{ $index === 0 ? '' : 'hidden' }}"
                            data-index="{{ $index }}">
                            <div class="grid gap-4">
                                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 lg:p-5">
                                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
                                        <div>
                                            <div class="text-xs uppercase tracking-[0.2em] text-sky-300">
                                                Agenda {{ $slide['agenda_no'] }} • Bahan {{ $slide['material_no'] }}
                                            </div>
                                            <div class="mt-2 text-2xl font-semibold text-white">
                                                {{ $slide['agenda_title'] }}
                                            </div>
                                            @if ($slide['agenda_description'])
                                                <div class="mt-2 text-sm text-slate-300">
                                                    {{ $slide['agenda_description'] }}
                                                </div>
                                            @endif
                                        </div>

                                        <div
                                            class="grid gap-2 rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-300">
                                            <div><span class="text-slate-400">File:</span> {{ $slide['material_name'] }}
                                            </div>
                                            <div><span class="text-slate-400">PIC:</span> {{ $slide['agenda_pic'] ?? '-' }}
                                            </div>
                                            <div><span class="text-slate-400">Direktorat:</span>
                                                {{ $slide['agenda_owner'] ?? '-' }}</div>
                                            <div><span class="text-slate-400">Upload:</span>
                                                {{ $slide['uploaded_at'] ?? '-' }}</div>
                                            @if ($slide['source_reference'])
                                                <div><span class="text-slate-400">Referensi:</span>
                                                    {{ $slide['source_reference'] }}</div>
                                            @endif
                                            @if ($slide['source_meeting'])
                                                <div><span class="text-slate-400">Sumber Rapat:</span>
                                                    {{ $slide['source_meeting'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 lg:p-5">
                                    @if ($slide['viewer_type'] === 'image')
                                        <img src="{{ $slide['material_url'] }}" alt="{{ $slide['material_name'] }}"
                                            class="meeting-presentation-image rounded-2xl bg-slate-950">
                                    @elseif ($slide['viewer_type'] === 'pdf')
                                        <iframe src="{{ $slide['material_url'] }}#toolbar=0&navpanes=0&view=FitH"
                                            class="meeting-presentation-frame">
                                        </iframe>
                                    @else
                                        <div class="flex min-h-[70vh] items-center justify-center">
                                            <div
                                                class="max-w-xl rounded-2xl border border-slate-800 bg-slate-950 p-8 text-center">
                                                <div class="text-lg font-semibold text-white">{{ $slide['material_name'] }}
                                                </div>
                                                <div class="mt-3 text-sm text-slate-300">
                                                    File ini tidak bisa dirender inline di browser. Untuk pengalaman
                                                    presentasi yang lebih rapi,
                                                    ubah file Office ke PDF saat upload.
                                                </div>
                                                <div class="mt-5">
                                                    <a href="{{ $slide['material_url'] }}" target="_blank" rel="noopener"
                                                        class="btn btn-primary">
                                                        Buka File
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.setProperty('zoom', '100%', 'important');

            const slides = Array.from(document.querySelectorAll('.js-presentation-slide'));
            const navButtons = Array.from(document.querySelectorAll('.js-presentation-nav'));
            const counter = document.getElementById('presentation-current-counter');
            const label = document.getElementById('presentation-current-label');
            const prevButton = document.getElementById('presentation-prev');
            const nextButton = document.getElementById('presentation-next');
            const fullscreenButton = document.getElementById('presentation-fullscreen');

            let activeIndex = 0;

            function renderSlide(index) {
                if (index < 0 || index >= slides.length) {
                    return;
                }

                activeIndex = index;

                slides.forEach(function(slide, slideIndex) {
                    slide.classList.toggle('hidden', slideIndex !== activeIndex);
                });

                navButtons.forEach(function(button, buttonIndex) {
                    button.classList.toggle('is-active', buttonIndex === activeIndex);
                });

                const activeSlide = slides[activeIndex];
                const activeButton = navButtons[activeIndex];

                counter.textContent = 'Slide ' + (activeIndex + 1) + ' / ' + slides.length;
                label.textContent = activeButton ?
                    activeButton.querySelector('.font-semibold')?.textContent?.trim() || 'Slide' :
                    'Slide';

                prevButton.disabled = activeIndex === 0;
                nextButton.disabled = activeIndex === slides.length - 1;

                activeButton?.scrollIntoView({
                    block: 'nearest'
                });
                activeSlide?.scrollIntoView({
                    block: 'start'
                });
            }

            navButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    renderSlide(Number(button.dataset.index || 0));
                });
            });

            prevButton.addEventListener('click', function() {
                renderSlide(activeIndex - 1);
            });

            nextButton.addEventListener('click', function() {
                renderSlide(activeIndex + 1);
            });

            fullscreenButton.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen?.();
                    return;
                }

                document.exitFullscreen?.();
            });

            document.addEventListener('keydown', function(event) {
                const tagName = document.activeElement?.tagName || '';
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)) {
                    return;
                }

                if (event.key === 'ArrowRight' || event.key === 'PageDown') {
                    event.preventDefault();
                    renderSlide(activeIndex + 1);
                }

                if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
                    event.preventDefault();
                    renderSlide(activeIndex - 1);
                }

                if (event.key === 'Home') {
                    event.preventDefault();
                    renderSlide(0);
                }

                if (event.key === 'End') {
                    event.preventDefault();
                    renderSlide(slides.length - 1);
                }

                if (event.key.toLowerCase() === 'f') {
                    event.preventDefault();
                    fullscreenButton.click();
                }
            });

            renderSlide(0);
        });
    </script>
@endpush
@endif

@php
    $slideCount = count($slides);
    $agendaCount = count($agendaGroups);
    $firstSlide = $slides[0] ?? null;
@endphp

@push('styles')
    <style>
        .meeting-presentation-root {
            --presentation-bg: #020617;
            --presentation-panel: rgba(15, 23, 42, 0.78);
            --presentation-panel-strong: rgba(15, 23, 42, 0.96);
            --presentation-border: rgba(148, 163, 184, 0.18);
            min-height: 100vh;
            width: 100%;
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.16), transparent 32%),
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 26%),
                linear-gradient(180deg, #020617 0%, #0f172a 100%);
            color: #e2e8f0;
        }

        .meeting-presentation-shell {
            min-height: 100vh;
        }

        .meeting-presentation-sidebar {
            background: rgba(2, 6, 23, 0.88);
            backdrop-filter: blur(18px);
        }

        .meeting-presentation-panel {
            border: 1px solid var(--presentation-border);
            background: var(--presentation-panel);
            backdrop-filter: blur(16px);
        }

        .meeting-presentation-panel-strong {
            border: 1px solid var(--presentation-border);
            background: var(--presentation-panel-strong);
            backdrop-filter: blur(18px);
        }

        .meeting-presentation-nav {
            border: 1px solid transparent;
        }

        .meeting-presentation-nav.is-active {
            border-color: rgba(56, 189, 248, 0.65) !important;
            background: rgba(56, 189, 248, 0.14) !important;
            box-shadow: inset 0 0 0 1px rgba(56, 189, 248, 0.16);
        }

        .meeting-presentation-nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            padding: 0.22rem 0.55rem;
            font-size: 11px;
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.72);
        }

        .meeting-presentation-frame {
            width: 100%;
            height: 68vh;
            border: 0;
            border-radius: 1.25rem;
            background: #ffffff;
        }

        .meeting-presentation-image {
            width: 100%;
            max-height: 68vh;
            object-fit: contain;
            display: block;
        }

        .meeting-presentation-stat {
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 1rem;
            background: rgba(15, 23, 42, 0.58);
            padding: 1rem;
        }
    </style>
@endpush

@section('main')
    <div class="meeting-presentation-root" id="meeting-presentation-app">
        <div class="meeting-presentation-shell grid lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="meeting-presentation-sidebar border-r border-slate-800 p-4 lg:p-5">
                <div class="grid h-full gap-4">
                    <div class="meeting-presentation-panel rounded-2xl p-4">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-sky-300">Mode Presentasi</div>
                        <div class="mt-2 text-xl font-semibold text-white">{{ $meeting->title ?? '-' }}</div>
                        <div class="mt-3 grid gap-1 text-sm text-slate-300">
                            <div>{{ $typeOptions[$meeting->meeting_type] ?? ($meeting->meeting_type ?? '-') }}</div>
                            <div>{{ $meeting->meeting_at ? $meeting->meeting_at->format('d/m/Y H:i') : '-' }}</div>
                            <div>{{ $meeting->location ?? 'Lokasi belum diisi' }}</div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="meeting-presentation-stat">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Agenda</div>
                                <div class="mt-2 text-2xl font-semibold text-white">{{ $agendaCount }}</div>
                            </div>
                            <div class="meeting-presentation-stat">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Slide</div>
                                <div class="mt-2 text-2xl font-semibold text-white">{{ $slideCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="meeting-presentation-panel rounded-2xl p-4 text-xs text-slate-300">
                        Navigasi: panah kiri dan kanan, PageUp dan PageDown, Home, End, dan tombol F untuk fullscreen.
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto pe-1">
                        <div class="space-y-4">
                            @foreach ($agendaGroups as $group)
                                <section class="meeting-presentation-panel rounded-2xl p-3">
                                    <div class="px-1 pb-3">
                                        <div class="text-[11px] uppercase tracking-[0.2em] text-sky-300">
                                            Agenda {{ $group['agenda_no'] }}
                                        </div>
                                        <div class="mt-1 font-semibold text-white">
                                            {{ $group['agenda_title'] }}
                                        </div>
                                        <div class="mt-1 text-xs text-slate-400">
                                            {{ $group['material_count'] }} bahan / {{ $group['slide_count'] }} slide
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        @foreach ($group['slides'] as $slide)
                                            <button type="button"
                                                class="meeting-presentation-nav js-presentation-nav w-full rounded-xl bg-slate-900/70 p-3 text-left transition"
                                                data-index="{{ (int) $slide['index'] - 1 }}">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="meeting-presentation-nav-pill">
                                                        {{ $slide['slide_label'] }}
                                                    </span>
                                                    <span class="meeting-presentation-nav-pill">
                                                        {{ $slide['viewer_label'] }}
                                                    </span>
                                                </div>
                                                <div class="mt-2 font-semibold text-white">
                                                    {{ $slide['slide_type'] === 'agenda' ? $slide['agenda_title'] : $slide['material_name'] }}
                                                </div>
                                                <div class="mt-1 truncate text-xs text-slate-400">
                                                    {{ $slide['slide_type'] === 'agenda' ? ($slide['agenda_description'] ?: 'Ringkasan agenda') : $slide['agenda_title'] }}
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            </aside>

            <section class="flex min-h-screen flex-col">
                <header class="border-b border-slate-800 bg-slate-950/60 px-4 py-3 backdrop-blur lg:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div id="presentation-current-counter" class="text-xs uppercase tracking-[0.22em] text-slate-400">
                                Slide 1 / {{ $slideCount }}
                            </div>
                            <div id="presentation-current-label" class="mt-1 text-xl font-semibold text-white">
                                {{ $firstSlide['agenda_title'] ?? '-' }}
                            </div>
                            <div id="presentation-current-caption" class="mt-1 text-sm text-slate-300">
                                {{ $firstSlide['slide_caption'] ?? '' }}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="presentation-prev" class="btn btn-sm btn-light">
                                Sebelumnya
                            </button>
                            <button type="button" id="presentation-next" class="btn btn-sm btn-primary">
                                Berikutnya
                            </button>
                            <button type="button" id="presentation-fullscreen" class="btn btn-sm btn-warning">
                                Fullscreen
                            </button>
                            <a href="{{ route('meeting.show', $meeting) }}" class="btn btn-sm btn-light">
                                Kembali ke Detail
                            </a>
                        </div>
                    </div>
                </header>

                <div class="flex-1 p-4 lg:p-6">
                    @foreach ($slides as $index => $slide)
                        <article
                            class="js-presentation-slide {{ $index === 0 ? '' : 'hidden' }}"
                            data-index="{{ $index }}"
                            data-nav-label="{{ $slide['slide_type'] === 'agenda' ? 'Agenda ' . $slide['agenda_no'] : $slide['material_name'] }}"
                            data-nav-caption="{{ $slide['slide_caption'] ?? '' }}">
                            @if ($slide['slide_type'] === 'agenda')
                                <div class="grid gap-4">
                                    <div class="meeting-presentation-panel-strong rounded-[1.75rem] p-6 lg:p-8">
                                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                                            <div>
                                                <div class="meeting-presentation-nav-pill">Agenda {{ $slide['agenda_no'] }}</div>
                                                <div class="mt-4 text-3xl font-semibold text-white lg:text-4xl">
                                                    {{ $slide['agenda_title'] }}
                                                </div>
                                                @if ($slide['agenda_description'])
                                                    <div class="mt-4 max-w-3xl text-sm leading-7 text-slate-300 lg:text-base">
                                                        {{ $slide['agenda_description'] }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="meeting-presentation-panel rounded-2xl p-4 text-sm text-slate-300">
                                                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                                    Ringkasan Agenda
                                                </div>
                                                <div class="mt-4 grid gap-3">
                                                    <div><span class="text-slate-400">PIC:</span> {{ $slide['agenda_pic'] ?? '-' }}</div>
                                                    <div><span class="text-slate-400">Direktorat:</span> {{ $slide['agenda_owner'] ?? '-' }}</div>
                                                    <div><span class="text-slate-400">Jumlah bahan:</span> {{ $slide['agenda_material_count'] }}</div>
                                                    <div><span class="text-slate-400">Bisa preview inline:</span> {{ $slide['agenda_inline_material_count'] }}</div>
                                                    @if ($slide['source_reference'])
                                                        <div><span class="text-slate-400">Referensi:</span> {{ $slide['source_reference'] }}</div>
                                                    @endif
                                                    @if ($slide['source_meeting'])
                                                        <div><span class="text-slate-400">Sumber rapat:</span> {{ $slide['source_meeting'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        <div class="meeting-presentation-stat">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Urutan</div>
                                            <div class="mt-2 text-3xl font-semibold text-white">{{ $slide['agenda_no'] }}</div>
                                        </div>
                                        <div class="meeting-presentation-stat">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Jumlah Bahan</div>
                                            <div class="mt-2 text-3xl font-semibold text-white">{{ $slide['agenda_material_count'] }}</div>
                                        </div>
                                        <div class="meeting-presentation-stat">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Preview Inline</div>
                                            <div class="mt-2 text-3xl font-semibold text-white">{{ $slide['agenda_inline_material_count'] }}</div>
                                        </div>
                                        <div class="meeting-presentation-stat">
                                            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Slide Berikutnya</div>
                                            <div class="mt-2 text-sm leading-6 text-slate-300">
                                                Lanjut ke bahan agenda sesuai urutan upload.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="grid gap-4">
                                    <div class="meeting-presentation-panel-strong rounded-[1.75rem] p-5 lg:p-6">
                                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                                            <div>
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="meeting-presentation-nav-pill">Agenda {{ $slide['agenda_no'] }}</span>
                                                    <span class="meeting-presentation-nav-pill">Bahan {{ $slide['material_no'] }}</span>
                                                    <span class="meeting-presentation-nav-pill">{{ $slide['viewer_label'] }}</span>
                                                    @if ($slide['file_extension'])
                                                        <span class="meeting-presentation-nav-pill">{{ $slide['file_extension'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="mt-4 text-2xl font-semibold text-white">
                                                    {{ $slide['agenda_title'] }}
                                                </div>
                                                <div class="mt-2 text-base text-slate-300">
                                                    {{ $slide['material_name'] }}
                                                </div>
                                                @if ($slide['agenda_description'])
                                                    <div class="mt-3 text-sm leading-6 text-slate-400">
                                                        {{ $slide['agenda_description'] }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="meeting-presentation-panel rounded-2xl p-4 text-sm text-slate-300">
                                                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                                    Metadata Bahan
                                                </div>
                                                <div class="mt-4 grid gap-3">
                                                    <div><span class="text-slate-400">PIC:</span> {{ $slide['agenda_pic'] ?? '-' }}</div>
                                                    <div><span class="text-slate-400">Direktorat:</span> {{ $slide['agenda_owner'] ?? '-' }}</div>
                                                    <div><span class="text-slate-400">Upload:</span> {{ $slide['uploaded_at'] ?? '-' }}</div>
                                                    @if ($slide['source_reference'])
                                                        <div><span class="text-slate-400">Referensi:</span> {{ $slide['source_reference'] }}</div>
                                                    @endif
                                                    @if ($slide['source_meeting'])
                                                        <div><span class="text-slate-400">Sumber rapat:</span> {{ $slide['source_meeting'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="meeting-presentation-panel rounded-[1.75rem] p-4 lg:p-5">
                                        @if ($slide['viewer_type'] === 'image')
                                            <img
                                                src="{{ $slide['material_url'] }}"
                                                alt="{{ $slide['material_name'] }}"
                                                class="meeting-presentation-image rounded-2xl bg-slate-950">
                                        @elseif ($slide['viewer_type'] === 'pdf')
                                            <iframe
                                                src="{{ $slide['material_url'] }}#toolbar=0&navpanes=0&view=FitH"
                                                class="meeting-presentation-frame">
                                            </iframe>
                                        @else
                                            <div class="flex min-h-[68vh] items-center justify-center">
                                                <div class="meeting-presentation-panel-strong max-w-xl rounded-[1.75rem] p-8 text-center">
                                                    <div class="meeting-presentation-nav-pill">Preview tidak tersedia</div>
                                                    <div class="mt-4 text-xl font-semibold text-white">
                                                        {{ $slide['material_name'] }}
                                                    </div>
                                                    <div class="mt-3 text-sm leading-7 text-slate-300">
                                                        Browser tidak bisa merender file ini secara inline. Untuk hasil presentasi yang paling rapih,
                                                        upload file dalam format PDF atau gambar.
                                                    </div>
                                                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                                                        <a href="{{ $slide['material_url'] }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="btn btn-primary">
                                                            Buka File
                                                        </a>
                                                        <span class="meeting-presentation-nav-pill">
                                                            {{ $slide['file_extension'] ?? 'FILE' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const previousZoom = document.body.style.zoom || '';
            document.body.style.setProperty('zoom', '100%', 'important');

            window.addEventListener('pagehide', function() {
                if (previousZoom) {
                    document.body.style.zoom = previousZoom;
                    return;
                }

                document.body.style.removeProperty('zoom');
            });

            const slides = Array.from(document.querySelectorAll('.js-presentation-slide'));
            const navButtons = Array.from(document.querySelectorAll('.js-presentation-nav'));
            const counter = document.getElementById('presentation-current-counter');
            const label = document.getElementById('presentation-current-label');
            const caption = document.getElementById('presentation-current-caption');
            const prevButton = document.getElementById('presentation-prev');
            const nextButton = document.getElementById('presentation-next');
            const fullscreenButton = document.getElementById('presentation-fullscreen');

            let activeIndex = 0;

            function renderSlide(index) {
                if (index < 0 || index >= slides.length) {
                    return;
                }

                activeIndex = index;

                slides.forEach(function(slide, slideIndex) {
                    slide.classList.toggle('hidden', slideIndex !== activeIndex);
                });

                navButtons.forEach(function(button, buttonIndex) {
                    const isActive = buttonIndex === activeIndex;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-current', isActive ? 'true' : 'false');
                });

                const activeSlide = slides[activeIndex];
                const activeButton = navButtons[activeIndex];

                counter.textContent = 'Slide ' + (activeIndex + 1) + ' / ' + slides.length;
                label.textContent = activeSlide?.dataset.navLabel || 'Slide';
                caption.textContent = activeSlide?.dataset.navCaption || '';

                prevButton.disabled = activeIndex === 0;
                nextButton.disabled = activeIndex === slides.length - 1;

                activeButton?.scrollIntoView({
                    block: 'nearest'
                });
            }

            navButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    renderSlide(Number(button.dataset.index || 0));
                });
            });

            prevButton.addEventListener('click', function() {
                renderSlide(activeIndex - 1);
            });

            nextButton.addEventListener('click', function() {
                renderSlide(activeIndex + 1);
            });

            fullscreenButton.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen?.();
                    return;
                }

                document.exitFullscreen?.();
            });

            document.addEventListener('keydown', function(event) {
                const tagName = document.activeElement?.tagName || '';
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)) {
                    return;
                }

                if (event.key === 'ArrowRight' || event.key === 'PageDown') {
                    event.preventDefault();
                    renderSlide(activeIndex + 1);
                }

                if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
                    event.preventDefault();
                    renderSlide(activeIndex - 1);
                }

                if (event.key === 'Home') {
                    event.preventDefault();
                    renderSlide(0);
                }

                if (event.key === 'End') {
                    event.preventDefault();
                    renderSlide(slides.length - 1);
                }

                if (event.key.toLowerCase() === 'f') {
                    event.preventDefault();
                    fullscreenButton.click();
                }
            });

            renderSlide(0);
        });
    </script>
@endpush
