<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class MateriController extends Controller
{
    protected $database;
    protected $lastGeminiStatus = null;
    protected $lastGeminiRetryAfter = null; // detik, kalau Google kasih tahu kapan boleh coba lagi
    protected $lastGeminiKoneksi = null;    // 'koneksi' | 'timeout' kalau server tidak bisa menjangkau Gemini

    public function __construct()
    {
        $this->database = Firebase::firestore()->database();
    }

    private function getDataGuru()
    {
        $uid = session('firebase_user_id');
        $namaGuru = 'Guru';
        $emailGuru = '';

        if ($uid) {
            $docRef = $this->database->collection('guru')->document($uid);
            $snapshot = $docRef->snapshot();

            if ($snapshot->exists()) {
                $data = $snapshot->data();
                $namaGuru = $data['nama'] ?? 'Guru';
                $emailGuru = $data['email'] ?? '';
            }
        }

        return [
            'namaGuru' => $namaGuru,
            'emailGuru' => $emailGuru,
        ];
    }

    /**
     * Susun data sub pembahasan dari input form (dipakai store dan update).
     */
    private function susunSubPembahasan(Request $request): array
    {
        $subPembahasanData = [];

        if ($request->has('sub_bahasan') && is_array($request->sub_bahasan)) {
            foreach ($request->sub_bahasan as $sub) {
                $namaSub = $sub['nama_sub'] ?? '';

                $contohKalimatArray = [];
                if (isset($sub['kalimat']) && is_array($sub['kalimat'])) {
                    foreach ($sub['kalimat'] as $index => $kalimatText) {
                        $artiText = $sub['arti'][$index] ?? '';
                        if (!empty($kalimatText)) {
                            $contohKalimatArray[] = [
                                'kalimat' => $kalimatText,
                                'arti' => $artiText,
                            ];
                        }
                    }
                }

                $poinArray = [];
                if (isset($sub['poin_penting']) && is_array($sub['poin_penting'])) {
                    foreach ($sub['poin_penting'] as $poinText) {
                        if (!empty(trim($poinText))) {
                            $poinArray[] = trim($poinText);
                        }
                    }
                }

                if (!empty($namaSub)) {
                    $subPembahasanData[] = [
                        'nama_sub' => $namaSub,
                        'contoh_kalimat' => $contohKalimatArray,
                        'poin_penting' => $poinArray,
                    ];
                }
            }
        }

        return $subPembahasanData;
    }

    /**
     * Kuis dan pelafalan menyimpan JUDUL bab sebagai materi_id, jadi dua bab
     * dengan judul yang sama akan saling bertabrakan. Cek dulu sebelum menyimpan.
     */
    private function judulSudahDipakai(string $judul, ?string $kecualiId = null): bool
    {
        $target = mb_strtolower(trim($judul));

        foreach ($this->database->collection('materi_vokasi')->documents() as $doc) {
            if (!$doc->exists() || $doc->id() === $kecualiId) {
                continue;
            }
            if (mb_strtolower(trim($doc->data()['judul'] ?? '')) === $target) {
                return true;
            }
        }

        return false;
    }

    public function index()
    {
        $documents = $this->database->collection('materi_vokasi')->documents();
        $materiList = [];

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                $data['id'] = $doc->id();

                $kelas = trim($data['tingkat_kelas'] ?? 'X');
                if (in_array($kelas, ['X', '10', 'Kelas X'])) {
                    $data['tingkat_kelas_formatted'] = 'Kelas X';
                } elseif (in_array($kelas, ['XI', '11', 'Kelas XI'])) {
                    $data['tingkat_kelas_formatted'] = 'Kelas XI';
                } elseif (in_array($kelas, ['XII', '12', 'Kelas XII'])) {
                    $data['tingkat_kelas_formatted'] = 'Kelas XII';
                } else {
                    $data['tingkat_kelas_formatted'] = 'Kelas X';
                }

                $materiList[] = $data;
            }
        }

        $guru = $this->getDataGuru();
        return view('guru.materi.index', compact('materiList', 'guru'));
    }

    public function create()
    {
        $guru = $this->getDataGuru();
        return view('guru.materi.create', compact('guru'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul' => 'required|string',
            'deskripsi' => 'required|string',
            'tingkat_kelas' => 'required|string',
        ]);

        if ($this->judulSudahDipakai($request->judul)) {
            return back()->withInput()->withErrors([
                'judul' => 'Judul bab ini sudah dipakai. Gunakan judul yang berbeda.',
            ]);
        }

        $this->database->collection('materi_vokasi')->add([
            'judul' => trim($request->judul),
            'deskripsi' => $request->deskripsi,
            'kategori' => $request->kategori ?? '',
            'tingkat_kelas' => $request->tingkat_kelas,
            'dibuat_oleh' => session('firebase_user_id', 'Admin/Guru'),
            'ikon' => $request->ikon ?? 'fa-book',
            'status_persetujuan' => '',
            'peringkat' => 0,
            'sub_pembahasan' => $this->susunSubPembahasan($request),
            'created_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('guru.materi')->with('success', 'Materi berhasil ditambahkan');
    }

    public function edit($id)
    {
        $docRef = $this->database->collection('materi_vokasi')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.materi')->with('error', 'Materi tidak ditemukan');
        }

        $materi = $snapshot->data();
        $materi['id'] = $snapshot->id();
        $guru = $this->getDataGuru();

        return view('guru.materi.edit', compact('materi', 'guru'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'judul' => 'required|string',
            'deskripsi' => 'required|string',
            'tingkat_kelas' => 'required|string',
        ]);

        $docRef = $this->database->collection('materi_vokasi')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.materi')->with('error', 'Materi tidak ditemukan');
        }

        if ($this->judulSudahDipakai($request->judul, $id)) {
            return back()->withInput()->withErrors([
                'judul' => 'Judul bab ini sudah dipakai bab lain. Gunakan judul yang berbeda.',
            ]);
        }

        $judulLama = $snapshot->data()['judul'] ?? null;
        $judulBaru = trim($request->judul);

        $docRef->set([
            'judul' => $judulBaru,
            'deskripsi' => $request->deskripsi,
            'kategori' => $request->kategori ?? '',
            'tingkat_kelas' => $request->tingkat_kelas,
            'ikon' => $request->ikon ?? 'fa-book',
            'sub_pembahasan' => $this->susunSubPembahasan($request),
            'updated_at' => now()->toIso8601String(),
        ], ['merge' => true]);

        // Judul bab dipakai sebagai penghubung ke kuis, pelafalan, dan kamus.
        // Kalau judul berubah, perbarui semuanya supaya hubungan tidak putus.
        if ($judulLama !== null && trim($judulLama) !== $judulBaru) {
            try {
                $this->sinkronJudulRelasi($id, $judulLama, $judulBaru);
            } catch (\Throwable $e) {
                Log::error('Gagal sinkron judul bab ke data terkait', [
                    'materi_id' => $id,
                    'judul_lama' => $judulLama,
                    'judul_baru' => $judulBaru,
                    'pesan' => $e->getMessage(),
                ]);

                return redirect()->route('guru.materi')->with(
                    'error',
                    'Materi berhasil diubah, tetapi kuis/kamus terkait belum ikut diperbarui. Coba simpan ulang materi ini.'
                );
            }
        }

        return redirect()->route('guru.materi')->with('success', 'Materi berhasil diubah');
    }

    public function destroy($id)
    {
        $docRef = $this->database->collection('materi_vokasi')->document($id);
        $snapshot = $docRef->snapshot();

        if ($snapshot->exists()) {
            $judulBab = $snapshot->data()['judul'] ?? null;

            // Hapus dulu semua data yang terhubung ke bab ini: kuis (quiz),
            // tes pelafalan (kuis_suara), dan kosakata (kamus_vokasi).
            // Kalau langkah ini gagal, bab TIDAK dihapus supaya data tidak yatim.
            try {
                $dihapus = $this->hapusRelasiTerkait($id, $judulBab);
            } catch (\Throwable $e) {
                Log::error('Gagal menghapus data terkait bab', [
                    'materi_id' => $id,
                    'judul' => $judulBab,
                    'pesan' => $e->getMessage(),
                ]);

                return redirect()->route('guru.materi')->with(
                    'error',
                    'Materi belum dihapus karena data terkait (kuis/kamus) gagal dibersihkan. Coba lagi.'
                );
            }

            // Terakhir, hapus dokumen materi/bab utamanya.
            $docRef->delete();

            $pesan = 'Materi berhasil dihapus'
                . " beserta {$dihapus['quiz']} kuis, {$dihapus['kuis_suara']} tes pelafalan,"
                . " dan {$dihapus['kamus_vokasi']} kosakata di dalamnya.";

            return redirect()->route('guru.materi')->with('success', $pesan);
        }

        return redirect()->route('guru.materi')->with('error', 'Materi tidak ditemukan');
    }

    // ==========================================================
    // Relasi ke kuis, tes pelafalan, dan kamus
    //
    // PENTING -- cara tiap koleksi menyimpan hubungan ke bab BEDA-BEDA:
    // - quiz & kuis_suara menyimpan JUDUL bab di field `materi_id` (bukan ID dokumen).
    // - kamus_vokasi menyimpan ID DOKUMEN Firestore bab di field `materi_id`.
    // ==========================================================

    /**
     * Hapus semua kuis, tes pelafalan, dan kosakata yang terhubung ke satu bab.
     * Dipanggil SEBELUM dokumen materi/bab-nya sendiri dihapus.
     * Mengembalikan jumlah dokumen yang terhapus per koleksi.
     */
    private function hapusRelasiTerkait(string $materiId, ?string $judulBab): array
    {
        $jumlah = [
            'quiz' => 0,
            'kuis_suara' => 0,
            'kamus_vokasi' => 0,
        ];

        $judulBab = trim((string) $judulBab);

        if ($judulBab !== '') {
            $jumlah['quiz'] = $this->hapusDokumenByField('quiz', 'materi_id', $judulBab);
            $jumlah['kuis_suara'] = $this->hapusDokumenByField('kuis_suara', 'materi_id', $judulBab);
        }

        $jumlah['kamus_vokasi'] = $this->hapusDokumenByField('kamus_vokasi', 'materi_id', $materiId);

        return $jumlah;
    }

    /**
     * Saat judul bab diganti, perbarui field yang menyimpan judul di data terkait,
     * supaya kuis/pelafalan/kamus tidak "putus hubungan" dari bab induknya.
     */
    private function sinkronJudulRelasi(string $materiId, string $judulLama, string $judulBaru): void
    {
        $judulLama = trim($judulLama);
        $judulBaru = trim($judulBaru);

        if ($judulLama === '' || $judulLama === $judulBaru) {
            return;
        }

        // quiz & kuis_suara: field materi_id ITU SENDIRI berisi judul, jadi harus diganti.
        $this->updateDokumenByField('quiz', 'materi_id', $judulLama, ['materi_id' => $judulBaru]);
        $this->updateDokumenByField('kuis_suara', 'materi_id', $judulLama, ['materi_id' => $judulBaru]);

        // kamus_vokasi: materi_id tetap (ID dokumen tidak berubah), yang diperbarui cuma
        // field 'bab' (salinan judul yang ditampilkan).
        $this->updateDokumenByField('kamus_vokasi', 'materi_id', $materiId, ['bab' => $judulBaru]);
    }

    /** Hapus semua dokumen di satu koleksi yang field-nya cocok dengan nilai tertentu. */
    private function hapusDokumenByField(string $koleksi, string $field, string $nilai): int
    {
        $hitung = 0;

        $documents = $this->database->collection($koleksi)
            ->where($field, '=', $nilai)
            ->documents();

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $doc->reference()->delete();
                $hitung++;
            }
        }

        return $hitung;
    }

    /** Perbarui (merge) field tertentu pada semua dokumen yang cocok dengan nilai field lain. */
    private function updateDokumenByField(string $koleksi, string $field, string $nilai, array $dataBaru): void
    {
        $documents = $this->database->collection($koleksi)
            ->where($field, '=', $nilai)
            ->documents();

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $doc->reference()->set($dataBaru, ['merge' => true]);
            }
        }
    }

    // ==========================================================
    // FITUR AI: Generate materi otomatis dari judul
    // ==========================================================
    public function generateAI(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'tingkat_kelas' => 'nullable|string',
        ]);

        $konteks = "Judul materi: {$request->judul}.";
        if ($request->tingkat_kelas) $konteks .= " Tingkat kelas: {$request->tingkat_kelas}.";

        $data = $this->structureMateriWithAI($konteks);

        if (!$data) {
            return $this->jsonGagalAI();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ==========================================================
    // FITUR AI: Generate / lengkapi SATU sub pembahasan
    //
    // Mode 1 (nama_sub diisi)  : AI melengkapi sub tersebut. Kalau sudah ada
    //                            contoh/poin, AI hanya menambah yang BARU.
    // Mode 2 (nama_sub kosong) : AI mengusulkan sub baru yang belum ada di daftar.
    // ==========================================================
    public function generateSubAI(Request $request)
    {
        $request->validate([
            'judul'            => 'required|string|max:255',
            'deskripsi'        => 'nullable|string|max:1000',
            'tingkat_kelas'    => 'nullable|string|max:20',
            'nama_sub'         => 'nullable|string|max:255',
            'sub_yang_ada'     => 'nullable|array|max:30',
            'sub_yang_ada.*'   => 'nullable|string|max:255',
            'kalimat_ada'      => 'nullable|array|max:30',
            'kalimat_ada.*'    => 'nullable|string|max:500',
            'poin_ada'         => 'nullable|array|max:30',
            'poin_ada.*'       => 'nullable|string|max:500',
        ]);

        $namaSub     = trim((string) $request->input('nama_sub', ''));
        $subLain     = $this->bersihkanDaftar($request->input('sub_yang_ada', []));
        $kalimatAda  = $this->bersihkanDaftar($request->input('kalimat_ada', []));
        $poinAda     = $this->bersihkanDaftar($request->input('poin_ada', []));

        $daftarSubLain = empty($subLain) ? '(belum ada)' : '- ' . implode("\n- ", $subLain);

        if ($namaSub !== '') {
            $instruksi = "Lengkapi isi untuk sub pembahasan berjudul \"{$namaSub}\". Pada JSON balasan, isi \"nama_sub\" dengan judul yang sama persis.";

            if (!empty($kalimatAda) || !empty($poinAda)) {
                $instruksi .= "\n\nSub ini SUDAH punya isi berikut. Berikan HANYA contoh kalimat dan poin penting BARU yang melengkapi dan tidak mengulang isi yang sudah ada.";
                if (!empty($kalimatAda)) {
                    $instruksi .= "\nContoh kalimat yang sudah ada:\n- " . implode("\n- ", $kalimatAda);
                }
                if (!empty($poinAda)) {
                    $instruksi .= "\nPoin penting yang sudah ada:\n- " . implode("\n- ", $poinAda);
                }
            }
        } else {
            $instruksi = "Usulkan SATU sub pembahasan baru yang melengkapi materi dan BELUM ada di daftar sub pembahasan lain di bawah.";
        }

        $prompt = <<<PROMPT
Kamu adalah asisten guru bahasa Inggris vokasi SMK jurusan RPL (Rekayasa Perangkat Lunak). {$instruksi}

Judul materi: {$request->judul}
Deskripsi materi: {$request->deskripsi}
Tingkat kelas: {$request->tingkat_kelas}
Sub pembahasan lain yang sudah ada di materi ini:
{$daftarSubLain}

Balas dalam format JSON PERSIS seperti ini (tanpa teks lain di luar JSON):

{
  "nama_sub": "nama sub pembahasan",
  "contoh_kalimat": [
    {"kalimat": "contoh kalimat", "arti": "artinya"}
  ],
  "poin_penting": ["poin 1", "poin 2"]
}

Buat 3-4 contoh kalimat & arti, dan 3-5 poin penting. Setiap poin penting WAJIB berupa kalimat penjelasan yang utuh dan jelas (bukan sekadar kata kunci singkat). Semua contoh kalimat dan istilah harus berkaitan dengan dunia IT/pengembangan perangkat lunak (RPL), JANGAN memakai contoh dari jurusan vokasi lain. Gunakan bahasa Indonesia yang sederhana dan mudah dipahami murid SMK.
PROMPT;

        // Key khusus Materi, supaya tidak berebut kuota dengan Kuis/Kamus.
        $apiKey = env('GEMINI_API_KEY_MATERI') ?: config('services.gemini.key');

        if (empty($apiKey)) {
            Log::error('GEMINI_API_KEY_MATERI / GEMINI_API_KEY kosong / tidak terbaca dari config. Cek .env dan jalankan php artisan config:clear');
            return $this->jsonGagalAI();
        }

        $this->lastGeminiStatus = null;
        $this->lastGeminiRetryAfter = null;
        $this->lastGeminiKoneksi = null;

        $rawText = $this->mintaJsonDariGemini($apiKey, $prompt);

        if ($rawText === null) {
            return $this->jsonGagalAI();
        }

        $data = json_decode($rawText, true);

        if (!is_array($data)) {
            Log::error('Gagal parsing hasil Gemini jadi JSON (Materi Sub AI)', ['raw_text' => $rawText]);
            return $this->jsonGagalAI();
        }

        // Rapikan hasil supaya frontend selalu menerima struktur yang konsisten
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $contoh = [];
        foreach (($data['contoh_kalimat'] ?? []) as $ck) {
            if (is_array($ck) && !empty(trim((string) ($ck['kalimat'] ?? '')))) {
                $contoh[] = [
                    'kalimat' => trim((string) $ck['kalimat']),
                    'arti'    => trim((string) ($ck['arti'] ?? '')),
                ];
            }
        }

        $poin = [];
        foreach (($data['poin_penting'] ?? []) as $p) {
            if (is_string($p) && trim($p) !== '') {
                $poin[] = trim($p);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nama_sub'       => $namaSub !== '' ? $namaSub : trim((string) ($data['nama_sub'] ?? '')),
                'contoh_kalimat' => $contoh,
                'poin_penting'   => $poin,
            ],
        ]);
    }

    // ==========================================================
    // FITUR AI: Ekstrak materi otomatis dari file PDF/Word
    // ==========================================================
    public function extractFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $text = '';

        try {
            if ($ext === 'pdf') {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($file->getPathname());
                $text = $pdf->getText();
            } else {
                $phpWord = IOFactory::load($file->getPathname());
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        } elseif (method_exists($element, 'getElements')) {
                            foreach ($element->getElements() as $sub) {
                                if (method_exists($sub, 'getText')) {
                                    $text .= $sub->getText() . "\n";
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Gagal membaca isi file (PDF/Word)', [
                'message' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            return response()->json(['success' => false, 'message' => 'File tidak bisa dibaca (mungkin rusak, terkunci, atau hasil scan gambar tanpa teks).'], 422);
        }

        // Bersihkan karakter yang bukan UTF-8 valid, biar nggak gagal dikirim ke Gemini
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\r\t]/u', '', $text); // buang karakter kontrol aneh
        $text = trim(mb_substr($text, 0, 12000));

        Log::info('Hasil ekstraksi file', [
            'file' => $file->getClientOriginalName(),
            'panjang_teks' => strlen($text),
            'contoh_awal' => mb_substr($text, 0, 200),
        ]);

        if (empty($text)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada teks yang bisa dibaca dari file ini (kemungkinan hasil scan/gambar, bukan teks asli).'], 422);
        }

        $data = $this->structureMateriWithAI(
            "Berikut isi dokumen materi vokasi. Susun jadi struktur materi (termasuk judul yang cocok):\n\n{$text}",
            true
        );

        if (!$data) {
            return $this->jsonGagalAI();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ==========================================================
    // Pesan limit AI (429) yang jelas + kapan bisa dipakai lagi.
    // Pola sama persis dengan QuizController / KamusVokasiController,
    // supaya guru melihat pesan yang konsisten di semua fitur AI.
    // ==========================================================

    /**
     * Baca jeda retry (dalam detik) dari error Google, kalau mereka kasih tahu.
     * Google biasanya menyertakan ini untuk limit PER MENIT (bukan limit harian).
     */
    private function ambilRetryDelay(?array $body): ?int
    {
        $details = data_get($body, 'error.details', []);
        if (!is_array($details)) {
            return null;
        }

        foreach ($details as $d) {
            if (($d['@type'] ?? '') === 'type.googleapis.com/google.rpc.RetryInfo') {
                $delay = $d['retryDelay'] ?? null; // contoh: "23s" atau "1.500s"
                if ($delay && preg_match('/([\d.]+)s/', (string) $delay, $m)) {
                    return (int) ceil((float) $m[1]);
                }
            }
        }

        return null;
    }

    /** Ubah detik jadi teks yang mudah dibaca: "25 detik", "12 menit", "3 jam 20 menit". */
    private function formatDurasi(int $detik): string
    {
        if ($detik < 60) {
            return $detik . ' detik';
        }

        if ($detik < 3600) {
            return (int) ceil($detik / 60) . ' menit';
        }

        $jam = intdiv($detik, 3600);
        $menit = (int) ceil(($detik % 3600) / 60);
        if ($menit === 60) {
            $jam++;
            $menit = 0;
        }

        return $menit > 0 ? "{$jam} jam {$menit} menit" : "{$jam} jam";
    }

    /**
     * Info kuota AI (Gemini) yang habis (HTTP 429): pesan untuk guru + kapan bisa dipakai lagi.
     * Semua waktu dipaksa ke WIB, supaya tidak ikut zona waktu bawaan server (sering UTC).
     */
    private function infoLimitAI(): array
    {
        $sekarang = now('Asia/Jakarta');

        if ($this->lastGeminiRetryAfter) {
            // Google memberi tahu jeda pastinya (biasanya limit per menit).
            $detik = max(1, (int) $this->lastGeminiRetryAfter);
            $kapan = $sekarang->copy()->addSeconds($detik);
            $harian = false;
        } else {
            // Tidak ada jeda spesifik -> biasanya kuota gratis HARIAN, yang direset tengah malam
            // waktu Pasifik (Amerika). Dihitung lalu dikonversi ke WIB.
            $kapan = now('America/Los_Angeles')->addDay()->startOfDay()->setTimezone('Asia/Jakarta');
            $detik = max(1, $kapan->getTimestamp() - $sekarang->getTimestamp());
            $harian = true;
        }

        $jam = $kapan->format('H:i');
        $tanggal = $kapan->copy()->locale('id')->translatedFormat('j F Y');
        $label = "pukul {$jam} WIB, {$tanggal}";
        $sisa = $this->formatDurasi($detik);

        if ($harian) {
            $pesan = "Kuota AI (Gemini) hari ini sudah habis. Ini bukan error aplikasi. "
                . "Fitur generate materi akan bisa dipakai lagi sekitar {$label} (kira-kira {$sisa} lagi, kuota harian direset otomatis). "
                . "Sementara itu, materi tetap bisa diisi manual.";
        } else {
            $pesan = "Batas pemakaian AI (Gemini) sedang tercapai. Ini bukan error aplikasi. "
                . "Coba lagi sekitar {$sisa} lagi ({$label}). Sementara itu, materi tetap bisa diisi manual.";
        }

        return [
            'pesan' => $pesan,
            'label' => $label,
        ];
    }

    /** Susun pesan gagal yang jelas penyebabnya, sesuai status panggilan Gemini terakhir. */
    private function pesanGagalAI(): array
    {
        $status = $this->lastGeminiStatus;

        if ($status === 429) {
            return ['status' => 429, 'pesan' => $this->infoLimitAI()['pesan']];
        }

        if ($this->lastGeminiKoneksi === 'timeout') {
            return [
                'status' => 504,
                'pesan' => 'Layanan AI terlalu lama merespons, jadi permintaan dihentikan. Ini bukan masalah kuota. Coba lagi sebentar lagi, atau kurangi ukuran dokumennya.',
            ];
        }

        if ($this->lastGeminiKoneksi === 'koneksi') {
            return [
                'status' => 503,
                'pesan' => 'Server aplikasi tidak bisa terhubung ke layanan AI (Gemini). Periksa koneksi internet di komputer/server yang menjalankan aplikasi ini, lalu coba lagi. Ini bukan masalah kuota.',
            ];
        }

        if ($status === 503) {
            return ['status' => 503, 'pesan' => 'Server AI (Gemini) sedang sibuk karena banyak pengguna. Tunggu sebentar lalu coba lagi.'];
        }

        if (in_array($status, [401, 403], true)) {
            return ['status' => 502, 'pesan' => 'API key Gemini ditolak. Hubungi admin untuk memeriksa GEMINI_API_KEY_MATERI di file .env.'];
        }

        if ($status === 404) {
            return ['status' => 502, 'pesan' => 'Model AI yang dipakai sudah tidak tersedia. Hubungi admin untuk memeriksa GEMINI_MODEL di file .env.'];
        }

        return ['status' => 500, 'pesan' => 'Gagal memproses materi dari AI/Dokumen. Coba lagi, dan kalau tetap gagal hubungi admin.'];
    }

    /** Respons JSON seragam untuk kegagalan generate materi. */
    private function jsonGagalAI()
    {
        $hasil = $this->pesanGagalAI();
        return response()->json(['success' => false, 'message' => $hasil['pesan']], $hasil['status']);
    }

    /**
     * Ubah array input jadi daftar string bersih (tanpa elemen kosong).
     */
    private function bersihkanDaftar($daftar): array
    {
        if (!is_array($daftar)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), $daftar),
            fn ($v) => $v !== ''
        ));
    }

    /**
     * Panggil Gemini dengan model utama (GEMINI_MODEL). Kalau kena limit (429), otomatis
     * coba sekali lagi dengan model cadangan (GEMINI_FALLBACK_MODEL) -- tiap model biasanya
     * punya jatah limit gratis sendiri-sendiri yang terpisah.
     */
    private function mintaJsonDariGemini(string $apiKey, string $prompt): ?string
    {
        $modelUtama = config('services.gemini.model', 'gemini-flash-latest');
        $modelCadangan = config('services.gemini.fallback_model');

        $rawText = $this->panggilModelGemini($apiKey, $modelUtama, $prompt);

        if ($rawText === null && $this->lastGeminiStatus === 429 && $modelCadangan && $modelCadangan !== $modelUtama) {
            Log::info("Model {$modelUtama} kena limit, coba model cadangan {$modelCadangan} (Materi AI).");
            $retryAsli = $this->lastGeminiRetryAfter;
            $this->lastGeminiStatus = null;
            $this->lastGeminiRetryAfter = null;
            $rawText = $this->panggilModelGemini($apiKey, $modelCadangan, $prompt);

            if ($rawText === null && $this->lastGeminiStatus !== 429) {
                Log::warning("Model cadangan {$modelCadangan} gagal (status {$this->lastGeminiStatus}). Periksa GEMINI_FALLBACK_MODEL di .env.");
                $this->lastGeminiStatus = 429;
                $this->lastGeminiRetryAfter = $retryAsli;
            }
        }

        return $rawText;
    }

    /** Satu kali panggilan nyata ke Gemini, untuk satu model tertentu. */
    private function panggilModelGemini(string $apiKey, string $model, string $prompt): ?string
    {
        try {
            $response = Http::timeout(60)
                ->retry(2, 3000, function ($exception, $request) {
                    // ulang otomatis kalau errornya 503 (server Gemini sibuk)
                    return $exception instanceof \Illuminate\Http\Client\RequestException
                        && $exception->response->status() === 503;
                })
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'contents' => [
                            ['parts' => [['text' => $prompt]]],
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                        ],
                    ]
                );
        } catch (\Throwable $e) {
            $status = null;
            $body = null;
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $status = $e->response->status();
                $body = $e->response->json();
            }
            $this->lastGeminiStatus = $status;
            $this->lastGeminiRetryAfter = $this->ambilRetryDelay($body);
            $this->lastGeminiKoneksi = null;
            if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                $this->lastGeminiKoneksi = stripos($e->getMessage(), 'timed out') !== false ? 'timeout' : 'koneksi';
            }

            Log::error('Gemini API exception (Materi AI, model ' . $model . ')', [
                'message' => $e->getMessage(),
                'status' => $status,
            ]);
            return null;
        }

        if (!$response->successful()) {
            Log::error('Gemini API gagal (Materi AI, model ' . $model . ')', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->lastGeminiStatus = $response->status();
            $this->lastGeminiRetryAfter = $this->ambilRetryDelay($response->json());
            return null;
        }

        $rawText = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!is_string($rawText) || trim($rawText) === '') {
            Log::error('Respons Gemini kosong (Materi AI, model ' . $model . ')', ['body' => $response->body()]);
            return null;
        }

        return $rawText;
    }

    // ==========================================================
    // Helper: kirim konteks ke Gemini, minta hasil JSON terstruktur
    // ==========================================================
    private function structureMateriWithAI(string $konteks, bool $sertakanJudul = false): ?array
    {
        $skemaJudul = $sertakanJudul ? '"judul": "judul materi yang sesuai",' : '';

        $prompt = <<<PROMPT
Kamu adalah asisten guru bahasa Inggris vokasi SMK jurusan RPL (Rekayasa Perangkat Lunak), yang sangat berpengalaman menjelaskan hal rumit dengan cara yang mudah dimengerti. Buatkan materi pembelajaran yang LENGKAP dan MENDALAM (boleh panjang, yang penting penjelasannya tuntas dan tidak terasa terpotong), dalam format JSON PERSIS seperti ini (tanpa teks lain di luar JSON):

{
  {$skemaJudul}
  "deskripsi": "ringkasan 2-4 kalimat: apa topik ini, kenapa penting dipelajari, dan di mana dipakai di dunia kerja vokasi",
  "sub_bahasan": [
    {
      "nama_sub": "nama sub pembahasan yang spesifik (bukan judul umum)",
      "contoh_kalimat": [
        {"kalimat": "contoh kalimat bahasa Inggris yang relevan", "arti": "terjemahan lengkap dalam bahasa Indonesia"}
      ],
      "poin_penting": [
        "poin penting ditulis sebagai KALIMAT PENJELASAN LENGKAP (bukan sekadar kata kunci/frasa pendek), supaya murid paham tanpa perlu guru menjelaskan ulang secara lisan"
      ]
    }
  ]
}

Ketentuan STRUKTUR sub pembahasan (WAJIB diikuti, ini urutan baku materi bahasa Inggris, jangan diganti strukturnya):
- KALAU topik ini adalah JENIS TEKS (misalnya descriptive text, report text, narrative text, recount text, procedure text, dan sejenisnya), susun TEPAT sub pembahasan berikut, dalam urutan ini:
  1. "Pengertian dan Tujuan [nama teks]" -- definisi teks ini, untuk apa teks ini dipakai/ditulis.
  2. "Struktur Teks (Generic Structure)" -- bagian-bagian pembangun teks ini secara berurutan (mis. identification, description, dst. tergantung jenis teksnya), masing-masing dijelaskan fungsinya.
  3. "Unsur Kebahasaan (Language Features)" -- ciri-ciri tata bahasa khas teks ini (tenses yang dipakai, jenis kata yang sering muncul, pola kalimat khas, dsb).
  4. (opsional, kalau relevan) "Contoh Teks Lengkap" -- satu contoh teks utuh (bukan potongan kalimat terpisah) sesuai jenis teks ini, lengkap dengan terjemahannya.
- KALAU topik ini BUKAN jenis teks (misalnya grammar/tenses tertentu, kosakata suatu bidang, atau topik lain), buat 3 sampai 5 sub pembahasan dengan urutan logis dari pengertian/konsep dasar -> istilah/aturan kunci -> cara pakai/penerapan praktis -> contoh situasi nyata di dunia kerja vokasi terkait.
- Setiap sub pembahasan diberi 3 sampai 5 contoh kalimat & artinya, yang benar-benar relevan dan bervariasi (bukan pengulangan pola yang sama).
- Setiap sub pembahasan diberi 4 sampai 6 poin penting. Setiap poin WAJIB berupa kalimat penjelasan yang utuh dan jelas (idealnya 1-2 kalimat), bukan daftar kata kunci singkat seperti "Penting untuk komunikasi" -- tulis selengkap "Kalimat ini penting dikuasai karena sering dipakai saat [konteks], misalnya ketika [contoh situasi]".
- Gunakan bahasa Indonesia yang SEDERHANA dan mudah dipahami murid SMK (hindari istilah akademis yang berat tanpa penjelasan), tetapi jangan mengorbankan kelengkapan isi -- lebih baik agak panjang tapi jelas, daripada singkat tapi murid masih bingung.
- Jangan membuat sub pembahasan yang isinya tumpang tindih satu sama lain; tiap sub harus punya fokus yang jelas dan berbeda.
- WAJIB: semua contoh kalimat, istilah, dan situasi/skenario kerja yang disebutkan harus berkaitan dengan dunia IT / pengembangan perangkat lunak (RPL) -- misalnya pemrograman, coding, debugging, aplikasi, website, database, sistem, server, developer, testing software, dsb. JANGAN memakai contoh dari jurusan vokasi lain seperti otomotif, perhotelan, tata boga, akuntansi, teknik mesin, atau sejenisnya, meskipun topik bahasa Inggrisnya umum.

Konteks: {$konteks}
PROMPT;

        // Key khusus Materi, supaya tidak berebut kuota dengan Kuis/Kamus.
        // Kalau GEMINI_API_KEY_MATERI belum diisi di .env, otomatis pakai GEMINI_API_KEY biasa.
        $apiKey = env('GEMINI_API_KEY_MATERI') ?: config('services.gemini.key');

        if (empty($apiKey)) {
            Log::error('GEMINI_API_KEY_MATERI / GEMINI_API_KEY kosong / tidak terbaca dari config. Cek .env dan jalankan php artisan config:clear');
            return null;
        }

        // Reset status Gemini dari percobaan sebelumnya, supaya tidak membaca sisa yang lama.
        $this->lastGeminiStatus = null;
        $this->lastGeminiRetryAfter = null;
        $this->lastGeminiKoneksi = null;

        $rawText = $this->mintaJsonDariGemini($apiKey, $prompt);

        if ($rawText === null) {
            return null;
        }

        $parsed = json_decode($rawText, true);

        if (!is_array($parsed)) {
            Log::error('Gagal parsing hasil Gemini jadi JSON (Materi AI)', ['raw_text' => $rawText]);
            return null;
        }

        return $parsed;
    }
}