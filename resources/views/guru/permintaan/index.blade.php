@extends('layouts.app')

@section('content')
@php
  $daftar   = collect($permintaanList ?? []);
  $hitung   = [
    'semua'     => $daftar->count(),
    'menunggu'  => $daftar->filter(fn ($p) => ($p['status'] ?? 'menunggu') === 'menunggu')->count(),
    'disetujui' => $daftar->filter(fn ($p) => ($p['status'] ?? '') === 'disetujui')->count(),
    'ditolak'   => $daftar->filter(fn ($p) => ($p['status'] ?? '') === 'ditolak')->count(),
  ];
  $tabs = [
    'semua'     => 'Semua',
    'menunggu'  => 'Menunggu',
    'disetujui' => 'Disetujui',
    'ditolak'   => 'Ditolak',
  ];
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  .req-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .req-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }

  /* Jalur kelas: titik asal -> titik tujuan */
  .jalur { display: flex; align-items: center; gap: .6rem; }
  .jalur-garis {
    flex: 1; min-width: 2rem; height: 2px; position: relative;
    background: repeating-linear-gradient(90deg, #c7d2fe 0 6px, transparent 6px 11px);
  }
  .jalur-garis::after {
    content: ''; position: absolute; right: -1px; top: -4px;
    border-left: 7px solid #6366f1; border-top: 5px solid transparent; border-bottom: 5px solid transparent;
  }
  .form-tolak { display: none; }
  .form-tolak.terbuka { display: block; }
</style>

<div class="req-wrap max-w-4xl mx-auto pb-16">

  <!-- Header -->
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <a href="{{ route('guru.ringkasan') }}"
         class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg transition-colors">
        <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke ringkasan
      </a>
      <h1 class="req-display mt-3 text-3xl md:text-4xl font-bold text-slate-900">Permintaan murid</h1>
      <p class="mt-1.5 text-sm text-slate-500 max-w-md">
        Murid yang ingin pindah tingkat kelas. Setujui atau tolak, murid akan melihat hasilnya.
      </p>
    </div>

    @if($hitung['menunggu'] > 0)
      <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-amber-50 border border-amber-100">
        <span class="req-display text-2xl font-bold text-amber-600 leading-none">{{ $hitung['menunggu'] }}</span>
        <span class="text-xs text-amber-700 leading-tight">permintaan<br>menunggu keputusan</span>
      </div>
    @endif
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

  <!-- Filter status -->
  <div class="mt-8 flex flex-wrap gap-2" role="tablist" aria-label="Filter status permintaan">
    @foreach($tabs as $kunci => $label)
      <button type="button" role="tab" data-filter="{{ $kunci }}"
              aria-selected="{{ $kunci === 'semua' ? 'true' : 'false' }}"
              class="filter-btn inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
        {{ $label }}
        <span class="text-xs opacity-70">{{ $hitung[$kunci] }}</span>
      </button>
    @endforeach
  </div>

  <!-- Daftar permintaan -->
  <div id="daftarPermintaan" class="mt-5 space-y-4">
    @forelse($daftar as $item)
      @php
        $status = $item['status'] ?? 'menunggu';
        $nama   = $item['nama_siswa'] ?? '-';
        $inisial = collect(preg_split('/\s+/', trim($nama)))
            ->filter()->take(2)
            ->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))
            ->implode('') ?: '?';

        [$badgeBg, $badgeDot, $badgeLabel] = match($status) {
          'menunggu'  => ['bg-amber-50 text-amber-700',   'bg-amber-500',   'Menunggu'],
          'disetujui' => ['bg-emerald-50 text-emerald-700','bg-emerald-500', 'Disetujui'],
          'ditolak'   => ['bg-rose-50 text-rose-700',     'bg-rose-500',    'Ditolak'],
          default     => ['bg-slate-100 text-slate-600',  'bg-slate-400',   ucfirst($status)],
        };

        $tanggal = null;
        if (!empty($item['dibuat_pada']) && is_object($item['dibuat_pada']) && method_exists($item['dibuat_pada'], 'get')) {
          $tanggal = $item['dibuat_pada']->get()->format('d M Y, H:i');
        }
      @endphp

      <article data-status="{{ $status }}"
               class="kartu bg-white rounded-3xl border border-slate-200/70 shadow-[0_12px_30px_-24px_rgba(30,27,75,.4)] p-6">

        <!-- Baris atas: murid + status -->
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-4 min-w-0">
            <span class="req-display w-12 h-12 rounded-full bg-indigo-950 text-white font-bold flex items-center justify-center shrink-0">
              {{ $inisial }}
            </span>
            <div class="min-w-0">
              <h2 class="text-base font-semibold text-slate-900 truncate">{{ $nama }}</h2>
              <p class="text-sm text-slate-500 truncate">{{ $item['email'] ?? '-' }}</p>
            </div>
          </div>

          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 {{ $badgeBg }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $badgeDot }}"></span> {{ $badgeLabel }}
          </span>
        </div>

        <!-- Jalur pindah kelas -->
        <div class="mt-6 p-4 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="jalur">
            <div class="text-center">
              <p class="text-xs text-slate-400">Kelas saat ini</p>
              <p class="mt-0.5 text-sm font-semibold text-slate-700">{{ $item['kelas_saat_ini'] ?? '-' }}</p>
            </div>
            <div class="jalur-garis" aria-hidden="true"></div>
            <div class="text-center">
              <p class="text-xs text-indigo-400">Kelas diminta</p>
              <p class="mt-0.5 text-sm font-bold text-indigo-700">{{ $item['kelas_diminta'] ?? '-' }}</p>
            </div>
          </div>
        </div>

        <!-- Info tambahan -->
        <div class="mt-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-xs text-slate-400">
          <span class="inline-flex items-center gap-2">
            <i class="fa-regular fa-clock"></i> Diajukan {{ $tanggal ?? '-' }}
          </span>
          @if($status !== 'menunggu' && !empty($item['diproses_oleh']))
            <span class="inline-flex items-center gap-2">
              <i class="fa-regular fa-user"></i> Diproses oleh {{ $item['diproses_oleh'] }}
            </span>
          @endif
        </div>

        @if($status === 'ditolak' && !empty($item['catatan_guru']))
          <div class="mt-4 p-3.5 rounded-2xl bg-rose-50/70 border border-rose-100 text-sm text-rose-800">
            <span class="font-semibold">Catatan guru:</span> {{ $item['catatan_guru'] }}
          </div>
        @endif

        <!-- Aksi (hanya saat menunggu) -->
        @if($status === 'menunggu')
          <div class="mt-5 pt-5 border-t border-slate-100">
            <div class="flex flex-wrap items-center gap-3">
              <form method="POST" action="{{ route('guru.permintaan.setujui', $item['id']) }}">
                @csrf
                <button type="submit"
                        data-kelas="{{ $item['kelas_diminta'] ?? '' }}"
                        onclick="return confirm('Setujui pindah ke ' + this.dataset.kelas + '?')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
                  <i class="fa-solid fa-check text-xs"></i> Setujui
                </button>
              </form>

              <button type="button"
                      data-target="tolak-form-{{ $item['id'] }}"
                      aria-expanded="false"
                      class="btn-tolak inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 transition-colors">
                <i class="fa-solid fa-xmark text-xs"></i> Tolak
              </button>
            </div>

            <form id="tolak-form-{{ $item['id'] }}" method="POST"
                  action="{{ route('guru.permintaan.tolak', $item['id']) }}"
                  class="form-tolak mt-4 max-w-md">
              @csrf
              <label for="catatan-{{ $item['id'] }}" class="block text-xs text-slate-500 mb-1.5">
                Alasan penolakan (opsional)
              </label>
              <textarea id="catatan-{{ $item['id'] }}" name="catatan_guru" rows="3"
                        placeholder="Contoh: nilai belum memenuhi syarat untuk naik kelas"
                        class="w-full text-sm text-slate-800 placeholder-slate-300 border border-slate-200 rounded-2xl p-3 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:border-rose-300"></textarea>
              <button type="submit"
                      class="mt-2 inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-2 transition-colors">
                Kirim penolakan
              </button>
            </form>
          </div>
        @endif
      </article>
    @empty
      <div class="text-center py-16 px-6 bg-white rounded-3xl border border-dashed border-slate-200">
        <span class="inline-flex w-14 h-14 rounded-full bg-indigo-50 text-indigo-500 items-center justify-center">
          <i class="fa-regular fa-inbox text-xl"></i>
        </span>
        <h2 class="req-display mt-4 text-xl font-bold text-slate-800">Belum ada permintaan</h2>
        <p class="mt-1 text-sm text-slate-500">Permintaan pindah kelas dari murid akan muncul di sini.</p>
      </div>
    @endforelse

    <!-- Pesan saat filter tidak menemukan apa pun -->
    <div id="kosongFilter" class="hidden text-center py-12 px-6 bg-white rounded-3xl border border-dashed border-slate-200 text-sm text-slate-500">
      Tidak ada permintaan dengan status ini.
    </div>
  </div>
</div>

<script>
  (function () {
    // Gaya tombol filter (aktif / tidak aktif)
    var tombol = document.querySelectorAll('.filter-btn');
    var kartu  = document.querySelectorAll('.kartu');
    var kosong = document.getElementById('kosongFilter');
    var aktif  = ['bg-indigo-950', 'text-white', 'border-indigo-950'];
    var pasif  = ['bg-white', 'text-slate-600', 'border-slate-200', 'hover:border-indigo-300'];

    function gayaTombol(el, hidup) {
      aktif.forEach(function (c) { el.classList.toggle(c, hidup); });
      pasif.forEach(function (c) { el.classList.toggle(c, !hidup); });
      el.setAttribute('aria-selected', hidup ? 'true' : 'false');
    }

    function terapkan(filter) {
      var terlihat = 0;
      kartu.forEach(function (k) {
        var cocok = filter === 'semua' || k.dataset.status === filter;
        k.classList.toggle('hidden', !cocok);
        if (cocok) terlihat++;
      });
      if (kosong && kartu.length) kosong.classList.toggle('hidden', terlihat > 0);
      tombol.forEach(function (t) { gayaTombol(t, t.dataset.filter === filter); });
    }

    tombol.forEach(function (t) {
      t.addEventListener('click', function () { terapkan(t.dataset.filter); });
    });

    // Buka tutup form penolakan
    document.querySelectorAll('.btn-tolak').forEach(function (b) {
      b.addEventListener('click', function () {
        var form = document.getElementById(b.dataset.target);
        var buka = !form.classList.contains('terbuka');
        form.classList.toggle('terbuka', buka);
        b.setAttribute('aria-expanded', buka ? 'true' : 'false');
        if (buka) form.querySelector('textarea').focus();
      });
    });

    terapkan('semua');
  })();
</script>
@endsection