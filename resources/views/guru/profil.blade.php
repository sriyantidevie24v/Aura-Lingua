@extends('layouts.app')

@section('content')
@php
  $nama   = $guru['namaGuru'] ?? 'Guru Aura Lingua';
  $email  = $guru['emailGuru'] ?? null;
  $inisial = collect(preg_split('/\s+/', trim($nama)))
      ->filter()->take(2)
      ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
      ->implode('');
  $inisial = $inisial ?: 'GU';
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  .profil-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .profil-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }

  /* Aura: ukuran mengikuti lebar layar (6rem di HP kecil, 7.5rem di layar besar) */
  .aura {
    position: relative;
    width: clamp(6rem, 24vw, 7.5rem);
    aspect-ratio: 1 / 1;
    border-radius: 9999px;
    padding: 5px;
    background: conic-gradient(from 0deg, #6366f1, #a78bfa, #f0abfc, #fbbf24, #34d399, #6366f1);
    animation: aura-putar 12s linear infinite;
  }
  .aura::after {
    content: ''; position: absolute; inset: -14px; z-index: -1;
    border-radius: 9999px;
    background: conic-gradient(from 0deg, #6366f1, #a78bfa, #f0abfc, #fbbf24, #34d399, #6366f1);
    filter: blur(22px); opacity: .35;
  }
  .aura-isi {
    width: 100%; height: 100%;
    border-radius: 9999px;
    background: #1e1b4b; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: clamp(1.6rem, 7vw, 2.1rem);
    animation: aura-putar 12s linear infinite reverse; /* teks tetap tegak */
  }
  @keyframes aura-putar { to { transform: rotate(360deg); } }

  /* Sapaan berganti bahasa */
  #sapaan { transition: opacity .4s ease, transform .4s ease; display: inline-block; }
  #sapaan.keluar { opacity: 0; transform: translateY(6px); }

  @media (prefers-reduced-motion: reduce) {
    .aura, .aura-isi { animation: none; }
    #sapaan { transition: none; }
  }
</style>

<div class="profil-wrap w-full max-w-2xl md:max-w-3xl mx-auto pb-12 sm:pb-16">

  <!-- Navigasi -->
  <a href="{{ route('guru.ringkasan') }}"
     class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg px-1 py-1 transition-colors">
    <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke ringkasan
  </a>

  <!-- Kartu profil -->
  <section class="mt-4 sm:mt-5 bg-white rounded-3xl sm:rounded-[2rem] border border-slate-200/70 shadow-[0_20px_50px_-30px_rgba(30,27,75,.35)] overflow-hidden">

    <!-- Bagian atas: aura + identitas -->
    <div class="px-5 sm:px-8 pt-8 sm:pt-10 pb-6 sm:pb-8 bg-gradient-to-b from-indigo-50/80 to-white flex flex-col items-center text-center">
      <div class="aura" aria-hidden="true">
        <div class="aura-isi profil-display">{{ $inisial }}</div>
      </div>

      <p class="mt-7 sm:mt-8 text-xs sm:text-sm text-slate-500" aria-live="polite">
        <span id="sapaan" class="profil-display text-base sm:text-lg text-indigo-700">Halo</span>,
        selamat datang kembali
      </p>

      <h1 class="profil-display mt-1 text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 leading-tight break-words max-w-full">
        {{ $nama }}
      </h1>
      <p class="mt-2 text-xs sm:text-sm text-slate-500">Pengajar di Aura Lingua</p>
    </div>

    <!-- Detail akun: satu kolom di HP, dua kolom mulai layar sedang -->
    <div class="px-5 sm:px-8 pb-2">
      <dl class="divide-y divide-slate-100 md:divide-y-0 md:grid md:grid-cols-2 md:gap-x-8">

        <div class="py-4 sm:py-5 flex items-center gap-3 sm:gap-4 min-w-0">
          <span class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
            <i class="fa-regular fa-user text-sm"></i>
          </span>
          <div class="min-w-0">
            <dt class="text-xs text-slate-400">Nama lengkap</dt>
            <dd class="text-sm sm:text-[15px] font-semibold text-slate-900 break-words">{{ $guru['namaGuru'] ?? '-' }}</dd>
          </div>
        </div>

        <div class="py-4 sm:py-5 flex flex-wrap items-center gap-x-3 sm:gap-x-4 gap-y-3 min-w-0">
          <span class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
            <i class="fa-regular fa-envelope text-sm"></i>
          </span>
          <div class="min-w-0 flex-1 basis-40">
            <dt class="text-xs text-slate-400">Email</dt>
            <dd id="emailGuru" class="text-sm sm:text-[15px] font-semibold text-slate-900 break-all">{{ $email ?? '-' }}</dd>
          </div>
          @if($email)
            <!-- Di layar sempit tombol turun ke bawah email, sejajar dengan teks -->
            <button type="button" id="tombolSalin"
                    class="shrink-0 ml-[3.25rem] sm:ml-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
              <i class="fa-regular fa-copy"></i>
              <span id="labelSalin">Salin</span>
            </button>
          @endif
        </div>
      </dl>
    </div>

    <!-- Aksi: bertumpuk di HP, sejajar di layar lebar -->
    <div class="px-5 sm:px-8 py-5 sm:py-6 mt-2 bg-slate-50/70 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-4">
      <p class="text-xs text-slate-400 flex items-center justify-center sm:justify-start gap-2 text-center sm:text-left">
        <i class="fa-solid fa-circle-info text-indigo-400 shrink-0"></i> Selesai memakai perangkat bersama? Keluar dulu ya.
      </p>

      <form action="{{ route('logout') }}" method="POST" class="w-full sm:w-auto">
        @csrf
        <button type="submit"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold text-rose-600 bg-white border border-rose-200 hover:bg-rose-50 hover:border-rose-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 transition-colors">
          <i class="fa-solid fa-right-from-bracket text-xs"></i> Keluar
        </button>
      </form>
    </div>
  </section>
</div>

<script>
  (function () {
    // Sapaan berganti bahasa (berhenti jika pengguna memilih reduced motion)
    var sapaan = document.getElementById('sapaan');
    var daftar = ['Halo', 'Hello', 'Hola', 'Bonjour', 'こんにちは', '안녕하세요', 'Hallo', 'Ciao'];
    var i = 0;
    var kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (sapaan && !kurangiGerak) {
      setInterval(function () {
        sapaan.classList.add('keluar');
        setTimeout(function () {
          i = (i + 1) % daftar.length;
          sapaan.textContent = daftar[i];
          sapaan.classList.remove('keluar');
        }, 400);
      }, 2800);
    }

    // Salin email
    var tombol = document.getElementById('tombolSalin');
    var label  = document.getElementById('labelSalin');
    var email  = document.getElementById('emailGuru');

    if (tombol && email) {
      tombol.addEventListener('click', function () {
        var teks = email.textContent.trim();
        var selesai = function () {
          label.textContent = 'Tersalin';
          setTimeout(function () { label.textContent = 'Salin'; }, 1800);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(teks).then(selesai);
        }
      });
    }
  })();
</script>
@endsection