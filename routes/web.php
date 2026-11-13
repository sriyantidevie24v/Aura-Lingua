<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\MateriController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\KamusVokasiController;

// Halaman Utama
Route::get('/', function () {
    return view('welcome');
});

// --- Rute Autentikasi Firebase (Login & Register Guru) ---
Route::get('/login', [FirebaseAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [FirebaseAuthController::class, 'login']);

Route::get('/register', [FirebaseAuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [FirebaseAuthController::class, 'register']);

Route::post('/logout', [FirebaseAuthController::class, 'logout'])->name('logout');


// --- Rute Panel Guru (Dilindungi Sesi Firebase) ---
Route::middleware(['web'])->prefix('guru')->name('guru.')->group(function () {

    // Halaman Ringkasan Dashboard Guru
    Route::get('/ringkasan', [GuruController::class, 'ringkasan'])->name('ringkasan');

    // Halaman Profil Guru (Dipindahkan ke dalam grup agar namanya menjadi guru.profil)
    Route::get('/profil', [GuruController::class, 'profil'])->name('profil');

    // Halaman Progres Murid
    // Route::get('/murid', [GuruController::class, 'murid'])->name('murid');

    // --- Rute Permintaan Pindah Kelas (GuruController) ---
    Route::get('/permintaan', [GuruController::class, 'permintaanKelas'])->name('permintaan.index');
    Route::post('/permintaan/{uid}/setujui', [GuruController::class, 'setujuiPermintaanKelas'])->name('permintaan.setujui');
    Route::post('/permintaan/{uid}/tolak', [GuruController::class, 'tolakPermintaanKelas'])->name('permintaan.tolak');

    // --- Rute Kelola Kode Akses Sekolah (GuruController) ---
    Route::get('/kode-akses', [GuruController::class, 'kodeAkses'])->name('kode-akses.edit');
    Route::post('/kode-akses', [GuruController::class, 'updateKodeAkses'])->name('kode-akses.update');

    // --- Rute AI untuk Kuis/Pelafalan (QuizController) ---
    // Rute statis diletakkan sebelum rute dengan parameter {id}.
    // URL tetap /guru/kuis/preview-ai dan nama tetap guru.kuis.previewAI (sama seperti sebelumnya).
    Route::post('/kuis/preview-ai', [QuizController::class, 'previewAI'])->name('kuis.previewAI');
    Route::post('/kuis/generate-ai', [QuizController::class, 'generateAI'])->name('kuis.generateAI');

    // --- Rute Manajemen Kuis Biasa (QuizController) ---
    Route::get('/kuis', [QuizController::class, 'index'])->name('kuis');
    Route::get('/kuis/create', [QuizController::class, 'create'])->name('kuis.create');
    Route::post('/kuis', [QuizController::class, 'store'])->name('kuis.store');
    Route::get('/kuis/{id}/edit', [QuizController::class, 'edit'])->name('kuis.edit');
    Route::put('/kuis/{id}', [QuizController::class, 'update'])->name('kuis.update');
    Route::delete('/kuis/{id}', [QuizController::class, 'destroy'])->name('kuis.destroy');

    // --- Rute Manajemen Kuis Suara / Tes Pelafalan (QuizController) ---
    Route::get('/kuis-suara/create', [QuizController::class, 'createSuara'])->name('kuis_suara.create');
    Route::post('/kuis-suara', [QuizController::class, 'storeSuara'])->name('kuis_suara.store');
    Route::get('/kuis-suara/{id}/edit', [QuizController::class, 'editSuara'])->name('kuis_suara.edit');
    Route::put('/kuis-suara/{id}', [QuizController::class, 'updateSuara'])->name('kuis_suara.update');
    Route::delete('/kuis-suara/{id}', [QuizController::class, 'destroySuara'])->name('kuis_suara.destroy');

    // --- Rute Manajemen Kamus Vokasi (KamusVokasiController) ---
    Route::get('/kamus-vokasi', [KamusVokasiController::class, 'index'])->name('kamus_vokasi.index');
    Route::get('/kamus-vokasi/create', [KamusVokasiController::class, 'create'])->name('kamus_vokasi.create');
    Route::post('/kamus-vokasi', [KamusVokasiController::class, 'store'])->name('kamus_vokasi.store');

    // Saran kosakata dari materi & kuis (AI) -> dipakai halaman tambah dan edit kamus
    Route::post('/kamus-vokasi/saran', [KamusVokasiController::class, 'saran'])->name('kamus_vokasi.saran');

    Route::get('/kamus-vokasi/{id}/edit', [KamusVokasiController::class, 'edit'])->name('kamus_vokasi.edit');
    Route::put('/kamus-vokasi/{id}', [KamusVokasiController::class, 'update'])->name('kamus_vokasi.update');
    Route::delete('/kamus-vokasi/{id}', [KamusVokasiController::class, 'destroy'])->name('kamus_vokasi.destroy');

    // --- Rute Manajemen Materi (Menggunakan MateriController) ---
    Route::get('/materi', [MateriController::class, 'index'])->name('materi');
    Route::get('/materi/create', [MateriController::class, 'create'])->name('materi.create');
    Route::post('/materi', [MateriController::class, 'store'])->name('materi.store');

    // --- Rute Fitur AI untuk Materi (MateriController) ---
    Route::post('/materi/generate-ai', [MateriController::class, 'generateAI'])->name('materi.generateAI');
    Route::post('/materi/generate-sub-ai', [MateriController::class, 'generateSubAI'])->name('materi.generateSubAI');
    Route::post('/materi/extract-file', [MateriController::class, 'extractFile'])->name('materi.extractFile');

    // Rute dinamis dengan parameter {id} diletakkan di bawah rute statis
    Route::get('/materi/{id}/edit', [MateriController::class, 'edit'])->name('materi.edit');
    Route::put('/materi/{id}', [MateriController::class, 'update'])->name('materi.update');
    Route::delete('/materi/{id}', [MateriController::class, 'destroy'])->name('materi.destroy');

});