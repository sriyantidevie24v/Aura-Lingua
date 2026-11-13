@extends('layouts.app')

@section('content')
@php
  $babAktif = old('materi_id', $kuis['materi_id'] ?? ($selectedMateri ?? ''));

  $tingkat = old('tingkatan_kesulitan', $kuis['tingkatan_kesulitan'] ?? 'Mudah');
  if (!in_array($tingkat, ['Mudah', 'Sedang', 'Sulit'], true)) { $tingkat = 'Mudah'; }

  $warnaTingkat = [
      'Mudah'  => 'peer-checked:bg-emerald-600 peer-checked:border-emerald-600',
      'Sedang' => 'peer-checked:bg-amber-500 peer-checked:border-amber-500',
      'Sulit'  => 'peer-checked:bg-rose-600 peer-checked:border-rose-600',
  ];
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
  <header class="mt-3 relative overflow-hidden rounded-3xl bg-gradient-to-br from-violet-950 via-violet-900 to-indigo-900 text-white p-6 md:p-8">
    <div class="pointer-events-none absolute -top-16 -right-10 w-56 h-56 rounded-full bg-indigo-400/30 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-10 w-56 h-56 rounded-full bg-violet-400/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative">
      <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-violet-100">
        <i class="fa-solid fa-book-open text-[10px]"></i> Bab: {{ $babAktif !== '' ? $babAktif : 'belum diketahui' }}
      </span>
      <h1 class="kc-display mt-3 text-2xl md:text-3xl font-bold">Edit tes pelafalan</h1>
      <p class="mt-1.5 text-sm text-violet-200 max-w-xl">
        Perbaiki kata atau kalimat yang harus diucapkan siswa. Tekan <b>Dengar</b> untuk mencoba bunyinya, dan lihat perkiraan tampilannya di kotak sebelah kanan.
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

  <form id="formEdit" action="{{ route('guru.kuis_suara.update', $kuis['id']) }}" method="POST" class="mt-6">
    @csrf
    @method('PUT')
    {{-- Bab tidak diubah di sini supaya hubungan latihan dengan materi tidak putus karena salah ketik. --}}
    <input type="hidden" name="materi_id" value="{{ $babAktif }}">

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

      <!-- Kolom isian -->
      <div class="lg:col-span-3 space-y-5">

        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <div class="flex items-center justify-between gap-3 mb-1.5">
            <label for="in-teks" class="block text-sm font-bold text-slate-800">Yang harus diucapkan siswa</label>
            <button type="button" id="tombolDengar" hidden
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-violet-50 hover:bg-violet-100 text-violet-800 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 transition-colors">
              <i class="fa-solid fa-volume-high text-[10px]"></i> Dengar
            </button>
          </div>
          <textarea id="in-teks" name="teks_target" rows="3" required placeholder="Contoh: Could you tell me about your experience?"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:bg-white">{{ old('teks_target', $kuis['teks_target'] ?? '') }}</textarea>
          <p class="mt-1 text-xs text-slate-400">Kata, frasa, atau kalimat dalam bahasa Inggris.</p>
        </section>

        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <label for="in-arti" class="block text-sm font-bold text-slate-800 mb-1.5">Artinya</label>
          <input id="in-arti" type="text" name="panduan_arti" value="{{ old('panduan_arti', $kuis['panduan_arti'] ?? '') }}" required maxlength="300"
                 placeholder="Contoh: Bisakah Anda ceritakan pengalaman Anda?"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:bg-white">
        </section>

        <section class="bg-white border border-slate-200/70 rounded-3xl p-5 md:p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <p class="text-sm font-bold text-slate-800 mb-2">Tingkat kesulitan</p>
          <div class="flex flex-wrap gap-2">
            @foreach ($warnaTingkat as $k => $warna)
              <label class="cursor-pointer">
                <input type="radio" name="tingkatan_kesulitan" value="{{ $k }}" class="peer sr-only" @if($tingkat === $k) checked @endif>
                <span class="block px-4 py-1.5 rounded-full border border-slate-200 text-sm font-medium text-slate-600 peer-checked:text-white {{ $warna }} peer-focus-visible:ring-2 peer-focus-visible:ring-violet-500 transition-colors">{{ $k }}</span>
              </label>
            @endforeach
          </div>
        </section>
      </div>

      <!-- Pratinjau untuk siswa -->
      <aside class="lg:col-span-2 lg:sticky lg:top-6" aria-label="Pratinjau latihan untuk siswa">
        <p class="text-sm font-semibold text-slate-500 mb-3 flex items-center gap-2">
          <i class="fa-regular fa-eye text-xs"></i> Pratinjau untuk siswa
        </p>

        <div class="bg-white border border-slate-200/70 rounded-3xl p-5 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
          <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-violet-50 text-violet-700 text-xs font-semibold">
              <i class="fa-solid fa-microphone text-[10px]"></i> Tes pelafalan
            </span>
            <span id="pvKesulitan" class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold"></span>
          </div>

          <p class="mt-5 text-xs font-semibold text-slate-500">Ucapkan kalimat ini</p>
          <p id="pvTeks" class="kc-display mt-1 text-xl font-bold break-words"></p>
          <p class="mt-2 text-sm text-slate-500 break-words">Artinya: <span id="pvArti"></span></p>

          <div class="mt-5 flex items-center justify-center">
            <span class="w-14 h-14 rounded-full bg-violet-600 text-white flex items-center justify-center shadow-md" aria-hidden="true">
              <i class="fa-solid fa-microphone"></i>
            </span>
          </div>
          <p class="mt-2 text-center text-xs text-slate-400">Siswa menekan tombol ini lalu mengucapkan kalimat di atas.</p>
        </div>
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
             class="px-4 py-2 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 transition-colors">
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
    var bisaSuara = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;

    function nilaiKesulitan() {
      var r = document.querySelector('input[name="tingkatan_kesulitan"]:checked');
      return r ? r.value : 'Mudah';
    }

    function perbaruiPratinjau() {
      var teks = $('in-teks').value.trim();
      var arti = $('in-arti').value.trim();

      var pvTeks = $('pvTeks');
      pvTeks.textContent = teks || 'Kalimat akan tampil di sini';
      pvTeks.classList.toggle('text-slate-900', teks !== '');
      pvTeks.classList.toggle('text-slate-300', teks === '');

      $('pvArti').textContent = arti || '-';
      $('pvKesulitan').textContent = nilaiKesulitan();
    }

    function tandaiKotor() {
      kotor = true;
      $('penandaKotor').classList.remove('hidden');
      $('penandaBersih').classList.add('hidden');
    }

    // Ucapkan teks dengan suara bawaan browser (bahasa Inggris).
    if (bisaSuara) {
      var tombol = $('tombolDengar');
      tombol.hidden = false;
      tombol.addEventListener('click', function () {
        var teks = $('in-teks').value.trim();
        if (!teks) return;
        window.speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(teks);
        u.lang = 'en-US';
        u.rate = 0.9;
        window.speechSynthesis.speak(u);
      });
    }

    form.addEventListener('input', function () { perbaruiPratinjau(); tandaiKotor(); });
    form.addEventListener('change', function () { perbaruiPratinjau(); tandaiKotor(); });
    form.addEventListener('submit', function () { mengirim = true; });

    // Peringatan kalau halaman ditutup padahal ada perubahan yang belum disimpan.
    window.addEventListener('beforeunload', function (e) {
      if (kotor && !mengirim) { e.preventDefault(); e.returnValue = ''; }
    });

    perbaruiPratinjau();
  })();
</script>
@endsection