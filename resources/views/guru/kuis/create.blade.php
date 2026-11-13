@extends('layouts.app')

@section('content')
@php
  // Isian lama dikembalikan kalau validasi gagal, supaya guru tidak mengetik ulang.
  $lama = old('quizzes');
  $barisAwal = (is_array($lama) && count($lama) > 0)
      ? collect($lama)->map(fn ($q) => [
          'konteks_bacaan'      => $q['konteks_bacaan'] ?? '',
          'pertanyaan'          => $q['pertanyaan'] ?? '',
          'pilihan'             => [$q['pilihan_a'] ?? '', $q['pilihan_b'] ?? '', $q['pilihan_c'] ?? ''],
          'jawaban_benar'       => (int) ($q['jawaban_benar'] ?? 0),
          'tingkatan_kesulitan' => $q['tingkatan_kesulitan'] ?? 'Mudah',
        ])->values()->all()
      : [[]];
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  .kc-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .kc-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }
  .modal-overlay { display: none; }
  .modal-overlay.is-open { display: flex; }
</style>

<div class="kc-wrap max-w-3xl mx-auto pb-16">

  <a href="{{ route('guru.kuis') }}"
     class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg transition-colors">
    <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke bank soal
  </a>

  <!-- Kepala halaman -->
  <header class="mt-3 relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-900 text-white p-6 md:p-8">
    <div class="pointer-events-none absolute -top-16 -right-10 w-56 h-56 rounded-full bg-violet-500/30 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-10 w-56 h-56 rounded-full bg-indigo-400/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative">
      <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-indigo-100">
        <i class="fa-solid fa-book-open text-[10px]"></i>
        Bab: {{ $selectedMateri !== '' ? $selectedMateri : 'belum dipilih' }}
      </span>
      <h1 class="kc-display mt-3 text-2xl md:text-3xl font-bold">Tambah soal pilihan ganda</h1>
      <p class="mt-1.5 text-sm text-indigo-200 max-w-xl">
        Ketik soal sendiri, atau minta AI menyiapkan drafnya. Semua soal bisa dibaca dan diperbaiki dulu sebelum disimpan.
      </p>

      <div class="mt-5 flex flex-wrap gap-2">
        <button type="button" onclick="bukaAI()"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white text-indigo-950 hover:bg-indigo-50 text-sm font-bold focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-900 transition-colors">
          <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Bantu dengan AI
        </button>
        <button type="button" onclick="tambahSoalBaru()"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-white transition-colors">
          <i class="fa-solid fa-plus text-xs"></i> Tambah soal kosong
        </button>
      </div>
    </div>
  </header>

  @if($selectedMateri === '')
    <div role="alert" class="mt-5 flex items-start gap-3 p-4 bg-amber-50 border border-amber-100 text-amber-900 text-sm rounded-2xl">
      <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
      <span>Bab belum dipilih, jadi soal belum bisa disimpan. Kembali ke bank soal, lalu pilih bab lewat tombol <b>Tambah soal</b>.</span>
    </div>
  @endif

  @if ($errors->any())
    <div role="alert" class="mt-5 p-4 bg-rose-50 border border-rose-100 text-rose-800 text-sm rounded-2xl">
      <p class="font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation text-rose-500"></i> Ada isian yang perlu diperbaiki
      </p>
      <ul class="mt-2 ml-6 list-disc space-y-0.5">
        @foreach (collect($errors->all())->unique() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <!-- Hasil AI -->
  <div id="hasilAI" class="hidden mt-5 flex items-start gap-3 p-4 bg-emerald-50 border border-emerald-100 text-emerald-900 text-sm rounded-2xl" role="status" aria-live="polite">
    <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i>
    <p id="hasilAITeks" class="flex-1"></p>
    <button type="button" onclick="document.getElementById('hasilAI').classList.add('hidden')" aria-label="Tutup pesan"
            class="shrink-0 text-emerald-700 hover:text-emerald-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 rounded">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <form action="{{ route('guru.kuis.store') }}" method="POST" class="mt-6">
    @csrf
    <input type="hidden" name="materi_id" value="{{ $selectedMateri }}">

    <div id="quiz-container" class="space-y-5"></div>

    <button type="button" onclick="tambahSoalBaru(true)"
            class="mt-5 w-full flex items-center justify-center gap-2 py-4 rounded-3xl border-2 border-dashed border-slate-300 hover:border-indigo-400 hover:bg-indigo-50/50 text-slate-500 hover:text-indigo-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
      <i class="fa-solid fa-plus text-xs"></i> Tambah soal lagi
    </button>

    <!-- Bilah simpan -->
    <div class="sticky bottom-4 z-20 mt-6">
      <div class="flex flex-wrap items-center justify-between gap-3 pl-5 pr-3 py-3 rounded-full bg-white/95 backdrop-blur border border-slate-200 shadow-lg">
        <p class="text-sm text-slate-600">
          <b id="totalSiap" class="text-slate-900">0</b> soal siap disimpan
          <span id="infoBelum" class="hidden text-amber-700"></span>
        </p>
        <div class="flex items-center gap-2">
          <a href="{{ route('guru.kuis') }}"
             class="px-4 py-2 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
            Batal
          </a>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
            <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan semua soal
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- Jendela asisten AI -->
<div id="aiModal" class="modal-overlay fixed inset-0 z-50 items-center justify-center bg-slate-900/50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="aiJudul" onclick="if (event.target === this) tutupAI()">
  <div class="w-full max-w-lg bg-white rounded-3xl p-6 md:p-7 shadow-2xl max-h-[90vh] overflow-y-auto">
    <h2 id="aiJudul" class="kc-display text-xl font-bold text-slate-900">Bantu dengan AI</h2>
    <p class="mt-1 text-sm text-slate-500">Hasilnya masuk ke form dulu. Belum tersimpan sampai Bapak/Ibu menekan "Simpan semua soal".</p>

    <div class="mt-6 space-y-6">

      <fieldset>
        <legend class="text-sm font-bold text-slate-800">1. Apa yang AI kerjakan?</legend>
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label class="cursor-pointer">
            <input type="radio" name="aiMetode" value="ai" class="peer sr-only" checked onchange="aturMetode()">
            <span class="block h-full p-3.5 rounded-2xl border border-slate-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 transition-colors">
              <span class="block text-sm font-semibold text-slate-800">Buat soal baru</span>
              <span class="block mt-0.5 text-xs text-slate-500">AI menyusun soal baru dari materi.</span>
            </span>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="aiMetode" value="pdf" class="peer sr-only" onchange="aturMetode()">
            <span class="block h-full p-3.5 rounded-2xl border border-slate-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 transition-colors">
              <span class="block text-sm font-semibold text-slate-800">Salin soal yang sudah ada</span>
              <span class="block mt-0.5 text-xs text-slate-500">File atau teks Anda sudah berisi soal. AI menyalinnya apa adanya.</span>
            </span>
          </label>
        </div>
      </fieldset>

      <div>
        <p class="text-sm font-bold text-slate-800">2. Bahan untuk AI</p>
        <p id="hintBahan" class="mt-0.5 text-xs text-slate-500">Boleh dikosongkan. AI memakai isi materi bab ini.</p>

        <div class="mt-2 inline-flex p-1 rounded-full bg-slate-100" role="tablist" aria-label="Sumber bahan">
          <button type="button" role="tab" id="tabTeks" data-src="teks" onclick="setSumber('teks')"
                  class="src-tab px-4 py-1.5 rounded-full text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            Tempel teks
          </button>
          <button type="button" role="tab" id="tabFile" data-src="file" onclick="setSumber('file')"
                  class="src-tab px-4 py-1.5 rounded-full text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            Unggah file
          </button>
        </div>

        <div id="panelTeks" role="tabpanel" aria-labelledby="tabTeks" class="mt-3">
          <label for="aiTeks" class="sr-only">Teks bahan</label>
          <textarea id="aiTeks" rows="4" placeholder="Salin ringkasan materi atau daftar soal, lalu tempel di sini."
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white resize-none"></textarea>
        </div>

        <div id="panelFile" role="tabpanel" aria-labelledby="tabFile" class="mt-3" hidden>
          <label for="aiFile" class="sr-only">File bahan</label>
          <input type="file" id="aiFile" accept=".pdf,.docx,.doc,.txt"
                 class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-600 file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
          <p class="mt-1.5 text-xs text-slate-500">PDF, DOCX, atau TXT (maksimal 5 MB). PDF hasil scan atau foto tidak bisa dibaca.</p>
        </div>
      </div>

      <div>
        <p class="text-sm font-bold text-slate-800">3. Berapa banyak dan seberapa sulit?</p>
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="aiJumlah" id="labelJumlah" class="block text-xs font-semibold text-slate-500 mb-1.5">Jumlah soal (1 sampai 20)</label>
            <div class="flex items-center gap-2">
              <button type="button" onclick="ubahJumlah(-1)" aria-label="Kurangi jumlah soal"
                      class="w-10 h-10 rounded-full bg-white border border-slate-200 hover:border-indigo-300 text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <i class="fa-solid fa-minus text-xs"></i>
              </button>
              <input type="number" id="aiJumlah" value="5" min="1" max="20" inputmode="numeric"
                     class="w-16 text-center px-2 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-[15px] font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">
              <button type="button" onclick="ubahJumlah(1)" aria-label="Tambah jumlah soal"
                      class="w-10 h-10 rounded-full bg-white border border-slate-200 hover:border-indigo-300 text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <i class="fa-solid fa-plus text-xs"></i>
              </button>
            </div>
          </div>

          <fieldset>
            <legend class="block text-xs font-semibold text-slate-500 mb-1.5">Tingkat kesulitan</legend>
            <div class="flex gap-2">
              @foreach (['Mudah', 'Sedang', 'Sulit'] as $tk)
                <label class="cursor-pointer flex-1">
                  <input type="radio" name="aiTingkat" value="{{ $tk }}" class="peer sr-only" @if($tk === 'Mudah') checked @endif>
                  <span class="block text-center py-2.5 rounded-full border border-slate-200 text-sm font-medium text-slate-600 peer-checked:bg-indigo-950 peer-checked:text-white peer-checked:border-indigo-950 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 transition-colors">{{ $tk }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>
        </div>
      </div>
    </div>

    <p id="aiStatus" class="mt-5 text-sm" role="status" aria-live="polite"></p>

    <div class="mt-4 flex justify-end gap-3">
      <button type="button" onclick="tutupAI()"
              class="px-5 py-2.5 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
        Batal
      </button>
      <button type="button" id="aiTombol" onclick="jalankanAI()"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
        <i id="aiSpinner" class="fa-solid fa-spinner fa-spin text-xs" hidden></i>
        <span id="aiLabel">Masukkan ke form</span>
      </button>
    </div>
  </div>
</div>

<script>
  const BAB = @json($selectedMateri);
  const BARIS_AWAL = @json($barisAwal);
  const URL_PREVIEW = '{{ route("guru.kuis.previewAI") }}';

  const container = document.getElementById('quiz-container');
  let idx = 0;
  let sumberAI = 'teks';

  const GAYA_INPUT = 'w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white';
  const WARNA_TINGKAT = {
    Mudah:  'peer-checked:bg-emerald-600 peer-checked:border-emerald-600',
    Sedang: 'peer-checked:bg-amber-500 peer-checked:border-amber-500',
    Sulit:  'peer-checked:bg-rose-600 peer-checked:border-rose-600',
  };

  // Jenis kegagalan yang bukan "error aplikasi" (kuota habis, koneksi, server sibuk) ditampilkan kuning.
  const JENIS_KUNING = ['limit', 'koneksi', 'timeout', 'sibuk'];

  // Amankan teks sebelum masuk ke HTML (tanda kutip, <, > dari hasil AI tidak merusak form).
  function esc(teks) {
    return String(teks == null ? '' : teks)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function getCsrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
  }

  // ===== Satu kartu soal =====
  function buatKartu(data) {
    const i = idx++;
    const pil = data.pilihan || [];
    const benar = [0, 1, 2].includes(Number(data.jawaban_benar)) ? Number(data.jawaban_benar) : 0;
    const tingkat = ['Mudah', 'Sedang', 'Sulit'].includes(data.tingkatan_kesulitan) ? data.tingkatan_kesulitan : 'Mudah';
    const adaCerita = String(data.konteks_bacaan || '').trim() !== '';
    const huruf = ['A', 'B', 'C'];
    const kunci = ['a', 'b', 'c'];

    const opsi = huruf.map(function (h, n) {
      return `
        <div class="opsi-baris flex items-center gap-3 p-2.5 pr-3 rounded-2xl border border-slate-200 bg-white transition-colors">
          <span class="huruf w-8 h-8 shrink-0 rounded-full bg-slate-100 text-slate-500 text-sm font-bold flex items-center justify-center">${h}</span>
          <input type="text" name="quizzes[${i}][pilihan_${kunci[n]}]" value="${esc(pil[n])}" required maxlength="300"
                 placeholder="Tulis pilihan ${h}"
                 class="in-pilihan flex-1 min-w-0 px-3 py-2 bg-transparent text-sm text-slate-900 placeholder-slate-400 focus:outline-none">
          <div class="shrink-0">
            <input type="radio" id="k${i}-benar${n}" name="quizzes[${i}][jawaban_benar]" value="${n}" class="peer sr-only" ${benar === n ? 'checked' : ''}>
            <label for="k${i}-benar${n}"
                   class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 text-xs font-semibold text-slate-500 peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 transition-colors">
              <i class="fa-solid fa-check text-[10px]"></i> Benar
            </label>
          </div>
        </div>`;
    }).join('');

    const pill = ['Mudah', 'Sedang', 'Sulit'].map(function (k) {
      return `
        <label class="cursor-pointer">
          <input type="radio" name="quizzes[${i}][tingkatan_kesulitan]" value="${k}" class="peer sr-only" ${tingkat === k ? 'checked' : ''}>
          <span class="block px-4 py-1.5 rounded-full border border-slate-200 text-sm font-medium text-slate-600 peer-checked:text-white ${WARNA_TINGKAT[k]} peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 transition-colors">${k}</span>
        </label>`;
    }).join('');

    const html = `
      <article class="quiz-item bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)] transition-shadow">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <span class="no-baris w-9 h-9 shrink-0 rounded-2xl bg-indigo-950 text-white text-sm font-bold flex items-center justify-center"></span>
            <span class="status-baris text-xs font-semibold"></span>
          </div>
          <button type="button" class="hapus-baris text-xs font-semibold text-slate-400 hover:text-rose-600 flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 rounded px-1">
            <i class="fa-regular fa-trash-can"></i> Hapus soal
          </button>
        </div>

        <div class="mt-4">
          <button type="button" class="tombol-cerita inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 transition-colors" aria-expanded="${adaCerita ? 'true' : 'false'}">
            <i class="fa-solid fa-book-open text-[10px]"></i>
            <span class="teks-tombol-cerita">${adaCerita ? 'Sembunyikan bacaan / cerita' : 'Tambah bacaan / cerita (opsional)'}</span>
          </button>
          <div class="area-cerita ${adaCerita ? '' : 'hidden'} mt-3">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Bacaan atau cerita untuk soal ini</label>
            <textarea name="quizzes[${i}][konteks_bacaan]" rows="4" placeholder="Tempel teks cerita, paragraf, atau dialog di sini..."
                      class="in-cerita ${GAYA_INPUT}">${esc(data.konteks_bacaan)}</textarea>
          </div>
        </div>

        <div class="mt-5">
          <label class="block text-sm font-bold text-slate-800 mb-1.5">Pertanyaan</label>
          <textarea name="quizzes[${i}][pertanyaan]" rows="2" required placeholder="Tulis pertanyaan di sini..."
                    class="in-pertanyaan ${GAYA_INPUT}">${esc(data.pertanyaan)}</textarea>
        </div>

        <div class="mt-5">
          <p class="text-sm font-bold text-slate-800">
            Pilihan jawaban <span class="font-normal text-slate-400">· tekan "Benar" pada jawaban yang tepat</span>
          </p>
          <div class="mt-2 space-y-2">${opsi}</div>
        </div>

        <div class="mt-5">
          <p class="text-sm font-bold text-slate-800 mb-2">Tingkat kesulitan</p>
          <div class="flex flex-wrap gap-2">${pill}</div>
        </div>
      </article>`;

    const t = document.createElement('template');
    t.innerHTML = html.trim();
    const kartu = t.content.firstElementChild;

    kartu.querySelector('.hapus-baris').addEventListener('click', function () {
      const berisi = kartu.querySelector('.in-pertanyaan').value.trim() !== '';
      if (berisi && !confirm('Hapus soal ini dari form?')) return;
      kartu.remove();
      if (container.children.length === 0) tambahSoalBaru();
      perbarui();
    });

    const tombolCerita = kartu.querySelector('.tombol-cerita');
    tombolCerita.addEventListener('click', function () {
      const area = kartu.querySelector('.area-cerita');
      const buka = area.classList.contains('hidden');
      area.classList.toggle('hidden', !buka);
      tombolCerita.setAttribute('aria-expanded', buka ? 'true' : 'false');
      kartu.querySelector('.teks-tombol-cerita').textContent = buka
        ? 'Sembunyikan bacaan / cerita'
        : 'Tambah bacaan / cerita (opsional)';
      if (buka) kartu.querySelector('.in-cerita').focus();
    });

    kartu.querySelectorAll('input[type="text"], textarea').forEach(function (el) {
      el.addEventListener('input', perbarui);
    });
    kartu.querySelectorAll('input[type="radio"]').forEach(function (el) {
      el.addEventListener('change', function () { tandaiBenar(kartu); });
    });

    container.appendChild(kartu);
    tandaiBenar(kartu);
    perbarui();
    return kartu;
  }

  // Beri warna hijau pada baris jawaban yang ditandai benar.
  function tandaiBenar(kartu) {
    kartu.querySelectorAll('.opsi-baris').forEach(function (baris) {
      const benar = baris.querySelector('input[type="radio"]').checked;
      const huruf = baris.querySelector('.huruf');
      baris.classList.toggle('bg-emerald-50', benar);
      baris.classList.toggle('border-emerald-300', benar);
      baris.classList.toggle('bg-white', !benar);
      baris.classList.toggle('border-slate-200', !benar);
      huruf.classList.toggle('bg-emerald-600', benar);
      huruf.classList.toggle('text-white', benar);
      huruf.classList.toggle('bg-slate-100', !benar);
      huruf.classList.toggle('text-slate-500', !benar);
    });
  }

  function kartuKosong(kartu) {
    const kosong = (sel) => kartu.querySelector(sel).value.trim() === '';
    const pilihanKosong = Array.from(kartu.querySelectorAll('.in-pilihan')).every(el => el.value.trim() === '');
    return kosong('.in-pertanyaan') && kosong('.in-cerita') && pilihanKosong;
  }

  function kartuLengkap(kartu) {
    const pilihanLengkap = Array.from(kartu.querySelectorAll('.in-pilihan')).every(el => el.value.trim() !== '');
    return kartu.querySelector('.in-pertanyaan').value.trim() !== '' && pilihanLengkap;
  }

  // Perbarui nomor, status tiap soal, dan hitungan di bilah simpan.
  function perbarui() {
    const semua = Array.from(container.querySelectorAll('.quiz-item'));
    let siap = 0;

    semua.forEach(function (kartu, n) {
      kartu.querySelector('.no-baris').textContent = n + 1;

      const lengkap = kartuLengkap(kartu);
      if (lengkap) siap++;
      const status = kartu.querySelector('.status-baris');
      status.textContent = lengkap ? 'Lengkap' : 'Belum lengkap';
      status.className = 'status-baris text-xs font-semibold ' + (lengkap ? 'text-emerald-600' : 'text-amber-600');

      // Soal terakhir yang tersisa tidak bisa dihapus, supaya form tidak pernah kosong.
      kartu.querySelector('.hapus-baris').classList.toggle('hidden', semua.length === 1);
    });

    document.getElementById('totalSiap').textContent = siap;
    const belum = semua.length - siap;
    const info = document.getElementById('infoBelum');
    info.textContent = belum > 0 ? ' · ' + belum + ' belum lengkap' : '';
    info.classList.toggle('hidden', belum === 0);
  }

  function tambahSoalBaru(fokus) {
    const kartu = buatKartu({});
    if (fokus) {
      kartu.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    kartu.querySelector('.in-pertanyaan').focus({ preventScroll: !!fokus });
    return kartu;
  }

  // ===== Jendela AI =====
  function metodeAI() { return document.querySelector('input[name="aiMetode"]:checked').value; }

  function aturMetode() {
    const salin = metodeAI() === 'pdf';
    document.getElementById('hintBahan').textContent = salin
      ? 'Wajib diisi. Tempel teks atau unggah file yang sudah berisi soal. AI hanya menyalin, tidak membuat soal baru.'
      : 'Boleh dikosongkan. AI memakai isi materi bab ini.';
    document.getElementById('labelJumlah').textContent = salin
      ? 'Salin paling banyak (1 sampai 20)'
      : 'Jumlah soal (1 sampai 20)';
  }

  function setSumber(src) {
    sumberAI = src;
    document.getElementById('panelTeks').hidden = (src !== 'teks');
    document.getElementById('panelFile').hidden = (src !== 'file');
    document.querySelectorAll('.src-tab').forEach(function (t) {
      const hidup = t.dataset.src === src;
      t.classList.toggle('bg-white', hidup);
      t.classList.toggle('shadow-sm', hidup);
      t.classList.toggle('text-indigo-700', hidup);
      t.classList.toggle('text-slate-500', !hidup);
      t.setAttribute('aria-selected', hidup ? 'true' : 'false');
    });
  }

  function ubahJumlah(delta) {
    const el = document.getElementById('aiJumlah');
    el.value = Math.min(20, Math.max(1, (parseInt(el.value, 10) || 5) + delta));
  }

  function bukaAI() {
    document.getElementById('aiStatus').textContent = '';
    document.getElementById('aiStatus').className = 'mt-5 text-sm';
    document.getElementById('aiTeks').value = '';
    document.getElementById('aiFile').value = '';
    document.querySelector('input[name="aiMetode"][value="ai"]').checked = true;
    aturMetode();
    setSumber('teks');
    document.getElementById('aiModal').classList.add('is-open');
    document.querySelector('input[name="aiMetode"]:checked').focus();
  }

  function tutupAI() { document.getElementById('aiModal').classList.remove('is-open'); }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') tutupAI();
  });

  function setMemuat(sedang) {
    document.getElementById('aiTombol').disabled = sedang;
    document.getElementById('aiSpinner').hidden = !sedang;
    document.getElementById('aiLabel').textContent = sedang ? 'Sedang membuat…' : 'Masukkan ke form';
  }

  function tampilkanHasil(teks) {
    document.getElementById('hasilAITeks').textContent = teks;
    document.getElementById('hasilAI').classList.remove('hidden');
  }

  function jalankanAI() {
    const metode = metodeAI();
    const tingkat = document.querySelector('input[name="aiTingkat"]:checked').value;
    const jumlah = Math.min(20, Math.max(1, parseInt(document.getElementById('aiJumlah').value, 10) || 5));
    const teks = document.getElementById('aiTeks').value.trim();
    const berkas = document.getElementById('aiFile').files[0];
    const status = document.getElementById('aiStatus');

    if (!BAB) {
      status.textContent = 'Bab belum dipilih. Kembali ke bank soal lalu pilih bab lewat tombol "Tambah soal".';
      status.className = 'mt-5 text-sm text-rose-600';
      return;
    }

    // Mode salin soal wajib punya bahan, supaya AI tidak mengarang soal sendiri.
    if (metode === 'pdf') {
      const adaBahan = (sumberAI === 'teks') ? teks !== '' : !!berkas;
      if (!adaBahan) {
        status.textContent = sumberAI === 'teks'
          ? 'Tempel dulu teks yang berisi soalnya, atau pilih "Unggah file".'
          : 'Pilih dulu file yang berisi soalnya, atau pilih "Tempel teks".';
        status.className = 'mt-5 text-sm text-rose-600';
        return;
      }
    }

    // Internet perangkat mati: tidak perlu menunggu, langsung beri tahu.
    if (navigator.onLine === false) {
      status.textContent = 'Tidak ada koneksi internet di perangkat Anda. Periksa Wi-Fi atau data seluler, lalu coba lagi.';
      status.className = 'mt-5 text-sm text-amber-700';
      return;
    }

    const formData = new FormData();
    formData.append('bab', BAB);
    formData.append('tipe', 'kuis');
    formData.append('jumlah', jumlah);
    formData.append('tingkatan_kesulitan', tingkat);
    formData.append('metode', metode);
    if (sumberAI === 'teks') {
      formData.append('teks_sumber', document.getElementById('aiTeks').value);
    } else {
      formData.append('teks_sumber', '');
      if (berkas) formData.append('file', berkas);
    }

    setMemuat(true);
    status.textContent = 'AI sedang bekerja. Ini bisa sampai satu menit, jangan tutup halaman ini.';
    status.className = 'mt-5 text-sm text-slate-500';

    fetch(URL_PREVIEW, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
      body: formData,
    })
    .then(res => res.json().catch(() => ({
      success: false,
      message: res.status === 419
        ? 'Sesi sudah habis. Muat ulang halaman lalu coba lagi.'
        : res.status === 413
          ? 'Ukuran file terlalu besar untuk diterima server. Gunakan file yang lebih kecil (maksimal 5 MB).'
          : 'Server memberi jawaban yang tidak terduga (kode ' + res.status + '). Coba lagi sebentar lagi.'
    })))
    .then(function (hasil) {
      setMemuat(false);

      if (!(hasil.success && hasil.items && hasil.items.length)) {
        status.textContent = hasil.message || 'AI belum berhasil membuat soal. Coba lagi.';
        const kuning = JENIS_KUNING.includes(hasil.jenis);
        status.className = 'mt-5 text-sm ' + (kuning ? 'text-amber-700' : 'text-rose-600');
        return;
      }

      // Buang kartu yang masih kosong supaya tidak ada soal kosong di tengah.
      container.querySelectorAll('.quiz-item').forEach(function (k) { if (kartuKosong(k)) k.remove(); });

      const baru = [];
      hasil.items.forEach(function (item) {
        item.tingkatan_kesulitan = tingkat; // kesulitan mengikuti pilihan guru
        baru.push(buatKartu(item));
      });

      tutupAI();

      // Sorot kartu baru sebentar supaya terlihat mana yang dibuat AI.
      baru.forEach(function (k) { k.classList.add('ring-2', 'ring-indigo-300'); });
      setTimeout(function () { baru.forEach(function (k) { k.classList.remove('ring-2', 'ring-indigo-300'); }); }, 3000);
      baru[0].scrollIntoView({ behavior: 'smooth', block: 'start' });

      let pesan = baru.length + ' soal dimasukkan ke form. Baca dulu dan perbaiki kalau perlu, lalu simpan.';
      if (hasil.dilewati) pesan += ' ' + hasil.dilewati + ' dilewati karena sudah ada di bab ini.';
      if (hasil.terpotong) pesan += ' Bahan terlalu panjang, jadi hanya bagian awalnya yang dibaca.';
      tampilkanHasil(pesan);
    })
    .catch(function () {
      setMemuat(false);
      status.textContent = navigator.onLine === false
        ? 'Tidak ada koneksi internet di perangkat Anda. Periksa Wi-Fi atau data seluler, lalu coba lagi.'
        : 'Tidak dapat menghubungi server aplikasi. Pastikan server masih berjalan, lalu coba lagi.';
      status.className = 'mt-5 text-sm text-amber-700';
    });
  }

  // ===== Mulai =====
  BARIS_AWAL.forEach(function (b) { buatKartu(b); });
  aturMetode();
  setSumber('teks');
</script>
@endsection