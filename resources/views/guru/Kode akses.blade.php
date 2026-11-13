@extends('layouts.app')

@section('content')
@php
  $aktif = $sekolah['data']['aktif'] ?? true;
  $namaSekolah = $sekolah['data']['nama_sekolah'] ?? 'Sekolah';
  $kodeSekarang = old('kode_akses', $sekolah['data']['kode_akses'] ?? '');
@endphp

<div class="w-full max-w-5xl mx-auto">

  <!-- Judul halaman -->
  <div class="mb-5 sm:mb-6">
    <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Kelola Kode Akses</h2>
    <p class="text-sm text-gray-600 mt-0.5">Kode akses ini dipakai murid saat mendaftar akun baru.</p>
  </div>

  <!-- Notifikasi -->
  @if(session('success'))
    <div class="mb-4 p-3.5 sm:p-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl flex items-start gap-2.5">
      <i class="fa-solid fa-circle-check text-green-500 mt-0.5"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 p-3.5 sm:p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl flex items-start gap-2.5">
      <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif
  @if ($errors->any())
    <div class="mb-4 p-3.5 sm:p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl space-y-1">
      @foreach ($errors->all() as $error)
        <p class="flex items-start gap-2.5"><i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5"></i><span>{{ $error }}</span></p>
      @endforeach
    </div>
  @endif

  <!-- Satu kolom di HP, dua kolom di layar lebar -->
  <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 lg:gap-6 items-start">

    <!-- KARTU UTAMA: kode akses -->
    <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

      <!-- Bagian atas: identitas sekolah + kode besar -->
      <div class="relative bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 p-5 sm:p-7 text-white overflow-hidden">
        <!-- hiasan lingkaran -->
        <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-12 -left-8 w-32 h-32 rounded-full bg-white/10"></div>

        <div class="relative flex items-center gap-3 mb-5 sm:mb-6">
          <div class="w-10 h-10 sm:w-11 sm:h-11 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
            <i class="fa-solid fa-school"></i>
          </div>
          <div class="min-w-0">
            <p class="text-sm sm:text-base font-bold truncate">{{ $namaSekolah }}</p>
            <span class="inline-flex items-center gap-1.5 mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $aktif ? 'bg-emerald-400/20 text-emerald-100' : 'bg-white/15 text-white/70' }}">
              <span class="w-1.5 h-1.5 rounded-full {{ $aktif ? 'bg-emerald-300' : 'bg-gray-300' }}"></span>
              {{ $aktif ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
        </div>

        <p class="relative text-xs sm:text-sm text-indigo-100 mb-1.5">Kode akses saat ini</p>
        <p id="kodeTampil"
           class="relative font-mono font-bold tracking-[0.2em] sm:tracking-[0.3em] text-2xl sm:text-4xl break-all leading-tight">
          {{ $kodeSekarang ?: '—' }}
        </p>
      </div>

      <!-- Form ubah kode -->
      <div class="p-5 sm:p-7">
        <form method="POST" action="{{ route('guru.kode-akses.update') }}" class="space-y-3">
          @csrf

          <label for="kode_akses" class="block text-sm font-semibold text-gray-700">Ubah kode akses</label>

          <input type="text" name="kode_akses" id="kode_akses"
                 value="{{ $kodeSekarang }}"
                 required maxlength="50" autocomplete="off" spellcheck="false"
                 class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-base sm:text-lg font-mono font-bold tracking-widest text-indigo-700 text-center uppercase focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:bg-white transition">

          <!-- Tombol aksi: tersusun 3 kolom di HP, memanjang di layar lebar -->
          <div class="grid grid-cols-3 gap-2">
            <button type="button" onclick="copyKode()"
                    class="flex items-center justify-center gap-2 px-3 py-2.5 bg-white border border-gray-200 text-gray-600 text-xs sm:text-sm font-semibold rounded-xl hover:bg-gray-50 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600 transition">
              <i id="copyIcon" class="fa-regular fa-copy"></i>
              <span id="copyLabel">Salin</span>
            </button>

            <button type="button" onclick="acakKode()"
                    class="flex items-center justify-center gap-2 px-3 py-2.5 bg-white border border-gray-200 text-gray-600 text-xs sm:text-sm font-semibold rounded-xl hover:bg-gray-50 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600 transition">
              <i class="fa-solid fa-shuffle"></i>
              <span>Acak</span>
            </button>

            <a id="waLink" href="#" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 px-3 py-2.5 bg-white border border-gray-200 text-gray-600 text-xs sm:text-sm font-semibold rounded-xl hover:bg-emerald-50 hover:text-emerald-600 focus:outline-none focus:ring-2 focus:ring-indigo-600 transition">
              <i class="fa-brands fa-whatsapp"></i>
              <span>Bagikan</span>
            </a>
          </div>

          <button type="submit"
                  class="w-full h-11 sm:h-12 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 transition mt-1">
            Simpan Kode Akses
          </button>
        </form>
      </div>
    </div>

    <!-- KARTU SAMPING: panduan untuk murid -->
    <div class="lg:col-span-2 space-y-5">

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Cara murid mendaftar</h3>
        <ol class="space-y-4">
          <li class="flex gap-3">
            <span class="w-7 h-7 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-xs font-bold flex items-center justify-center">1</span>
            <p class="text-sm text-gray-600">Bagikan kode akses ke murid lewat WhatsApp atau kelas.</p>
          </li>
          <li class="flex gap-3">
            <span class="w-7 h-7 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-xs font-bold flex items-center justify-center">2</span>
            <p class="text-sm text-gray-600">Murid membuka aplikasi dan memilih <b class="text-gray-800">Daftar</b>.</p>
          </li>
          <li class="flex gap-3">
            <span class="w-7 h-7 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-xs font-bold flex items-center justify-center">3</span>
            <p class="text-sm text-gray-600">Murid memasukkan kode akses, lalu akunnya otomatis masuk ke sekolah ini.</p>
          </li>
        </ol>
      </div>

      <div class="p-4 sm:p-5 bg-amber-50 border border-amber-200 rounded-2xl flex gap-3">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5"></i>
        <p class="text-xs sm:text-sm text-amber-800">
          Kode yang diubah langsung berlaku. Murid yang memakai kode lama tidak bisa mendaftar lagi, tetapi akun yang sudah dibuat tetap aman.
        </p>
      </div>
    </div>

  </div>
</div>

<script>
  const kodeInput = document.getElementById('kode_akses');
  const kodeTampil = document.getElementById('kodeTampil');
  const waLink = document.getElementById('waLink');
  const namaSekolah = @json($namaSekolah);

  // Sinkronkan tampilan besar, tautan WhatsApp, dan huruf kapital saat kode berubah
  function segarkan() {
    const kode = kodeInput.value.trim();
    kodeTampil.textContent = kode || '—';
    const pesan = 'Kode akses pendaftaran Aura Lingua untuk ' + namaSekolah + ': ' + kode;
    waLink.href = 'https://wa.me/?text=' + encodeURIComponent(pesan);
  }

  kodeInput.addEventListener('input', () => {
    kodeInput.value = kodeInput.value.toUpperCase();
    segarkan();
  });

  function acakKode() {
    const huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa O, I, 0, 1 agar mudah dibaca
    let kode = '';
    for (let i = 0; i < 8; i++) kode += huruf[Math.floor(Math.random() * huruf.length)];
    kodeInput.value = kode;
    segarkan();
  }

  function copyKode() {
    navigator.clipboard.writeText(kodeInput.value).then(() => {
      const icon = document.getElementById('copyIcon');
      const label = document.getElementById('copyLabel');
      icon.className = 'fa-solid fa-check';
      label.textContent = 'Tersalin';
      setTimeout(() => {
        icon.className = 'fa-regular fa-copy';
        label.textContent = 'Salin';
      }, 1500);
    });
  }

  segarkan();
</script>
@endsection