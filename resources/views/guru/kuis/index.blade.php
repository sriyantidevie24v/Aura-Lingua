@extends('layouts.app')

@section('content')
@php
  $allItems    = collect($kuisList)->merge(collect($kuisSuaraList));
  $kelasCounts = $allItems->countBy(fn ($i) => $i['tingkatan_kelas'] ?? 'X');
  $kesulitanCounts = $allItems->countBy(fn ($i) => $i['tingkatan_kesulitan'] ?? '');
  $itemsByBab  = $allItems->groupBy(fn ($i) => $i['bab'] ?? 'Tanpa Bab');

  foreach ($activeChapters as $chapName => $chapData) {
      if (!$itemsByBab->has($chapName)) {
          $itemsByBab[$chapName] = collect();
      }
  }

  $totalKuis  = $allItems->where('tipe', 'kuis')->count();
  $totalSuara = $allItems->where('tipe', 'suara')->count();
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  .kq-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .kq-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }
  .modal-overlay { display: none; }
  .modal-overlay.is-open { display: flex; }
  details.bab-section > summary { list-style: none; }
  details.bab-section > summary::-webkit-details-marker { display: none; }
  details.bab-section[open] .chevron { transform: rotate(180deg); }
  .chevron { transition: transform .15s ease; }
  @media (prefers-reduced-motion: reduce) { .chevron { transition: none; } }
</style>

<div class="kq-wrap max-w-5xl mx-auto pb-16">

  <!-- Header -->
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="kq-display text-3xl md:text-4xl font-bold text-slate-900">Bank soal</h1>
      <p class="mt-1.5 text-sm text-slate-500 max-w-lg">
        Kumpulan soal latihan untuk siswa, dikelompokkan per bab materi.
        Total {{ $totalKuis }} soal pilihan ganda dan {{ $totalSuara }} tes pelafalan.
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" onclick="openChooser('')"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
        <i class="fa-solid fa-plus text-xs"></i> Tambah soal
      </button>
    <button type="button" id="guide-open" onclick="toggleGuide(true)" hidden
            class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 hover:border-indigo-300 hover:text-indigo-700 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
      <i class="fa-regular fa-circle-question text-xs"></i> Lihat panduan
    </button>
    </div>
  </div>

  <!-- Notifikasi -->
  @if(session('success'))
    <div role="status" class="mt-6 flex items-start gap-3 p-4 bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm rounded-2xl">
      <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i> <span>{{ session('success') }}</span>
    </div>
  @endif
  @if(session('error'))
    <div role="alert" class="mt-6 flex items-start gap-3 p-4 bg-rose-50 border border-rose-100 text-rose-800 text-sm rounded-2xl">
      <i class="fa-solid fa-circle-exclamation mt-0.5 text-rose-500"></i> <span>{{ session('error') }}</span>
    </div>
  @endif

  <!-- Panduan singkat -->
  {{-- <section id="guide" class="mt-6 bg-indigo-950 text-white rounded-3xl p-6 md:p-7" aria-labelledby="guide-title">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 id="guide-title" class="kq-display text-xl font-bold">Cara menambah soal</h2>
        <p class="mt-1 text-sm text-indigo-200">Cukup tiga langkah. Semua dilakukan di dalam kotak bab.</p>
      </div>
      <button type="button" onclick="toggleGuide(false)" aria-label="Tutup panduan"
              class="shrink-0 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-white transition-colors">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>

    <ol class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-3">
      <li class="rounded-2xl bg-white/10 p-4">
        <p class="text-sm font-bold">1. Buka bab yang ingin diisi</p>
        <p class="mt-1 text-sm text-indigo-200">Klik nama bab di bawah. Kotaknya akan terbuka.</p>
      </li>
      <li class="rounded-2xl bg-white/10 p-4">
        <p class="text-sm font-bold">2. Pilih cara membuat soal</p>
        <p class="mt-1 text-sm text-indigo-200">
          <span class="font-semibold text-white">Buat dengan AI</span> kalau ingin cepat, atau
          <span class="font-semibold text-white">tulis sendiri</span> kalau ingin soal persis seperti keinginan Bapak/Ibu.
        </p>
      </li>
      <li class="rounded-2xl bg-white/10 p-4">
        <p class="text-sm font-bold">3. Periksa hasilnya</p>
        <p class="mt-1 text-sm text-indigo-200">
          Jawaban benar ditandai hijau. Kalau ada yang kurang pas, klik ikon pensil untuk mengubahnya.
        </p>
      </li>
    </ol>
  </section> --}}

  <!-- Filter -->
  <div class="mt-6 bg-white border border-slate-200/70 rounded-3xl p-5">
    <div class="relative max-w-md">
      <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
      <label for="search-input" class="sr-only">Cari soal</label>
      <input type="search" id="search-input" oninput="applyFilters()" placeholder="Ketik kata dari soal yang dicari"
             class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-full text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <p class="text-xs font-semibold text-slate-500 mb-2" id="kelas-label">Kelas</p>
        <div class="flex flex-wrap items-center gap-2" id="kelas-filter-group" role="group" aria-labelledby="kelas-label">
          <button type="button" data-value="all" onclick="setKelasFilter('all', this)"
                  class="filter-btn px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            Semua
          </button>
          @foreach (['X', 'XI', 'XII'] as $kelas)
            <button type="button" data-value="{{ $kelas }}" onclick="setKelasFilter(this.dataset.value, this)"
                    class="filter-btn px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
              {{ $kelas }} <span class="text-xs opacity-70 ml-0.5">({{ $kelasCounts[$kelas] ?? 0 }})</span>
            </button>
          @endforeach
        </div>
      </div>

      <div>
        <p class="text-xs font-semibold text-slate-500 mb-2" id="kesulitan-label">Tingkat kesulitan</p>
        <div class="flex flex-wrap items-center gap-2" id="kesulitan-filter-group" role="group" aria-labelledby="kesulitan-label">
          <button type="button" data-value="all" onclick="setKesulitanFilter('all', this)"
                  class="filter-btn px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            Semua
          </button>
          @foreach (['Mudah', 'Sedang', 'Sulit'] as $tk)
            <button type="button" data-value="{{ $tk }}" onclick="setKesulitanFilter(this.dataset.value, this)"
                    class="filter-btn px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
              {{ $tk }} <span class="text-xs opacity-70 ml-0.5">({{ $kesulitanCounts[$tk] ?? 0 }})</span>
            </button>
          @endforeach
        </div>
      </div>
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="tipe-select" class="block text-xs font-semibold text-slate-500 mb-2">Jenis soal</label>
        <select id="tipe-select" onchange="applyFilters()"
                class="w-full px-4 py-2 bg-white border border-slate-200 rounded-full text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="all">Semua jenis</option>
          <option value="kuis">Pilihan ganda</option>
          <option value="suara">Tes pelafalan</option>
        </select>
      </div>

      <div>
        <label for="bab-select" class="block text-xs font-semibold text-slate-500 mb-2">Bab</label>
        <select id="bab-select" onchange="applyFilters()"
                class="w-full px-4 py-2 bg-white border border-slate-200 rounded-full text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="all">Semua bab</option>
          @foreach ($activeChapters as $chapName => $chapData)
            <option value="{{ $chapName }}">{{ $chapName }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <p id="result-count" class="mt-4 text-sm text-slate-500" role="status" aria-live="polite"></p>
  </div>

  <!-- Daftar per bab -->
  <div class="mt-6 space-y-4">
    @forelse ($itemsByBab as $babName => $items)
      @php
        $suaraItems = $items->where('tipe', 'suara');
        $kuisItems  = $items->where('tipe', 'kuis');
        $no = 1;
      @endphp

      <details class="bab-section group bg-white border border-slate-200/70 rounded-3xl shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]"
               data-bab-section="{{ $babName }}" @if($loop->first) open @endif>

        <!-- Header bab (klik untuk buka/tutup) -->
        <summary class="cursor-pointer select-none flex items-center justify-between gap-4 p-5 md:p-6 rounded-3xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
          <div class="min-w-0">
            <h2 class="kq-display text-lg md:text-xl font-bold text-slate-900 break-words">{{ $babName }}</h2>
            <p class="mt-1 text-sm text-slate-500">
              @if($items->isEmpty())
                <span class="text-amber-700 font-medium">Belum ada soal</span>
              @else
                {{ $kuisItems->count() }} pilihan ganda, {{ $suaraItems->count() }} tes pelafalan
              @endif
            </p>
          </div>
          <span class="shrink-0 inline-flex items-center gap-2 text-sm text-slate-500">
            <span class="hidden sm:inline">Klik untuk membuka</span>
            <i class="chevron fa-solid fa-chevron-down text-xs"></i>
          </span>
        </summary>

        <div class="px-5 md:px-6 pb-6">

          @if($items->isEmpty())
            <div class="text-center py-8">
              <span class="inline-flex w-12 h-12 rounded-full bg-indigo-50 text-indigo-500 items-center justify-center">
                <i class="fa-regular fa-circle-question"></i>
              </span>
              <p class="mt-3 text-sm font-semibold text-slate-700">Bab ini belum punya soal.</p>
              <p class="text-sm text-slate-500">Mulai dengan tombol "Tambah soal" di bagian atas halaman.</p>
            </div>
          @else
            <div class="mt-5 space-y-3">

              <!-- Tes pelafalan -->
              @foreach($suaraItems as $item)
                <article class="kuis-card rounded-2xl border border-slate-100 bg-slate-50/60 p-5"
                         data-kelas="{{ $item['tingkatan_kelas'] ?? 'X' }}"
                         data-kesulitan="{{ $item['tingkatan_kesulitan'] ?? '' }}"
                         data-bab="{{ $babName }}"
                         data-tipe="suara"
                         data-text="{{ strtolower(($item['teks_target'] ?? '') . ' ' . ($item['panduan_arti'] ?? '')) }}">
                  <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="w-7 h-7 rounded-full bg-white border border-slate-200 flex items-center justify-center text-xs font-semibold text-slate-600">{{ $no++ }}</span>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-violet-50 text-violet-700 text-xs font-semibold">
                        <i class="fa-solid fa-microphone text-[10px]"></i> Tes pelafalan
                      </span>
                      @if(!empty($item['tingkatan_kesulitan']))
                        <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-500 text-xs">{{ $item['tingkatan_kesulitan'] }}</span>
                      @endif
                      <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-500 text-xs">Kelas {{ $item['tingkatan_kelas'] ?? 'X' }}</span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <a href="{{ route('guru.kuis_suara.edit', $item['id']) }}" aria-label="Ubah tes pelafalan" title="Ubah"
                         class="w-9 h-9 rounded-full bg-white border border-slate-200 hover:border-indigo-300 hover:text-indigo-700 text-slate-500 flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
                        <i class="fa-regular fa-pen-to-square text-xs"></i>
                      </a>
                      <form action="{{ route('guru.kuis_suara.destroy', $item['id']) }}" method="POST" onsubmit="return confirm('Hapus tes pelafalan ini? Soal yang dihapus tidak bisa dikembalikan.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" aria-label="Hapus tes pelafalan" title="Hapus"
                                class="w-9 h-9 rounded-full bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-slate-500 flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 transition-colors">
                          <i class="fa-regular fa-trash-can text-xs"></i>
                        </button>
                      </form>
                    </div>
                  </div>

                  <p class="mt-4 text-xs font-semibold text-slate-500">Siswa diminta mengucapkan</p>
                  <p class="kq-display mt-0.5 text-xl font-bold text-slate-900 break-words">{{ $item['teks_target'] ?? '-' }}</p>
                  <p class="mt-1 text-sm text-slate-500">Artinya: {{ $item['panduan_arti'] ?? '-' }}</p>
                </article>
              @endforeach

              <!-- Pilihan ganda -->
              @foreach($kuisItems as $item)
                @php
                  $ansIndex = $item['jawaban_benar'] ?? 0;
                  $letters  = ['A', 'B', 'C'];
                  $konteks  = trim((string) ($item['konteks_bacaan'] ?? ''));
                @endphp
                <article class="kuis-card rounded-2xl border border-slate-100 bg-slate-50/60 p-5"
                         data-kelas="{{ $item['tingkatan_kelas'] ?? 'X' }}"
                         data-kesulitan="{{ $item['tingkatan_kesulitan'] ?? '' }}"
                         data-bab="{{ $babName }}"
                         data-tipe="kuis"
                         data-text="{{ strtolower($konteks . ' ' . ($item['pertanyaan'] ?? '') . ' ' . implode(' ', $item['pilih_jawaban'] ?? [])) }}">
                  <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="w-7 h-7 rounded-full bg-white border border-slate-200 flex items-center justify-center text-xs font-semibold text-slate-600">{{ $no++ }}</span>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold">
                        <i class="fa-solid fa-list-check text-[10px]"></i> Pilihan ganda
                      </span>
                      @if($konteks !== '')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                          <i class="fa-solid fa-book-open text-[10px]"></i> Ada bacaan
                        </span>
                      @endif
                      @if(!empty($item['tingkatan_kesulitan']))
                        <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-500 text-xs">{{ $item['tingkatan_kesulitan'] }}</span>
                      @endif
                      <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-500 text-xs">Kelas {{ $item['tingkatan_kelas'] ?? 'X' }}</span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <a href="{{ route('guru.kuis.edit', $item['id']) }}" aria-label="Ubah soal" title="Ubah"
                         class="w-9 h-9 rounded-full bg-white border border-slate-200 hover:border-indigo-300 hover:text-indigo-700 text-slate-500 flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
                        <i class="fa-regular fa-pen-to-square text-xs"></i>
                      </a>
                      <form action="{{ route('guru.kuis.destroy', $item['id']) }}" method="POST" onsubmit="return confirm('Hapus soal ini? Soal yang dihapus tidak bisa dikembalikan.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" aria-label="Hapus soal" title="Hapus"
                                class="w-9 h-9 rounded-full bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-slate-500 flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 transition-colors">
                          <i class="fa-regular fa-trash-can text-xs"></i>
                        </button>
                      </form>
                    </div>
                  </div>

                  @if($konteks !== '')
                    <div class="mt-4 p-4 rounded-xl bg-amber-50/70 border border-amber-100">
                      <p class="flex items-center gap-1.5 text-xs font-bold text-amber-800 mb-1.5">
                        <i class="fa-solid fa-book-open text-[11px]"></i> Bacaan untuk soal ini
                      </p>
                      <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words">{{ $konteks }}</p>
                    </div>
                  @endif

                  <p class="mt-4 text-[15px] font-semibold text-slate-900">{{ $item['pertanyaan'] ?? '-' }}</p>

                  <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
                    @foreach($item['pilih_jawaban'] ?? [] as $index => $pilihan)
                      @php $isCorrect = ($index == $ansIndex); @endphp
                      <div class="flex items-center gap-2.5 p-3 rounded-xl border text-sm {{ $isCorrect ? 'bg-emerald-50 border-emerald-200 text-emerald-900 font-semibold' : 'bg-white border-slate-200 text-slate-600' }}">
                        <span class="w-6 h-6 rounded-full shrink-0 flex items-center justify-center text-xs font-bold {{ $isCorrect ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                          {{ $letters[$index] ?? $index }}
                        </span>
                        <span class="min-w-0 break-words flex-1">{{ $pilihan }}</span>
                        @if($isCorrect)
                          <span class="shrink-0 text-xs font-bold text-emerald-700"><i class="fa-solid fa-check"></i> Benar</span>
                        @endif
                      </div>
                    @endforeach
                  </div>
                </article>
              @endforeach
            </div>
          @endif
        </div>
      </details>
    @empty
      <div class="text-center py-16 px-6 bg-white rounded-3xl border border-dashed border-slate-200">
        <span class="inline-flex w-14 h-14 rounded-full bg-indigo-50 text-indigo-500 items-center justify-center">
          <i class="fa-solid fa-book text-xl"></i>
        </span>
        <h2 class="kq-display mt-4 text-xl font-bold text-slate-800">Belum ada bab materi</h2>
        <p class="mt-1 text-sm text-slate-500">Tambahkan materi bab terlebih dahulu agar soal bisa dibuat.</p>
      </div>
    @endforelse

    <div id="empty-state" class="hidden text-center py-12 px-6 bg-white rounded-3xl border border-dashed border-slate-200 text-sm text-slate-500">
      Tidak ada soal yang cocok. Coba ganti kata pencarian atau ubah pilihan filter di atas.
    </div>
  </div>
</div>

<!-- Modal: pilih bab dan cara menambah soal -->
<div id="chooserModal" class="modal-overlay fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="chooserJudul" onclick="if (event.target === this) closeChooser()">
  <div class="w-full max-w-lg bg-white rounded-3xl p-6 md:p-7 shadow-2xl max-h-[90vh] overflow-y-auto">
    <h2 id="chooserJudul" class="kq-display text-xl font-bold text-slate-900">Tambah soal</h2>
    <p class="mt-1 text-sm text-slate-500">Pilih bab dulu, lalu pilih cara menambah soalnya.</p>

    <div class="mt-6">
      <label for="chooserBab" class="block text-sm font-bold text-slate-800 mb-2">1. Untuk bab mana?</label>
      <select id="chooserBab"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-[15px] text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">
        <option value="">Pilih bab materi</option>
        @foreach ($activeChapters as $chapName => $chapData)
          <option value="{{ $chapName }}">{{ $chapName }}</option>
        @endforeach
      </select>
    </div>

    <div class="mt-6">
      <p class="text-sm font-bold text-slate-800">2. Mau menambah dengan cara apa?</p>
      <div class="mt-2 space-y-3">
        <button type="button" onclick="pilihJenis('kuis')"
                class="w-full text-left p-4 rounded-2xl bg-white border border-slate-200 hover:border-indigo-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
          <span class="flex items-center gap-2 text-sm font-bold text-slate-800">
            <i class="fa-solid fa-pen text-xs text-indigo-600"></i> Tulis soal pilihan ganda
          </span>
          <span class="mt-1 block text-xs text-slate-500">Isi sendiri, atau minta bantuan AI di halaman berikutnya. Soal bisa diperiksa dulu sebelum disimpan.</span>
        </button>

        <button type="button" onclick="pilihJenis('suara')"
                class="w-full text-left p-4 rounded-2xl bg-white border border-slate-200 hover:border-indigo-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
          <span class="flex items-center gap-2 text-sm font-bold text-slate-800">
            <i class="fa-solid fa-microphone text-xs text-violet-600"></i> Tulis tes pelafalan
          </span>
          <span class="mt-1 block text-xs text-slate-500">Siswa mengucapkan kata atau kalimat yang Bapak/Ibu tentukan. Bisa dibantu AI di halaman berikutnya.</span>
        </button>
      </div>
    </div>

    <p id="chooserError" class="mt-4 text-sm text-rose-600" role="alert" aria-live="assertive"></p>

    <div class="mt-4 flex justify-end">
      <button type="button" onclick="closeChooser()"
              class="px-5 py-2.5 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
        Batal
      </button>
    </div>
  </div>
</div>

<script>
  let activeFilters = { kelas: 'all', kesulitan: 'all', bab: 'all', tipe: 'all' };

  const AKTIF = ['bg-indigo-950', 'text-white', 'border-indigo-950'];
  const PASIF = ['bg-white', 'text-slate-600', 'border-slate-200', 'hover:border-indigo-300'];

  function gayaTombol(btn, hidup) {
    AKTIF.forEach(c => btn.classList.toggle(c, hidup));
    PASIF.forEach(c => btn.classList.toggle(c, !hidup));
    btn.setAttribute('aria-pressed', hidup ? 'true' : 'false');
  }

  // ==== Panduan ====
  const GUIDE_KEY = 'bankSoalGuideHidden';

  function toggleGuide(show) {
    const guide = document.getElementById('guide');
    const open = document.getElementById('guide-open');
    // Panduan sedang dinonaktifkan (dikomentari) jadi elemennya bisa tidak ada.
    if (guide) guide.hidden = !show;
    if (open) open.hidden = show || !guide;
    try { localStorage.setItem(GUIDE_KEY, show ? '0' : '1'); } catch (e) {}
  }

  // ==== Filter ====
  function setKelasFilter(value, btn) {
    activeFilters.kelas = value;
    document.querySelectorAll('#kelas-filter-group .filter-btn').forEach(b => gayaTombol(b, b === btn));
    applyFilters();
  }

  function setKesulitanFilter(value, btn) {
    activeFilters.kesulitan = value;
    document.querySelectorAll('#kesulitan-filter-group .filter-btn').forEach(b => gayaTombol(b, b === btn));
    applyFilters();
  }

  function applyFilters() {
    activeFilters.bab = document.getElementById('bab-select').value;
    activeFilters.tipe = document.getElementById('tipe-select').value;
    const search = document.getElementById('search-input').value.trim().toLowerCase();
    const filterAktif = activeFilters.kelas !== 'all' || activeFilters.kesulitan !== 'all'
      || activeFilters.bab !== 'all' || activeFilters.tipe !== 'all' || search !== '';
    let totalVisible = 0;

    document.querySelectorAll('.bab-section').forEach(function (section) {
      const cards = section.querySelectorAll('.kuis-card');

      if (cards.length === 0) {
        const matchBab = (activeFilters.bab === 'all') || (section.dataset.babSection === activeFilters.bab);
        const tampil = matchBab && activeFilters.kelas === 'all' && activeFilters.kesulitan === 'all'
          && activeFilters.tipe === 'all' && search === '';
        section.style.display = tampil ? '' : 'none';
        return;
      }

      let visibleInSection = 0;
      cards.forEach(function (card) {
        const matchKelas = (activeFilters.kelas === 'all') || (card.dataset.kelas === activeFilters.kelas);
        const matchKesulitan = (activeFilters.kesulitan === 'all') || (card.dataset.kesulitan === activeFilters.kesulitan);
        const matchBab = (activeFilters.bab === 'all') || (card.dataset.bab === activeFilters.bab);
        const matchTipe = (activeFilters.tipe === 'all') || (card.dataset.tipe === activeFilters.tipe);
        const matchSearch = (search === '') || card.dataset.text.includes(search);

        if (matchKelas && matchKesulitan && matchBab && matchTipe && matchSearch) {
          card.style.display = '';
          visibleInSection++;
        } else {
          card.style.display = 'none';
        }
      });

      section.style.display = visibleInSection > 0 ? '' : 'none';
      // Saat mencari/menyaring, bab yang cocok langsung dibuka agar hasilnya terlihat
      if (filterAktif && visibleInSection > 0) section.open = true;
      totalVisible += visibleInSection;
    });

    const emptyState = document.getElementById('empty-state');
    if (emptyState) emptyState.classList.toggle('hidden', totalVisible > 0 || !filterAktif);

    document.getElementById('result-count').textContent = filterAktif
      ? 'Menampilkan ' + totalVisible + ' soal yang cocok.'
      : '';
  }

  // ==== Pemilih: bab + cara menambah soal ====
  const URL_KUIS_BARU  = '{{ route("guru.kuis.create") }}';
  const URL_SUARA_BARU = '{{ route("guru.kuis_suara.create") }}';

  function openChooser(babName) {
    const sel = document.getElementById('chooserBab');
    sel.value = babName || '';
    // Kalau hanya ada satu bab, langsung dipilihkan supaya guru tidak perlu memilih lagi.
    if (!sel.value && sel.options.length === 2) sel.selectedIndex = 1;
    document.getElementById('chooserError').textContent = '';
    document.getElementById('chooserModal').classList.add('is-open');
    sel.focus();
  }

  function closeChooser() {
    document.getElementById('chooserModal').classList.remove('is-open');
  }

  function pilihJenis(jenis) {
    const sel = document.getElementById('chooserBab');
    const bab = sel.value;

    if (!bab) {
      document.getElementById('chooserError').textContent = 'Pilih bab dulu di langkah 1.';
      sel.focus();
      return;
    }

    if (jenis === 'kuis') {
      window.location.href = URL_KUIS_BARU + '?materi_id=' + encodeURIComponent(bab);
    } else if (jenis === 'suara') {
      window.location.href = URL_SUARA_BARU + '?materi_id=' + encodeURIComponent(bab);
    }
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeChooser();
  });

  // Keadaan awal
  (function () {
    ['kelas-filter-group', 'kesulitan-filter-group'].forEach(function (id) {
      const awal = document.querySelector('#' + id + ' .filter-btn[data-value="all"]');
      document.querySelectorAll('#' + id + ' .filter-btn').forEach(b => gayaTombol(b, b === awal));
    });

    let sembunyi = false;
    try { sembunyi = localStorage.getItem(GUIDE_KEY) === '1'; } catch (e) {}
    toggleGuide(!sembunyi);
  })();
</script>
@endsection