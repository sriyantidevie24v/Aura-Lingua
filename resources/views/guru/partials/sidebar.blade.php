@php
  $namaGuru  = $guru['namaGuru'] ?? 'Guru';
  $emailGuru = $guru['emailGuru'] ?? '';
  $inisial   = collect(preg_split('/\s+/', trim($namaGuru)))
      ->filter()->take(2)
      ->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))
      ->implode('') ?: 'GU';

  // Daftar menu: ubah di sini, tampilan ikut otomatis
  $grupMenu = [
    'Belajar & konten' => [
      ['label' => 'Ringkasan',      'route' => 'guru.ringkasan',            'match' => 'guru.ringkasan',     'icon' => 'fa-chart-simple'],
      ['label' => 'Materi',         'route' => 'guru.materi',               'match' => 'guru.materi*',       'icon' => 'fa-book'],
      ['label' => 'Bank soal',      'route' => 'guru.kuis',                 'match' => 'guru.kuis*',         'icon' => 'fa-circle-question'],
      ['label' => 'Kamus vokasi',   'route' => 'guru.kamus_vokasi.index',   'match' => 'guru.kamus_vokasi*', 'icon' => 'fa-book-bookmark'],
    ],
    'Murid & akun' => [
      ['label' => 'Permintaan murid',  'route' => 'guru.permintaan.index', 'match' => 'guru.permintaan*', 'icon' => 'fa-user-clock', 'badge' => $totalPermintaanMenunggu ?? 0],
      ['label' => 'Kelola kode akses', 'route' => 'guru.kode-akses.edit',  'match' => 'guru.kode-akses*', 'icon' => 'fa-key'],
    ],
  ];
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  .sb-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .sb-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }
  .sb-aura {
    background: conic-gradient(from 0deg, #6366f1, #a78bfa, #f0abfc, #fbbf24, #34d399, #6366f1);
  }
  .sb-wrap nav a.aktif::before {
    content: ''; position: absolute; left: -1.25rem; top: 50%; transform: translateY(-50%);
    width: 4px; height: 1.25rem; border-radius: 0 4px 4px 0; background: #fbbf24;
  }
  .sb-scroll { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.15) transparent; }
</style>

<aside class="sb-wrap bg-gradient-to-b from-[#1e1b4b] to-[#2a2670] text-indigo-100 p-5 flex flex-col sticky top-0 h-screen">

  <!-- Brand -->
  <a href="{{ route('guru.ringkasan') }}"
     class="flex items-center gap-3 px-2 pb-7 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 rounded-xl">
    <span class="sb-aura w-10 h-10 rounded-full p-[3px] shrink-0" aria-hidden="true">
      <span class="sb-display w-full h-full rounded-full bg-[#1e1b4b] text-white text-sm flex items-center justify-center">AL</span>
    </span>
    <span>
      <span class="sb-display block text-lg text-white leading-none">Aura Lingua</span>
      <span class="block mt-1 text-xs text-indigo-300">Panel guru</span>
    </span>
  </a>

  <!-- Menu -->
  <div class="sb-scroll flex-1 overflow-y-auto -mr-2 pr-2 space-y-6">
    @foreach($grupMenu as $judul => $menu)
      <div>
        <p class="px-3 mb-2 text-xs font-medium text-indigo-400">{{ $judul }}</p>
        <nav class="flex flex-col gap-1" aria-label="{{ $judul }}">
          @foreach($menu as $m)
            @php $aktif = request()->routeIs($m['match']); @endphp
            <a href="{{ route($m['route']) }}"
               @if($aktif) aria-current="page" @endif
               class="relative flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300
                      {{ $aktif ? 'aktif bg-white/10 text-white' : 'text-indigo-200 hover:bg-white/5 hover:text-white' }}">
              <span class="flex items-center gap-3">
                <i class="fa-solid {{ $m['icon'] }} w-4 text-center {{ $aktif ? 'text-amber-300' : 'text-indigo-300' }}"></i>
                {{ $m['label'] }}
              </span>
              @if(!empty($m['badge']))
                <span class="min-w-[1.4rem] px-1.5 py-0.5 bg-amber-400 text-[#1e1b4b] rounded-full text-[11px] font-bold text-center"
                      title="{{ $m['badge'] }} menunggu keputusan">
                  {{ $m['badge'] }}
                </span>
              @endif
            </a>
          @endforeach
        </nav>
      </div>
    @endforeach
  </div>

  <!-- Profil guru -->
  <div class="pt-4 mt-4 border-t border-white/10">
    <a href="{{ route('guru.profil') }}"
       @if(request()->routeIs('guru.profil')) aria-current="page" @endif
       class="group flex items-center gap-3 px-2 py-2 rounded-2xl transition-colors hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300
              {{ request()->routeIs('guru.profil') ? 'bg-white/10' : '' }}">
      <span class="sb-aura w-10 h-10 rounded-full p-[2px] shrink-0" aria-hidden="true">
        <span class="sb-display w-full h-full rounded-full bg-[#1e1b4b] text-white text-xs flex items-center justify-center">{{ $inisial }}</span>
      </span>
      <span class="min-w-0 flex-1">
        <span class="block text-sm font-semibold text-white truncate">{{ $namaGuru }}</span>
        @if($emailGuru)
          <span class="block text-xs text-indigo-300 truncate">{{ $emailGuru }}</span>
        @endif
      </span>
      <i class="fa-solid fa-chevron-right text-[10px] text-indigo-400 group-hover:text-white group-hover:translate-x-0.5 transition"></i>
    </a>
  </div>
</aside>