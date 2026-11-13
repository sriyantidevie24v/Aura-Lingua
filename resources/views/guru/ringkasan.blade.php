@extends('layouts.app')

@section('content')

<!-- Topbar -->
<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-800">
    Halo, {{ $guru['namaGuru'] ?? 'Guru' }} 👋
  </h2>
  <p class="text-sm text-gray-600 mt-0.5">Gambaran umum aktivitas belajar semua murid.</p>
</div>

<!-- Notifikasi Permintaan Pindah Kelas (hanya muncul kalau ada yang menunggu) -->
@if(Route::has('guru.permintaan.index') && !empty($totalPermintaanMenunggu))
  <a href="{{ route('guru.permintaan.index') }}"
     class="mb-6 flex items-center justify-between gap-4 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 hover:bg-amber-100/70 transition group">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
        <i class="fa-solid fa-user-clock"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-amber-900">
          {{ $totalPermintaanMenunggu }} permintaan pindah kelas menunggu persetujuan
        </p>
        <p class="text-xs text-amber-700/80 mt-0.5">Klik untuk meninjau dan memprosesnya sekarang.</p>
      </div>
    </div>
    <span class="text-xs font-bold text-amber-700 flex items-center gap-1 shrink-0 group-hover:gap-2 transition-all">
      Tinjau <i class="fa-solid fa-arrow-right text-[11px]"></i>
    </span>
  </a>
@endif

<!-- Stat Strip Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition">
    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-sm">
      <i class="fa-solid fa-user-group"></i>
    </div>
    <h3 class="text-3xl font-extrabold text-gray-900 mt-4">{{ $totalMurid ?? 0 }}</h3>
    <p class="text-xs text-gray-500 font-semibold mt-1">Total Murid</p>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition">
    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-sm">
      <i class="fa-solid fa-book"></i>
    </div>
    <h3 class="text-3xl font-extrabold text-gray-900 mt-4">{{ $totalMateri ?? 0 }}</h3>
    <p class="text-xs text-gray-500 font-semibold mt-1">Total Materi</p>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition">
    <div class="w-10 h-10 bg-violet-50 text-violet-600 rounded-xl flex items-center justify-center text-sm">
      <i class="fa-solid fa-circle-question"></i>
    </div>
    <h3 class="text-3xl font-extrabold text-gray-900 mt-4">{{ $totalKuis ?? 0 }}</h3>
    <p class="text-xs text-gray-500 font-semibold mt-1">Total Soal Kuis</p>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition">
    <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center text-sm">
      <i class="fa-solid fa-microphone"></i>
    </div>
    <h3 class="text-3xl font-extrabold text-gray-900 mt-4">{{ $totalTesSuara ?? 0 }}</h3>
    <p class="text-xs text-gray-500 font-semibold mt-1">Total Teks Tes Suara</p>
  </div>
</div>

@endsection