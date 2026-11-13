@extends('layouts.app')

@section('content')
@php
  $pilihan   = $kuis['pilih_jawaban'] ?? [];
  $babAktif  = old('materi_id', $kuis['materi_id'] ?? ($selectedMateri ?? ''));
  $cerita    = old('konteks_bacaan', $kuis['konteks_bacaan'] ?? '');
  $adaCerita = trim((string) $cerita) !== '';

  $benar = (int) old('jawaban_benar', $kuis['jawaban_benar'] ?? 0);
  if (!in_array($benar, [0, 1, 2], true)) { $benar = 0; }

  $tingkat = old('tingkatan_kesulitan', $kuis['tingkatan_kesulitan'] ?? 'Mudah');
  if (!in_array($tingkat, ['Mudah', 'Sedang', 'Sulit'], true)) { $tingkat = 'Mudah'; }

  $warnaTingkat = [
      'Mudah'  => 'peer-checked:bg-emerald-600 peer-checked:border-emerald-600',
      'Sedang' => 'peer-checked:bg-amber-500 peer-checked:border-amber-500',
      'Sulit'  => 'peer-checked:bg-rose-600 peer-checked:border-rose-600',
  ];
  $huruf = ['a' => 'A', 'b' => 'B', 'c' => 'C'];
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  .kc-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .kc-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }
</style>

<div class="kc-wrap max-w-5xl mx-auto pb-16">

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
        <i class="fa-solid fa-book-open text-[10px]"></i> Bab: {{ $babAktif !== '' ? $babAktif : 'belum diketahui' }}
      </span>
      <h1 class="kc-display mt-3 text-2xl md:text-3xl font-bold">Edit soal pilihan ganda</h1>
      <p class="mt-1.5 text-sm text-indigo-200 max-w-xl">
        Perbaiki cerita, pertanyaan, pilihan, atau kunci jawabannya. Kotak di sebelah kanan menunjukkan perkiraan tampilannya bagi siswa.
      </p>
    </div>
  </header>

  @if ($errors->any())
    <div role="alert" class="mt-5 p-4 bg-rose-50 border border-rose-100 text-rose-800 text-sm rounded-2xl">
      <p class="font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation text-rose-500"></i> Perubahan belum tersimpan
      </p>
      <ul class="mt-2 ml-6 list-disc space-y-0.5">
        @foreach (collect($errors->all())->unique() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form id="formEdit" action="{{ route('guru.kuis.update', $kuis['id']) }}" method="POST" class="mt-6">
    @csrf
    @method('PUT')
    {{-- Bab tidak diubah di sini supaya hubungan soal dengan materi tidak putus karena salah ketik. --}}
    <input type="hidden" name="materi_id" value="{{ $babAktif }}">

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

      <!-- Kolom isian -->
      <div class="lg:col-span-3 space-y-5">

        <!-- Bacaan / cerita -->
        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <button type="button" id="tombolCerita" aria-expanded="{{ $adaCerita ? 'true' : 'false' }}" aria-controls="areaCerita"
                  class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 transition-colors">
            <i class="fa-solid fa-book-open text-[10px]"></i>
            <span id="teksTombolCerita">{{ $adaCerita ? 'Sembunyikan bacaan / cerita' : 'Tambah bacaan / cerita (opsional)' }}</span>
          </button>

          <div id="areaCerita" class="{{ $adaCerita ? '' : 'hidden' }} mt-3">
            <label for="in-konteks" class="block text-sm font-bold text-slate-800 mb-1.5">Bacaan atau cerita untuk soal ini</label>
            <textarea id="in-konteks" name="konteks_bacaan" rows="5" placeholder="Tempel teks cerita, paragraf, atau dialog di sini..."
                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">{{ $cerita }}</textarea>
            <p class="mt-1 text-xs text-slate-400">Kosongkan teks ini kalau soal tidak memakai bacaan.</p>
          </div>
        </section>

        <!-- Pertanyaan -->
        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <label for="in-pertanyaan" class="block text-sm font-bold text-slate-800 mb-1.5">Pertanyaan</label>
          <textarea id="in-pertanyaan" name="pertanyaan" rows="3" required placeholder="Tulis pertanyaan di sini..."
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">{{ old('pertanyaan', $kuis['pertanyaan'] ?? '') }}</textarea>
        </section>

        <!-- Pilihan jawaban -->
        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <p class="text-sm font-bold text-slate-800">
            Pilihan jawaban <span class="font-normal text-slate-400">· tekan "Benar" pada jawaban yang tepat</span>
          </p>

          <div class="mt-3 space-y-2">
            @foreach ($huruf as $kunci => $h)
              @php
                $n = $loop->index;
                $nilai = old('pilihan_' . $kunci, $pilihan[$n] ?? '');
              @endphp
              <div class="opsi-baris flex items-center gap-3 p-2.5 pr-3 rounded-2xl border border-slate-200 bg-white transition-colors">
                <span class="huruf w-8 h-8 shrink-0 rounded-full bg-slate-100 text-slate-500 text-sm font-bold flex items-center justify-center">{{ $h }}</span>
                <label for="in-{{ $kunci }}" class="sr-only">Pilihan {{ $h }}</label>
                <input id="in-{{ $kunci }}" type="text" name="pilihan_{{ $kunci }}" value="{{ $nilai }}" required maxlength="300"
                       placeholder="Tulis pilihan {{ $h }}"
                       class="flex-1 min-w-0 px-3 py-2 bg-transparent text-sm text-slate-900 placeholder-slate-400 focus:outline-none">
                <div class="shrink-0">
                  <input type="radio" id="benar-{{ $n }}" name="jawaban_benar" value="{{ $n }}" class="peer sr-only" @if($benar === $n) checked @endif>
                  <label for="benar-{{ $n }}"
                         class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 text-xs font-semibold text-slate-500 peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 transition-colors">
                    <i class="fa-solid fa-check text-[10px]"></i> Benar
                  </label>
                </div>
              </div>
            @endforeach
          </div>
        </section>

        <!-- Tingkat kesulitan -->
        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <p class="text-sm font-bold text-slate-800 mb-2">Tingkat kesulitan</p>
          <div class="flex flex-wrap gap-2">
            @foreach ($warnaTingkat as $k => $warna)
              <label class="cursor-pointer">
                <input type="radio" name="tingkatan_kesulitan" value="{{ $k }}" class="peer sr-only" @if($tingkat === $k) checked @endif>
                <span class="block px-4 py-1.5 rounded-full border border-slate-200 text-sm font-medium text-slate-600 peer-checked:text-white {{ $warna }} peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 transition-colors">{{ $k }}</span>
              </label>
            @endforeach
          </div>
        </section>
      </div>

      <!-- Pratinjau untuk siswa -->
      <aside class="lg:col-span-2 lg:sticky lg:top-6" aria-label="Pratinjau soal untuk siswa">
        <p class="text-sm font-semibold text-slate-500 mb-3 flex items-center gap-2">
          <i class="fa-regular fa-eye text-xs"></i> Pratinjau untuk siswa
        </p>

        <div class="bg-white border border-slate-200/70 rounded-3xl p-5 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold">
              <i class="fa-solid fa-list-check text-[10px]"></i> Pilihan ganda
            </span>
            <span id="pvKesulitan" class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold"></span>
          </div>

          <div id="pvCerita" class="hidden mt-4 p-3.5 rounded-2xl bg-amber-50/70 border border-amber-100">
            <p class="flex items-center gap-1.5 text-xs font-bold text-amber-800 mb-1">
              <i class="fa-solid fa-book-open text-[11px]"></i> Bacaan
            </p>
            <p id="pvCeritaTeks" class="text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words max-h-48 overflow-y-auto"></p>
          </div>

          <p id="pvPertanyaan" class="mt-4 text-[15px] font-semibold break-words"></p>

          <div class="mt-3 space-y-2">
            @foreach ($huruf as $kunci => $h)
              <div id="pvOpsi{{ $loop->index }}" class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-600 transition-colors">
                <span class="pv-huruf w-6 h-6 rounded-full shrink-0 flex items-center justify-center text-xs font-bold bg-slate-100 text-slate-500">{{ $h }}</span>
                <span class="pv-teks min-w-0 break-words flex-1"></span>
                <span class="pv-kunci hidden shrink-0 text-xs font-bold text-emerald-700"><i class="fa-solid fa-check"></i> Kunci</span>
              </div>
            @endforeach
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">
          Tanda "Kunci" hanya terlihat oleh guru. Siswa baru melihat jawaban yang benar setelah menjawab.
        </p>
      </aside>
    </div>

    <!-- Bilah simpan -->
    <div class="sticky bottom-4 z-20 mt-6">
      <div class="flex flex-wrap items-center justify-between gap-3 pl-5 pr-3 py-3 rounded-full bg-white/95 backdrop-blur border border-slate-200 shadow-lg">
        <p class="text-sm text-slate-600 flex items-center gap-2">
          <span id="penandaKotor" class="hidden inline-flex items-center gap-1.5 text-amber-700 font-semibold">
            <i class="fa-solid fa-circle text-[7px]"></i> Ada perubahan yang belum disimpan
          </span>
          <span id="penandaBersih" class="text-slate-400">Belum ada perubahan</span>
        </p>
        <div class="flex items-center gap-2">
          <a href="{{ route('guru.kuis') }}"
             class="px-4 py-2 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
            Batal
          </a>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
            <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan perubahan
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  (function () {
    var $ = function (id) { return document.getElementById(id); };
    var form = $('formEdit');
    var kotor = false;
    var mengirim = false;

    function nilaiBenar() {
      var r = document.querySelector('input[name="jawaban_benar"]:checked');
      return r ? Number(r.value) : 0;
    }

    function nilaiKesulitan() {
      var r = document.querySelector('input[name="tingkatan_kesulitan"]:checked');
      return r ? r.value : 'Mudah';
    }

    // Warna hijau pada baris jawaban yang ditandai benar (di form dan di pratinjau).
    function tandaiBenar() {
      var benar = nilaiBenar();

      document.querySelectorAll('.opsi-baris').forEach(function (baris, n) {
        var aktif = n === benar;
        var huruf = baris.querySelector('.huruf');
        baris.classList.toggle('bg-emerald-50', aktif);
        baris.classList.toggle('border-emerald-300', aktif);
        baris.classList.toggle('bg-white', !aktif);
        baris.classList.toggle('border-slate-200', !aktif);
        huruf.classList.toggle('bg-emerald-600', aktif);
        huruf.classList.toggle('text-white', aktif);
        huruf.classList.toggle('bg-slate-100', !aktif);
        huruf.classList.toggle('text-slate-500', !aktif);
      });
    }

    function perbaruiPratinjau() {
      var cerita = $('in-konteks').value.trim();
      $('pvCerita').classList.toggle('hidden', cerita === '');
      $('pvCeritaTeks').textContent = cerita;

      var tanya = $('in-pertanyaan').value.trim();
      var pv = $('pvPertanyaan');
      pv.textContent = tanya || 'Pertanyaan akan tampil di sini';
      pv.classList.toggle('text-slate-900', tanya !== '');
      pv.classList.toggle('text-slate-400', tanya === '');

      var benar = nilaiBenar();
      ['a', 'b', 'c'].forEach(function (k, n) {
        var isi = $('in-' + k).value.trim();
        var baris = $('pvOpsi' + n);
        var aktif = n === benar;

        baris.querySelector('.pv-teks').textContent = isi || ('Pilihan ' + k.toUpperCase());
        baris.querySelector('.pv-teks').classList.toggle('text-slate-400', isi === '');
        baris.querySelector('.pv-kunci').classList.toggle('hidden', !aktif);

        baris.classList.toggle('bg-emerald-50', aktif);
        baris.classList.toggle('border-emerald-200', aktif);
        baris.classList.toggle('bg-white', !aktif);
        baris.classList.toggle('border-slate-200', !aktif);

        var huruf = baris.querySelector('.pv-huruf');
        huruf.classList.toggle('bg-emerald-600', aktif);
        huruf.classList.toggle('text-white', aktif);
        huruf.classList.toggle('bg-slate-100', !aktif);
        huruf.classList.toggle('text-slate-500', !aktif);
      });

      $('pvKesulitan').textContent = nilaiKesulitan();
    }

    function tandaiKotor() {
      kotor = true;
      $('penandaKotor').classList.remove('hidden');
      $('penandaBersih').classList.add('hidden');
    }

    function perbaruiSemua() { tandaiBenar(); perbaruiPratinjau(); }

    // Tombol bacaan / cerita
    var tombolCerita = $('tombolCerita');
    tombolCerita.addEventListener('click', function () {
      var area = $('areaCerita');
      var buka = area.classList.contains('hidden');
      area.classList.toggle('hidden', !buka);
      tombolCerita.setAttribute('aria-expanded', buka ? 'true' : 'false');
      $('teksTombolCerita').textContent = buka ? 'Sembunyikan bacaan / cerita' : 'Tambah bacaan / cerita (opsional)';
      if (buka) $('in-konteks').focus();
    });

    form.addEventListener('input', function () { perbaruiSemua(); tandaiKotor(); });
    form.addEventListener('change', function () { perbaruiSemua(); tandaiKotor(); });
    form.addEventListener('submit', function () { mengirim = true; });

    // Peringatan kalau halaman ditutup padahal ada perubahan yang belum disimpan.
    window.addEventListener('beforeunload', function (e) {
      if (kotor && !mengirim) { e.preventDefault(); e.returnValue = ''; }
    });

    perbaruiSemua();
  })();
</script>
@endsection