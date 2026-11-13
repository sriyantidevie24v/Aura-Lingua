@extends('layouts.app')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  .kv-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .kv-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }
</style>

<div class="kv-wrap max-w-5xl mx-auto pb-16">

  <a href="{{ route('guru.kamus_vokasi.index') }}"
     class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg transition-colors">
    <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke kamus
  </a>

  <h1 class="kv-display mt-3 text-3xl md:text-4xl font-bold text-slate-900">Edit kosakata</h1>
  <p class="mt-1.5 text-sm text-slate-500">Perbarui informasi kosakata kamus.</p>

  @if ($errors->any())
    <div role="alert" class="mt-6 p-4 bg-rose-50 border border-rose-100 text-rose-800 text-sm rounded-2xl">
      <p class="font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation text-rose-500"></i> Ada isian yang perlu diperbaiki
      </p>
      <ul class="mt-2 ml-6 list-disc space-y-0.5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(($selectedMateriId ?? '') === '')
    <div role="status" class="mt-6 flex items-start gap-3 p-4 bg-amber-50 border border-amber-100 text-amber-800 text-sm rounded-2xl">
      <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
      <span>Bab lama untuk kosakata ini sudah tidak ada di materi. Pilih bab yang tersedia agar kosakata tampil kembali di kamus.</span>
    </div>
  @endif

  <div class="mt-8 grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

    <!-- Form -->
    <form action="{{ route('guru.kamus_vokasi.update', $kamus['id']) }}" method="POST"
          class="lg:col-span-3 bg-white rounded-3xl border border-slate-200/70 p-7 space-y-5 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
      @csrf
      @method('PUT')

      <div>
        <label for="kata" class="block text-sm font-semibold text-slate-800 mb-1.5">Kata target</label>
        <input id="kata" type="text" name="kata" value="{{ old('kata', $kamus['kata'] ?? '') }}" required placeholder="Contoh: curriculum vitae"
               class="w-full px-4 py-3 bg-slate-50 border rounded-2xl text-[15px] text-slate-900 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white @error('kata') border-rose-300 @else border-slate-200 @enderror">
        <p class="mt-1.5 text-xs text-slate-400">Kata dalam bahasa asing yang dipelajari murid.</p>
        @error('kata') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
      </div>

      <div>
        <label for="arti_id" class="block text-sm font-semibold text-slate-800 mb-1.5">Arti dalam Bahasa Indonesia</label>
        <input id="arti_id" type="text" name="arti_id" value="{{ old('arti_id', $kamus['arti_id'] ?? '') }}" required placeholder="Contoh: daftar riwayat hidup"
               class="w-full px-4 py-3 bg-slate-50 border rounded-2xl text-[15px] text-slate-900 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white @error('arti_id') border-rose-300 @else border-slate-200 @enderror">
        @error('arti_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
      </div>

      <div>
        <label for="materi_id" class="block text-sm font-semibold text-slate-800 mb-1.5">Bab terkait</label>
        <select id="materi_id" name="materi_id" required
                class="w-full px-4 py-3 bg-slate-50 border rounded-2xl text-[15px] text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white @error('materi_id') border-rose-300 @else border-slate-200 @enderror">
          <option value="">Pilih bab materi</option>
          @foreach($activeChapters as $chap)
            <option value="{{ $chap['id'] }}" {{ old('materi_id', $selectedMateriId ?? '') == $chap['id'] ? 'selected' : '' }}>{{ $chap['judul'] }}</option>
          @endforeach
        </select>
        <p class="mt-1.5 text-xs text-slate-400">Daftar bab mengikuti materi. Bab baru di materi otomatis muncul di sini.</p>
        @error('materi_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
      </div>

      <div class="pt-5 border-t border-slate-100 flex flex-wrap justify-end gap-3">
        <a href="{{ route('guru.kamus_vokasi.index') }}"
           class="px-5 py-2.5 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
          Batal
        </a>
        <button type="submit"
                class="px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
          Simpan perubahan
        </button>
      </div>
    </form>

    <!-- Pratinjau langsung -->
    <aside class="lg:col-span-2 lg:sticky lg:top-6" aria-label="Pratinjau kosakata">
      <p class="text-sm font-medium text-slate-500 mb-3">Pratinjau di kamus</p>
      <div class="bg-white rounded-3xl border border-slate-200/70 p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
        <div class="flex items-start justify-between gap-3">
          <h2 id="pvKata" class="kv-display text-2xl font-bold text-slate-300 break-words min-w-0">Kata target</h2>
          <span id="pvBab" class="shrink-0 px-3 py-1 rounded-full bg-slate-100 text-slate-400 text-xs font-semibold">Bab</span>
        </div>
        <p id="pvArti" class="mt-2 text-[15px] text-slate-300">Arti dalam Bahasa Indonesia</p>
      </div>
    </aside>
  </div>

  <!-- Saran kosakata dari AI berdasarkan judul bab (sama seperti halaman tambah) -->
  @include('guru.kamus_vokasi._saran', ['kecualiId' => $kamus['id']])
</div>

<script>
  (function () {
    var kata = document.getElementById('kata');
    var arti = document.getElementById('arti_id');
    var bab  = document.getElementById('materi_id');
    var pvKata = document.getElementById('pvKata');
    var pvArti = document.getElementById('pvArti');
    var pvBab  = document.getElementById('pvBab');

    function isi(el, nilai, kosong, aktif, redup) {
      el.textContent = nilai || kosong;
      el.classList.toggle(aktif, !!nilai);
      el.classList.toggle(redup, !nilai);
    }

    function perbarui() {
      isi(pvKata, kata.value.trim(), 'Kata target', 'text-slate-900', 'text-slate-300');
      isi(pvArti, arti.value.trim(), 'Arti dalam Bahasa Indonesia', 'text-slate-600', 'text-slate-300');

      // Tampilkan JUDUL bab (teks pilihan), bukan id-nya.
      var teksBab = bab.value ? bab.options[bab.selectedIndex].text : '';
      pvBab.textContent = teksBab || 'Bab';
      pvBab.classList.toggle('bg-indigo-50', !!teksBab);
      pvBab.classList.toggle('text-indigo-700', !!teksBab);
      pvBab.classList.toggle('bg-slate-100', !teksBab);
      pvBab.classList.toggle('text-slate-400', !teksBab);
    }

    [kata, arti].forEach(function (el) { el.addEventListener('input', perbarui); });
    bab.addEventListener('change', perbarui);
    perbarui();
  })();
</script>
@endsection