@extends('layouts.app')

@section('content')
@php
  $daftar = collect($kamusList ?? []);
  $totalKata = $daftar->count();
  // Kelompokkan kosakata per id bab. Bab dari materi yang belum punya kata tetap ditampilkan.
  $perBab = $daftar->groupBy('materi_id');
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  .kv-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .kv-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }

  /* Bab yang bisa dibuka-tutup: isinya mengempis lewat transisi tinggi baris grid. */
  .bab-isi { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .28s ease; }
  .bab-isi-dalam { overflow: hidden; min-height: 0; }
  .bab-section.is-open .bab-isi { grid-template-rows: 1fr; }
  .bab-chevron { transition: transform .28s ease; }
  .bab-section.is-open .bab-chevron { transform: rotate(180deg); }

  .modal-overlay { display: none; }
  .modal-overlay.is-open { display: flex; }

  @media (prefers-reduced-motion: reduce) {
    .bab-isi, .bab-chevron { transition: none; }
  }
</style>

<div class="kv-wrap max-w-6xl mx-auto pb-16">

  <!-- Header -->
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="kv-display text-3xl md:text-4xl font-bold text-slate-900">Kamus vokasi</h1>
      <p class="mt-1.5 text-sm text-slate-500 max-w-md">
        Kosakata bahasa asing per bab materi. Daftar bab mengikuti materi, jadi bab baru otomatis muncul di sini.
      </p>
    </div>
    @if(count($activeChapters) > 0)
      <button type="button" onclick="openChooser('')"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
        <i class="fa-solid fa-plus text-xs"></i> Tambah kosakata
      </button>
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
  @if(($jumlahYatim ?? 0) > 0)
    <div role="status" class="mt-6 flex items-start gap-3 p-4 bg-amber-50 border border-amber-100 text-amber-800 text-sm rounded-2xl">
      <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
      <span>{{ $jumlahYatim }} kosakata disembunyikan karena bab materinya sudah dihapus.</span>
    </div>
  @endif

  @if(count($activeChapters) > 0)
    <!-- Cari & saring -->
    <div class="mt-8 flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[220px] max-w-md">
        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
        <label for="cari" class="sr-only">Cari kosakata</label>
        <input id="cari" type="search" placeholder="Cari kata atau arti"
               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-full text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400">
      </div>

      <div>
        <label for="filterBab" class="sr-only">Saring berdasarkan bab</label>
        <select id="filterBab"
                class="px-4 py-2.5 bg-white border border-slate-200 rounded-full text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400">
          <option value="">Semua bab</option>
          @foreach($activeChapters as $chap)
            <option value="{{ $chap['id'] }}">{{ $chap['judul'] }}</option>
          @endforeach
        </select>
      </div>

      <div class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-1">
        <button type="button" id="bukaSemua"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
          Buka semua
        </button>
        <button type="button" id="tutupSemua"
                class="text-sm font-semibold text-slate-500 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
          Tutup semua
        </button>
        <p class="text-sm text-slate-400">
          <span id="jumlah">{{ $totalKata }}</span> dari {{ $totalKata }} kata
        </p>
      </div>
    </div>

    <!-- Daftar per bab -->
    <div class="mt-5 space-y-5">
      @foreach($activeChapters as $chap)
        @php $items = $perBab->get($chap['id'], collect()); @endphp

        <section class="bab-section bg-white border border-slate-200/70 rounded-3xl p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]"
                 data-bab-id="{{ $chap['id'] }}">

          <!-- Judul bab: klik untuk membuka atau menutup isinya -->
          <h2>
            <button type="button"
                    class="bab-toggle w-full flex items-center justify-between gap-4 text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-4"
                    aria-expanded="false"
                    aria-controls="bab-isi-{{ $loop->index }}">
              <span class="min-w-0">
                <span class="kv-display block text-xl font-bold text-slate-900 break-words">{{ $chap['judul'] }}</span>
                <span class="mt-1 block text-sm font-normal text-slate-500">{{ $items->count() }} kosakata</span>
              </span>
              <span class="shrink-0 flex items-center gap-2 text-sm font-normal text-slate-500">
                <span class="bab-label hidden sm:inline">Klik untuk membuka</span>
                <i class="bab-chevron fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
              </span>
            </button>
          </h2>

          <div id="bab-isi-{{ $loop->index }}" class="bab-isi" inert>
            <div class="bab-isi-dalam">
              <div class="mt-4 pt-5 border-t border-slate-100">

                <!-- Tambah kosakata langsung ke bab ini -->
                <div class="mb-4 flex justify-end">
                  <a href="{{ route('guru.kamus_vokasi.create', ['materi_id' => $chap['id']]) }}"
                     class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
                    <i class="fa-solid fa-plus text-xs"></i> Tambah ke bab ini
                  </a>
                </div>

                @if($items->isEmpty())
                  <div class="text-center py-6">
                    <span class="inline-flex w-12 h-12 rounded-full bg-indigo-50 text-indigo-500 items-center justify-center">
                      <i class="fa-solid fa-book-bookmark"></i>
                    </span>
                    <p class="mt-3 text-sm text-slate-500">Bab ini belum punya kosakata.</p>
                    <p class="text-sm text-slate-400">Pakai tombol "Tambah ke bab ini" di atas.</p>
                  </div>
                @else
                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($items as $item)
                      <article class="kata-kartu bg-slate-50/60 rounded-2xl border border-slate-100 p-5 flex flex-col"
                               data-kata="{{ mb_strtolower($item['kata'] ?? '') }}"
                               data-arti="{{ mb_strtolower($item['arti_id'] ?? '') }}"
                               data-bab="{{ $chap['id'] }}">
                        <h3 class="kv-display text-xl font-bold text-slate-900 break-words">{{ $item['kata'] ?? '-' }}</h3>
                        <p class="mt-1.5 text-[15px] text-slate-600 flex-1">{{ $item['arti_id'] ?? '-' }}</p>

                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
                          <a href="{{ route('guru.kamus_vokasi.edit', $item['id']) }}"
                             class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 hover:border-indigo-300 hover:text-indigo-700 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
                            <i class="fa-regular fa-pen-to-square text-xs"></i> Edit
                          </a>

                          <form action="{{ route('guru.kamus_vokasi.destroy', $item['id']) }}" method="POST"
                                onsubmit="return confirm('Hapus kosakata ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 transition-colors">
                              <i class="fa-regular fa-trash-can text-xs"></i> Hapus
                            </button>
                          </form>
                        </div>
                      </article>
                    @endforeach
                  </div>
                @endif
              </div>
            </div>
          </div>
        </section>
      @endforeach

      <div id="kosong" class="hidden text-center py-12 px-6 bg-white rounded-3xl border border-dashed border-slate-200 text-sm text-slate-500">
        Tidak ada kosakata yang cocok. Coba kata kunci atau bab lain.
      </div>
    </div>
  @else
    <!-- Belum ada bab materi -->
    <div class="mt-8 text-center py-16 px-6 bg-white rounded-3xl border border-dashed border-slate-200">
      <span class="inline-flex w-14 h-14 rounded-full bg-indigo-50 text-indigo-500 items-center justify-center">
        <i class="fa-solid fa-book text-xl"></i>
      </span>
      <h2 class="kv-display mt-4 text-xl font-bold text-slate-800">Belum ada bab materi</h2>
      <p class="mt-1 text-sm text-slate-500">Tambahkan materi bab terlebih dahulu, lalu kosakata bisa dibuat per bab.</p>
    </div>
  @endif
</div>

<!-- Modal: pilih bab sebelum menambah kosakata -->
<div id="chooserModal" class="modal-overlay fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="chooserJudul"
     onclick="if (event.target === this) closeChooser()">
  <div class="w-full max-w-lg bg-white rounded-3xl p-6 md:p-7 shadow-2xl max-h-[90vh] overflow-y-auto">
    <h2 id="chooserJudul" class="kv-display text-xl font-bold text-slate-900">Tambah kosakata</h2>
    <p class="mt-1 text-sm text-slate-500">Pilih bab dulu, lalu isi kosakatanya (manual atau dengan AI).</p>

    <label for="chooserBab" class="block mt-6 text-sm font-bold text-slate-800 mb-2">Untuk bab mana?</label>
    <select id="chooserBab"
            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-[15px] text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">
      <option value="">Pilih bab materi</option>
      @foreach($activeChapters as $chap)
        <option value="{{ $chap['id'] }}">{{ $chap['judul'] }}</option>
      @endforeach
    </select>

    <p id="chooserError" class="mt-3 text-sm text-rose-600" role="alert" aria-live="assertive"></p>

    <div class="mt-4 flex justify-end gap-3">
      <button type="button" onclick="closeChooser()"
              class="px-5 py-2.5 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
        Batal
      </button>
      <button type="button" onclick="lanjutTambah()"
              class="px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
        Lanjut
      </button>
    </div>
  </div>
</div>

<!-- Pemilih bab (script terpisah supaya tetap jalan walau belum ada bab) -->
<script>
  var URL_TAMBAH = '{{ route("guru.kamus_vokasi.create") }}';

  function openChooser(babId) {
    var sel = document.getElementById('chooserBab');
    sel.value = babId || '';
    // Kalau hanya ada satu bab, langsung dipilihkan supaya guru tidak perlu memilih lagi.
    if (!sel.value && sel.options.length === 2) sel.selectedIndex = 1;
    document.getElementById('chooserError').textContent = '';
    document.getElementById('chooserModal').classList.add('is-open');
    sel.focus();
  }

  function closeChooser() {
    document.getElementById('chooserModal').classList.remove('is-open');
  }

  function lanjutTambah() {
    var sel = document.getElementById('chooserBab');
    if (!sel.value) {
      document.getElementById('chooserError').textContent = 'Pilih bab dulu.';
      sel.focus();
      return;
    }
    window.location.href = URL_TAMBAH + '?materi_id=' + encodeURIComponent(sel.value);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeChooser();
  });
</script>

<script>
  (function () {
    var cari     = document.getElementById('cari');
    var bab      = document.getElementById('filterBab');
    var sections = Array.prototype.slice.call(document.querySelectorAll('.bab-section'));
    var jumlah   = document.getElementById('jumlah');
    var kosong   = document.getElementById('kosong');
    if (!cari || !bab) return;

    // Status buka/tutup sebelum pencarian dimulai, supaya bisa dikembalikan setelah pencarian dihapus.
    var tersimpan = null;

    function sedangBuka(section) {
      return section.classList.contains('is-open');
    }

    function setBuka(section, buka) {
      var tombol = section.querySelector('.bab-toggle');
      var isi    = section.querySelector('.bab-isi');
      var label  = section.querySelector('.bab-label');

      section.classList.toggle('is-open', buka);
      tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
      // Isi yang tertutup tidak boleh bisa difokus lewat Tab.
      if (buka) { isi.removeAttribute('inert'); } else { isi.setAttribute('inert', ''); }
      if (label) label.textContent = buka ? 'Klik untuk menutup' : 'Klik untuk membuka';
    }

    // Awal: semua bab tertutup, klik judulnya untuk membuka.
    sections.forEach(function (section) {
      setBuka(section, false);
      section.querySelector('.bab-toggle').addEventListener('click', function () {
        tersimpan = null; // pilihan guru sendiri lebih diutamakan daripada status yang tersimpan
        setBuka(section, !sedangBuka(section));
      });
    });

    function bagianTerlihat() {
      return sections.filter(function (s) { return !s.classList.contains('hidden'); });
    }

    document.getElementById('bukaSemua').addEventListener('click', function () {
      tersimpan = null;
      bagianTerlihat().forEach(function (s) { setBuka(s, true); });
    });

    document.getElementById('tutupSemua').addEventListener('click', function () {
      tersimpan = null;
      bagianTerlihat().forEach(function (s) { setBuka(s, false); });
    });

    function saring() {
      var q = cari.value.trim().toLowerCase();
      var b = bab.value;
      var cariAktif = q !== '' || b !== '';
      var totalKata = 0;
      var bagianTampil = 0;

      // Simpan status buka/tutup sekali saja, tepat sebelum pencarian/saringan dimulai.
      if (cariAktif && tersimpan === null) {
        tersimpan = sections.map(sedangBuka);
      }

      sections.forEach(function (section) {
        var cocokBab = !b || section.dataset.babId === b;
        var kartu = section.querySelectorAll('.kata-kartu');
        var terlihat = 0;

        kartu.forEach(function (k) {
          var cocokTeks = !q || k.dataset.kata.indexOf(q) !== -1 || k.dataset.arti.indexOf(q) !== -1;
          var tampil = cocokBab && cocokTeks;
          k.classList.toggle('hidden', !tampil);
          if (tampil) terlihat++;
        });

        // Bab tanpa kosakata tetap tampil, kecuali sedang mencari kata atau bab lain dipilih.
        var tampilBagian = kartu.length === 0 ? (cocokBab && !q) : terlihat > 0;
        section.classList.toggle('hidden', !tampilBagian);

        // Saat mencari atau menyaring, bab yang cocok dibuka otomatis supaya hasilnya langsung terlihat.
        if (cariAktif && tampilBagian) setBuka(section, true);

        if (tampilBagian) bagianTampil++;
        totalKata += terlihat;
      });

      // Pencarian dihapus: kembalikan status buka/tutup seperti sebelumnya.
      if (!cariAktif && tersimpan !== null) {
        sections.forEach(function (section, i) { setBuka(section, tersimpan[i]); });
        tersimpan = null;
      }

      jumlah.textContent = totalKata;
      kosong.classList.toggle('hidden', bagianTampil > 0);
    }

    cari.addEventListener('input', saring);
    bab.addEventListener('change', saring);
  })();
</script>
@endsection