<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    protected $firestore;
    protected $lastGeminiStatus = null;
    protected $lastGeminiRetryAfter = null; // detik, kalau Google kasih tahu kapan boleh coba lagi
    protected $lastGeminiKoneksi = null;    // 'koneksi' | 'timeout' kalau server tidak bisa menjangkau Gemini

    // Cache peta "bab -> tingkat kelas" selama satu request, supaya koleksi
    // materi_vokasi tidak dibaca berulang-ulang.
    protected $petaKelasBab = null;

    public function __construct()
    {
        $this->firestore = app('firebase.firestore')->database();
    }

    // ==================== HELPER KELAS (IKUT MATERI) ====================

    /**
     * Samakan berbagai bentuk penulisan kelas ("X", "10", "Kelas XI", "xii", dst.)
     * menjadi salah satu dari: X, XI, XII. Kalau tidak dikenali, dianggap X
     * (sama seperti perilaku halaman Materi).
     */
    private function normalisasiKelas(?string $raw): string
    {
        $k = strtoupper(trim((string) $raw));

        if (preg_match('/\b(XII|XI|X)\b/', $k, $m)) {
            return $m[1];
        }

        if (preg_match('/\b(12|11|10)\b/', $k, $m)) {
            return ['10' => 'X', '11' => 'XI', '12' => 'XII'][$m[1]];
        }

        return 'X';
    }

    /**
     * Bangun peta bab -> kelas dari koleksi materi_vokasi.
     * Dikunci dengan DUA kunci: ID dokumen dan JUDUL bab (huruf kecil),
     * karena kuis/pelafalan menyimpan judul bab sebagai materi_id.
     */
    private function bangunPetaKelasBab(): array
    {
        if ($this->petaKelasBab !== null) {
            return $this->petaKelasBab;
        }

        $peta = [];
        foreach ($this->firestore->collection('materi_vokasi')->documents() as $doc) {
            if (!$doc->exists()) {
                continue;
            }

            $d = $doc->data();
            $kelas = $this->normalisasiKelas($d['tingkat_kelas'] ?? 'X');

            $peta[$doc->id()] = $kelas;

            $judul = mb_strtolower(trim((string) ($d['judul'] ?? '')));
            if ($judul !== '') {
                $peta[$judul] = $kelas;
            }
        }

        return $this->petaKelasBab = $peta;
    }

    /**
     * Cari tingkat kelas dari bab yang dipilih ($materiId = judul bab atau ID dokumen).
     * Mengembalikan null kalau bab tidak ditemukan.
     */
    private function kelasUntukBab(?string $materiId, ?array $peta = null): ?string
    {
        $materiId = trim((string) $materiId);
        if ($materiId === '') {
            return null;
        }

        $peta = $peta ?? $this->bangunPetaKelasBab();

        return $peta[$materiId] ?? $peta[mb_strtolower($materiId)] ?? null;
    }

    /**
     * Tulis perbaikan tingkatan_kelas ke Firestore secara massal (batch).
     * $daftar: [['koleksi' => 'quiz'|'kuis_suara', 'id' => ..., 'kelas' => 'XI'], ...]
     * Kalau gagal, hanya dicatat di log -- tampilan halaman tetap benar
     * karena kelasnya sudah dikoreksi di memori.
     */
    private function sinkronkanKelasKeFirestore(array $daftar): void
    {
        if (empty($daftar)) {
            return;
        }

        try {
            foreach (array_chunk($daftar, 400) as $kelompok) {
                $batch = $this->firestore->batch();

                foreach ($kelompok as $item) {
                    $ref = $this->firestore->collection($item['koleksi'])->document($item['id']);
                    $batch->set($ref, ['tingkatan_kelas' => $item['kelas']], ['merge' => true]);
                }

                $batch->commit();
            }
        } catch (\Throwable $e) {
            Log::error('Gagal menyamakan tingkatan_kelas soal dengan kelas materi', [
                'jumlah' => count($daftar),
                'pesan' => $e->getMessage(),
            ]);
        }
    }

    // ==================== HELPER CEK SOAL GANDA ====================

    /**
     * Rapikan teks untuk perbandingan: huruf kecil, tanda baca dan spasi ganda dibuang.
     * Jadi "What is it?" dan "what  is it" dianggap sama.
     */
    private function normalisasi(?string $teks): string
    {
        $teks = mb_strtolower(trim((string) $teks));
        $teks = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks);
        return trim(preg_replace('/\s+/', ' ', $teks));
    }

    /** Soal kuis dianggap sama jika cerita DAN pertanyaannya sama. */
    private function kunciKuis(?string $konteks, ?string $pertanyaan): string
    {
        return $this->normalisasi($konteks) . '|' . $this->normalisasi($pertanyaan);
    }

    /**
     * Ambil soal kuis yang sudah ada pada satu bab.
     * Hasil: ['kunci' => [kunci => true], 'daftar' => [teks pertanyaan, ...]]
     */
    private function soalKuisYangAda(string $materiId, ?string $kecualiId = null): array
    {
        $kunci = [];
        $daftar = [];

        $docs = $this->firestore->collection('quiz')->where('materi_id', '=', $materiId)->documents();
        foreach ($docs as $doc) {
            if (!$doc->exists() || $doc->id() === $kecualiId) {
                continue;
            }
            $d = $doc->data();
            $kunci[$this->kunciKuis($d['konteks_bacaan'] ?? '', $d['pertanyaan'] ?? '')] = true;
            if (!empty($d['pertanyaan'])) {
                $daftar[] = $d['pertanyaan'];
            }
        }

        return ['kunci' => $kunci, 'daftar' => $daftar];
    }

    /** Ambil teks target pelafalan yang sudah ada pada satu bab. */
    private function pelafalanYangAda(string $materiId, ?string $kecualiId = null): array
    {
        $kunci = [];
        $daftar = [];

        $docs = $this->firestore->collection('kuis_suara')->where('materi_id', '=', $materiId)->documents();
        foreach ($docs as $doc) {
            if (!$doc->exists() || $doc->id() === $kecualiId) {
                continue;
            }
            $d = $doc->data();
            $teks = $d['teks_target'] ?? '';
            $kunci[$this->normalisasi($teks)] = true;
            if ($teks !== '') {
                $daftar[] = $teks;
            }
        }

        return ['kunci' => $kunci, 'daftar' => $daftar];
    }

    // ==================== HELPER KALIMAT PELAFALAN ====================

    /** Hitung jumlah kata dalam sebuah kalimat. */
    private function hitungKata(?string $teks): int
    {
        $teks = trim((string) $teks);
        if ($teks === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $teks, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Batas jumlah kata per kalimat menurut tingkat kesulitan: [minimum, target maksimum].
     * Minimum dipakai untuk membuang hasil AI yang ternyata hanya kata tunggal / frasa pendek.
     */
    private function batasKataSuara(string $tingkat): array
    {
        switch ($tingkat) {
            case 'Sulit':
                return [14, 22];
            case 'Sedang':
                return [9, 15];
            case 'Mudah':
            default:
                return [6, 11];
        }
    }

    /** Petunjuk panjang & kerumitan kalimat untuk prompt AI, sesuai tingkat kesulitan. */
    private function panduanTingkatSuara(string $tingkat): string
    {
        switch ($tingkat) {
            case 'Sulit':
                return "TINGKAT SULIT: SATU kalimat saja (jangan dua kalimat atau lebih), sekitar 14 sampai 22 kata. "
                    . "Gunakan kalimat kompleks dengan anak kalimat (which, that, although, if, because, while), "
                    . "variasi tenses (past perfect, future, conditional, passive voice), kosakata teknis vokasi, "
                    . "dan kata yang sulit diucapkan (bunyi th, r/l, v/w, kata dengan banyak suku kata). "
                    . "Tetap harus bisa dibaca lisan dalam satu tarikan napas yang wajar, jangan sampai seperti paragraf.";
            case 'Sedang':
                return "TINGKAT SEDANG: SATU kalimat, sekitar 9 sampai 15 kata. "
                    . "Gunakan kalimat majemuk (and, but, because, when, so), variasi tenses (past, future, present continuous), "
                    . "dan kosakata yang berkaitan dengan dunia kerja/vokasi. Bunyi katanya cukup bervariasi tetapi tidak terlalu ekstrem.";
            case 'Mudah':
            default:
                return "TINGKAT MUDAH: kalimat LENGKAP (subjek + predikat, bukan hanya satu-dua kata atau frasa), sekitar 6 sampai 11 kata, satu klausa, "
                    . "umumnya present simple, kosakata sehari-hari yang umum dan mudah dilafalkan.";
        }
    }

    // ==================== INDEX ====================

    public function index()
    {
        $materiDocs = $this->firestore->collection('materi_vokasi')->documents();
        $materiMap = [];
        $activeChapters = [];
        $petaKelas = []; // bab (ID / judul) -> tingkat kelas materinya

        foreach ($materiDocs as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                $id = $doc->id();
                $judul = $data['judul'] ?? 'Tanpa Judul';
                $deskripsi = $data['deskripsi'] ?? '';

                $materiMap[$id] = ['judul' => $judul, 'deskripsi' => $deskripsi];
                $materiMap[strtolower(trim($judul))] = ['judul' => $judul, 'deskripsi' => $deskripsi];

                $activeChapters[$judul] = [
                    'judul' => $judul,
                    'deskripsi' => $deskripsi,
                ];

                $kelasMateri = $this->normalisasiKelas($data['tingkat_kelas'] ?? 'X');
                $petaKelas[$id] = $kelasMateri;
                $petaKelas[mb_strtolower(trim($judul))] = $kelasMateri;
            }
        }

        $resolveBab = function ($data) use ($materiMap) {
            $possibleKeys = ['materi_id', 'materi', 'id_materi', 'bab', 'materi_vokasi_id'];
            foreach ($possibleKeys as $key) {
                if (!empty($data[$key])) {
                    $val = trim($data[$key]);
                    if (isset($materiMap[$val])) {
                        return $materiMap[$val]['judul'];
                    }
                    if (isset($materiMap[strtolower($val)])) {
                        return $materiMap[strtolower($val)]['judul'];
                    }
                    return $val;
                }
            }
            return null;
        };

        // Soal yang kelasnya tidak sama dengan kelas materinya (mis. soal lama yang
        // dulu otomatis tersimpan sebagai Kelas X). Dikoreksi di bawah.
        $perluDisinkron = [];

        $kuisList = [];
        foreach ($this->firestore->collection('quiz')->documents() as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                $resolvedBab = $resolveBab($data);

                if ($resolvedBab && isset($activeChapters[$resolvedBab])) {
                    $kelasMateri = $this->kelasUntukBab($resolvedBab, $petaKelas);
                    if ($kelasMateri !== null) {
                        if (($data['tingkatan_kelas'] ?? null) !== $kelasMateri) {
                            $perluDisinkron[] = ['koleksi' => 'quiz', 'id' => $doc->id(), 'kelas' => $kelasMateri];
                        }
                        $data['tingkatan_kelas'] = $kelasMateri;
                    }

                    $data['id'] = $doc->id();
                    $data['bab'] = $resolvedBab;
                    $data['tipe'] = 'kuis';
                    $kuisList[] = $data;
                }
            }
        }

        $kuisSuaraList = [];
        foreach ($this->firestore->collection('kuis_suara')->documents() as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                $resolvedBab = $resolveBab($data);

                if ($resolvedBab && isset($activeChapters[$resolvedBab])) {
                    $kelasMateri = $this->kelasUntukBab($resolvedBab, $petaKelas);
                    if ($kelasMateri !== null) {
                        if (($data['tingkatan_kelas'] ?? null) !== $kelasMateri) {
                            $perluDisinkron[] = ['koleksi' => 'kuis_suara', 'id' => $doc->id(), 'kelas' => $kelasMateri];
                        }
                        $data['tingkatan_kelas'] = $kelasMateri;
                    }

                    $data['id'] = $doc->id();
                    $data['bab'] = $resolvedBab;
                    $data['tipe'] = 'suara';
                    $kuisSuaraList[] = $data;
                }
            }
        }

        // Rapikan data lama di Firestore supaya kelasnya benar secara permanen.
        $this->sinkronkanKelasKeFirestore($perluDisinkron);

        return view('guru.kuis.index', compact('kuisList', 'kuisSuaraList', 'activeChapters', 'materiMap'));
    }

    // ==================== KUIS BIASA ====================

    public function create(Request $request)
    {
        $selectedMateri = $request->query('materi_id', '');
        return view('guru.kuis.create', compact('selectedMateri'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'materi_id' => 'required|string',
            'quizzes' => 'required|array|min:1',
            'quizzes.*.konteks_bacaan' => 'nullable|string',
            'quizzes.*.pertanyaan' => 'required|string',
            'quizzes.*.pilihan_a' => 'required|string',
            'quizzes.*.pilihan_b' => 'required|string',
            'quizzes.*.pilihan_c' => 'required|string',
            'quizzes.*.jawaban_benar' => 'required|integer|in:0,1,2',
            'quizzes.*.tingkatan_kesulitan' => 'required|in:Mudah,Sedang,Sulit',
        ]);

        // Kelas soal mengikuti kelas materi (bab) yang dipilih.
        $kelasBab = $this->kelasUntukBab($request->materi_id) ?? 'X';

        $ada = $this->soalKuisYangAda($request->materi_id)['kunci'];
        $tersimpan = 0;
        $dilewati = 0;

        foreach ($request->quizzes as $quiz) {
            $kunci = $this->kunciKuis($quiz['konteks_bacaan'] ?? '', $quiz['pertanyaan']);

            // Lewati kalau sudah ada di bab ini, atau kembar dengan soal lain di form yang sama.
            if (isset($ada[$kunci])) {
                $dilewati++;
                continue;
            }
            $ada[$kunci] = true;

            $pilihJawaban = [
                0 => $quiz['pilihan_a'],
                1 => $quiz['pilihan_b'],
                2 => $quiz['pilihan_c'],
            ];

            $this->firestore->collection('quiz')->add([
                'materi_id' => $request->materi_id,
                'konteks_bacaan' => $quiz['konteks_bacaan'] ?? '',
                'pertanyaan' => $quiz['pertanyaan'],
                'pilih_jawaban' => $pilihJawaban,
                'jawaban_benar' => (int) $quiz['jawaban_benar'],
                'tingkatan_kelas' => $kelasBab,
                'tingkatan_kesulitan' => $quiz['tingkatan_kesulitan'],
                'dibuat_pada' => now()->toIso8601String(),
            ]);
            $tersimpan++;
        }

        if ($tersimpan === 0) {
            return redirect()->route('guru.kuis')
                ->with('error', "Tidak ada soal yang disimpan: {$dilewati} soal sudah ada di bab ini.");
        }

        $pesan = "{$tersimpan} kuis berhasil ditambahkan!";
        if ($dilewati > 0) {
            $pesan .= " {$dilewati} soal dilewati karena sudah ada di bab ini.";
        }

        return redirect()->route('guru.kuis')->with('success', $pesan);
    }

    public function edit($id)
    {
        $docRef = $this->firestore->collection('quiz')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.kuis')->with('error', 'Kuis tidak ditemukan!');
        }

        $kuis = $snapshot->data();
        $kuis['id'] = $snapshot->id();
        $selectedMateri = $kuis['materi_id'] ?? '';

        return view('guru.kuis.edit', compact('kuis', 'selectedMateri'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'materi_id' => 'required|string',
            'konteks_bacaan' => 'nullable|string',
            'pertanyaan' => 'required|string',
            'pilihan_a' => 'required|string',
            'pilihan_b' => 'required|string',
            'pilihan_c' => 'required|string',
            'jawaban_benar' => 'required|integer|in:0,1,2',
            'tingkatan_kesulitan' => 'required|in:Mudah,Sedang,Sulit',
        ]);

        // Cegah soal kembar dengan soal LAIN di bab yang sama (soal ini sendiri dikecualikan).
        $kunciBaru = $this->kunciKuis($request->input('konteks_bacaan'), $request->pertanyaan);
        $ada = $this->soalKuisYangAda($request->materi_id, $id)['kunci'];
        if (isset($ada[$kunciBaru])) {
            return back()->withInput()->withErrors([
                'pertanyaan' => 'Soal dengan cerita dan pertanyaan yang sama sudah ada di bab ini.',
            ]);
        }

        $pilihJawaban = [
            0 => $request->pilihan_a,
            1 => $request->pilihan_b,
            2 => $request->pilihan_c,
        ];

        $docRef = $this->firestore->collection('quiz')->document($id);
        if ($docRef->snapshot()->exists()) {
            $dataUpdate = [
                'materi_id' => $request->materi_id,
                'pertanyaan' => $request->pertanyaan,
                'pilih_jawaban' => $pilihJawaban,
                'jawaban_benar' => (int) $request->jawaban_benar,
                'tingkatan_kesulitan' => $request->tingkatan_kesulitan,
                'diperbarui_pada' => now()->toIso8601String(),
            ];

            // Kelas soal selalu mengikuti kelas bab yang dipilih. Kalau bab tidak
            // ditemukan, nilai lama dibiarkan apa adanya.
            $kelasBab = $this->kelasUntukBab($request->materi_id);
            if ($kelasBab !== null) {
                $dataUpdate['tingkatan_kelas'] = $kelasBab;
            }

            // Cerita hanya ditimpa kalau form memang mengirim field-nya.
            if ($request->has('konteks_bacaan')) {
                $dataUpdate['konteks_bacaan'] = $request->input('konteks_bacaan') ?? '';
            }

            $docRef->set($dataUpdate, ['merge' => true]);
        }

        return redirect()->route('guru.kuis')->with('success', 'Kuis biasa berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $docRef = $this->firestore->collection('quiz')->document($id);
        if ($docRef->snapshot()->exists()) {
            $docRef->delete();
        }
        return redirect()->route('guru.kuis')->with('success', 'Kuis berhasil dihapus!');
    }

    // ==================== TES PELAFALAN (SUARA) ====================

    public function createSuara(Request $request)
    {
        $selectedMateri = $request->query('materi_id', '');
        return view('guru.kuis.create_suara', compact('selectedMateri'));
    }

    public function storeSuara(Request $request)
    {
        $request->validate([
            'materi_id' => 'required|string',
            'suara' => 'required|array|min:1',
            'suara.*.teks_target' => 'required|string',
            'suara.*.panduan_arti' => 'required|string',
            'suara.*.tingkatan_kesulitan' => 'required|in:Mudah,Sedang,Sulit',
        ]);

        // Kelas latihan mengikuti kelas materi (bab) yang dipilih.
        $kelasBab = $this->kelasUntukBab($request->materi_id) ?? 'X';

        $ada = $this->pelafalanYangAda($request->materi_id)['kunci'];
        $tersimpan = 0;
        $dilewati = 0;

        foreach ($request->suara as $item) {
            $kunci = $this->normalisasi($item['teks_target']);

            if (isset($ada[$kunci])) {
                $dilewati++;
                continue;
            }
            $ada[$kunci] = true;

            $judulLatihan = 'Pelafalan ' . Str::limit($item['teks_target'], 25, '');

            $this->firestore->collection('kuis_suara')->add([
                'judul_latihan' => $judulLatihan,
                'materi_id' => $request->materi_id,
                'teks_target' => $item['teks_target'],
                'panduan_arti' => $item['panduan_arti'],
                'tingkatan_kelas' => $kelasBab,
                'tingkatan_kesulitan' => $item['tingkatan_kesulitan'],
                'dibuat_pada' => now()->toIso8601String(),
            ]);
            $tersimpan++;
        }

        if ($tersimpan === 0) {
            return redirect()->route('guru.kuis')
                ->with('error', "Tidak ada latihan yang disimpan: {$dilewati} teks target sudah ada di bab ini.");
        }

        $pesan = "{$tersimpan} tes pelafalan berhasil ditambahkan!";
        if ($dilewati > 0) {
            $pesan .= " {$dilewati} dilewati karena sudah ada di bab ini.";
        }

        return redirect()->route('guru.kuis')->with('success', $pesan);
    }

    public function editSuara($id)
    {
        $docRef = $this->firestore->collection('kuis_suara')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.kuis')->with('error', 'Tes pelafalan tidak ditemukan!');
        }

        $kuis = $snapshot->data();
        $kuis['id'] = $snapshot->id();
        $selectedMateri = $kuis['materi_id'] ?? '';

        return view('guru.kuis.edit_suara', compact('kuis', 'selectedMateri'));
    }

    public function updateSuara(Request $request, $id)
    {
        $request->validate([
            'materi_id' => 'required|string',
            'teks_target' => 'required|string',
            'panduan_arti' => 'required|string',
            'tingkatan_kesulitan' => 'required|in:Mudah,Sedang,Sulit',
        ]);

        $ada = $this->pelafalanYangAda($request->materi_id, $id)['kunci'];
        if (isset($ada[$this->normalisasi($request->teks_target)])) {
            return back()->withInput()->withErrors([
                'teks_target' => 'Teks target yang sama sudah ada di bab ini.',
            ]);
        }

        $judulLatihan = 'Pelafalan ' . Str::limit($request->teks_target, 25, '');

        $docRef = $this->firestore->collection('kuis_suara')->document($id);
        if ($docRef->snapshot()->exists()) {
            $dataUpdate = [
                'judul_latihan' => $judulLatihan,
                'materi_id' => $request->materi_id,
                'teks_target' => $request->teks_target,
                'panduan_arti' => $request->panduan_arti,
                'tingkatan_kesulitan' => $request->tingkatan_kesulitan,
                'diperbarui_pada' => now()->toIso8601String(),
            ];

            // Kelas latihan selalu mengikuti kelas bab yang dipilih. Kalau bab tidak
            // ditemukan, nilai lama dibiarkan apa adanya.
            $kelasBab = $this->kelasUntukBab($request->materi_id);
            if ($kelasBab !== null) {
                $dataUpdate['tingkatan_kelas'] = $kelasBab;
            }

            $docRef->set($dataUpdate, ['merge' => true]);
        }

        return redirect()->route('guru.kuis')->with('success', 'Tes pelafalan suara berhasil diperbarui!');
    }

    public function destroySuara($id)
    {
        $docRef = $this->firestore->collection('kuis_suara')->document($id);
        if ($docRef->snapshot()->exists()) {
            $docRef->delete();
        }
        return redirect()->route('guru.kuis')->with('success', 'Kuis suara berhasil dihapus!');
    }

    // ==================== GENERATE AI (PREVIEW KE FORM & SIMPAN LANGSUNG) ====================

    private const MAKS_TEKS_SUMBER = 20000;

    private function aturanGenerate(): array
    {
        return [
            'bab' => 'required|string',
            'tipe' => 'required|in:kuis,suara',
            'jumlah' => 'required|integer|min:1|max:20',
            'tingkatan_kesulitan' => 'required|in:Mudah,Sedang,Sulit',
            'metode' => 'nullable|in:ai,pdf',
            'teks_sumber' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:5120',
        ];
    }

    /** Ambil teks dari file DOCX (file zip berisi word/document.xml). */
    private function teksDariDocx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi zip PHP belum aktif.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File DOCX tidak bisa dibuka.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('Isi dokumen DOCX tidak ditemukan.');
        }

        $xml = str_replace(['</w:p>', '<w:br/>'], "\n", $xml);
        $xml = str_replace('<w:tab/>', "\t", $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Baca teks dari file unggahan.
     * Mengembalikan [teks, pesanError]. Kalau pesanError terisi, file tidak bisa dipakai.
     */
    private function bacaFileSumber(\Illuminate\Http\UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        try {
            if ($extension === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                $teks = $parser->parseFile($path)->getText();
            } elseif ($extension === 'docx') {
                $teks = $this->teksDariDocx($path);
            } elseif ($extension === 'txt') {
                $teks = (string) file_get_contents($path);
            } else {
                return ['', 'File .doc (Word lama) belum bisa dibaca. Simpan ulang sebagai .docx atau PDF, lalu unggah lagi.'];
            }
        } catch (\Throwable $e) {
            Log::error('Gagal membaca file sumber AI', [
                'file' => $file->getClientOriginalName(),
                'pesan' => $e->getMessage(),
            ]);
            return ['', 'Isi file tidak bisa dibaca. Coba simpan ulang file-nya sebagai PDF/DOCX/TXT yang baru.'];
        }

        $teks = $this->rapikanTeks($teks);

        if ($teks === '') {
            return ['', 'Tidak ada teks yang terbaca dari file ini. Kalau file berupa PDF hasil scan atau foto, teksnya tidak bisa dibaca. Gunakan PDF yang teksnya bisa diseleksi, DOCX, atau TXT.'];
        }

        return [$teks, null];
    }

    private function rapikanTeks(string $teks): string
    {
        if (!mb_check_encoding($teks, 'UTF-8')) {
            $teks = mb_convert_encoding($teks, 'UTF-8', 'Windows-1252');
        }

        $teks = preg_replace('/[ \t]+/u', ' ', $teks) ?? '';
        $teks = preg_replace('/\n{3,}/', "\n\n", $teks) ?? '';

        return trim($teks);
    }

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
                . "Fitur generate akan bisa dipakai lagi sekitar {$label} (kira-kira {$sisa} lagi, kuota harian direset otomatis). "
                . "Sementara itu, soal tetap bisa diisi manual.";
        } else {
            $pesan = "Batas pemakaian AI (Gemini) sedang tercapai. Ini bukan error aplikasi. "
                . "Coba lagi sekitar {$sisa} lagi ({$label}). Sementara itu, soal tetap bisa diisi manual.";
        }

        return [
            'pesan' => $pesan,
            'iso' => $kapan->toIso8601String(),
            'label' => $label,
            'harian' => $harian,
        ];
    }

    /**
     * Susun hasil gagal yang jelas penyebabnya, sesuai status panggilan Gemini terakhir.
     * 'jenis': limit | koneksi | timeout | sibuk | kunci | model | lainnya
     */
    private function hasilGagalAI(): array
    {
        $status = $this->lastGeminiStatus;

        if ($status === 429) {
            $info = $this->infoLimitAI();
            return [
                'ok' => false,
                'status' => 429,
                'jenis' => 'limit',
                'message' => $info['pesan'],
                'bisa_lagi_pada' => $info['iso'],
                'bisa_lagi_label' => $info['label'],
            ];
        }

        if ($this->lastGeminiKoneksi === 'timeout') {
            return [
                'ok' => false,
                'status' => 504,
                'jenis' => 'timeout',
                'message' => 'Layanan AI terlalu lama merespons, jadi permintaan dihentikan. Ini bukan masalah kuota. Coba lagi sebentar lagi, atau kurangi jumlah soal / ukuran dokumennya.',
            ];
        }

        if ($this->lastGeminiKoneksi === 'koneksi') {
            return [
                'ok' => false,
                'status' => 503,
                'jenis' => 'koneksi',
                'message' => 'Server aplikasi tidak bisa terhubung ke layanan AI (Gemini). Periksa koneksi internet di komputer/server yang menjalankan aplikasi ini, lalu coba lagi. Ini bukan masalah kuota.',
            ];
        }

        if ($status === 503) {
            return [
                'ok' => false,
                'status' => 503,
                'jenis' => 'sibuk',
                'message' => 'Server AI (Gemini) sedang sibuk. Tunggu sebentar lalu coba lagi.',
            ];
        }

        if (in_array($status, [401, 403], true)) {
            return [
                'ok' => false,
                'status' => 502,
                'jenis' => 'kunci',
                'message' => 'API key Gemini ditolak. Hubungi admin untuk memeriksa GEMINI_API_KEY_KUIS di file .env.',
            ];
        }

        if ($status === 404) {
            return [
                'ok' => false,
                'status' => 502,
                'jenis' => 'model',
                'message' => 'Model AI yang dipakai sudah tidak tersedia. Hubungi admin untuk memeriksa GEMINI_MODEL di file .env.',
            ];
        }

        return [
            'ok' => false,
            'status' => 500,
            'jenis' => 'lainnya',
            'message' => 'Gagal memproses soal dari AI/Dokumen. Coba lagi, dan kalau tetap gagal hubungi admin.',
        ];
    }

    /** Respons JSON seragam untuk semua kegagalan generate. */
    private function jsonGagal(array $hasil)
    {
        return response()->json([
            'success' => false,
            'jenis' => $hasil['jenis'] ?? 'lainnya',
            'message' => $hasil['message'],
            'bisa_lagi_pada' => $hasil['bisa_lagi_pada'] ?? null,
            'bisa_lagi_label' => $hasil['bisa_lagi_label'] ?? null,
        ], $hasil['status']);
    }

    /**
     * Panggil Gemini dengan model utama (GEMINI_MODEL). Kalau gagal karena limit (429),
     * server sibuk (503), atau terlalu lama merespons (timeout), otomatis coba sekali lagi
     * dengan model cadangan (GEMINI_FALLBACK_MODEL). Tiap model punya jatah limit sendiri,
     * dan model Flash-Lite umumnya lebih cepat.
     * Mengembalikan teks JSON mentah dari AI, atau null kalau gagal total.
     */
    private function mintaJsonDariGemini(string $apiKey, string $prompt, string $label, string $levelThinking = 'low'): ?string
    {
        $modelUtama = config('services.gemini.model', 'gemini-flash-latest');
        $modelCadangan = config('services.gemini.fallback_model');

        $rawText = $this->panggilModelGemini($apiKey, $modelUtama, $prompt, $label, 90, $levelThinking);

        $statusAsli = $this->lastGeminiStatus;
        $retryAsli = $this->lastGeminiRetryAfter;
        $koneksiAsli = $this->lastGeminiKoneksi;

        $perluCadangan = $rawText === null
            && $modelCadangan
            && $modelCadangan !== $modelUtama
            && ($statusAsli === 429 || $statusAsli === 503 || $koneksiAsli === 'timeout');

        if ($perluCadangan) {
            $alasan = $statusAsli === 429 ? 'kena limit' : ($koneksiAsli === 'timeout' ? 'timeout' : 'sibuk (503)');
            Log::info("Model {$modelUtama} {$alasan}, coba model cadangan {$modelCadangan} ({$label}).");

            $this->lastGeminiStatus = null;
            $this->lastGeminiRetryAfter = null;
            $this->lastGeminiKoneksi = null;

            $rawText = $this->panggilModelGemini($apiKey, $modelCadangan, $prompt, $label, 60, $levelThinking);

            if ($rawText === null) {
                // Cadangan gagal karena konfigurasi (mis. 404 modelnya sudah dimatikan), atau penyebab asli
                // adalah limit dan cadangan gagal karena hal lain: tetap laporkan penyebab asli ke guru,
                // bukan pesan "gagal memproses" yang membingungkan.
                $gagalKonfigurasi = in_array($this->lastGeminiStatus, [400, 401, 403, 404], true);
                $limitAsliLainnya = $statusAsli === 429 && $this->lastGeminiStatus !== 429;

                if ($gagalKonfigurasi || $limitAsliLainnya) {
                    Log::warning("Model cadangan {$modelCadangan} gagal (status {$this->lastGeminiStatus}). Periksa GEMINI_FALLBACK_MODEL di .env.");
                    $this->lastGeminiStatus = $statusAsli;
                    $this->lastGeminiRetryAfter = $retryAsli;
                    $this->lastGeminiKoneksi = $koneksiAsli;
                }
            }
        }

        return $rawText;
    }

    /**
     * Satu kali panggilan nyata ke Gemini, untuk satu model tertentu.
     * Model Gemini 3.x secara bawaan "berpikir" cukup lama sebelum menjawab, dan jawabannya baru
     * dikirim setelah selesai -- itu sebabnya request bisa timeout dengan 0 byte diterima.
     * Untuk membuat soal, berpikir ringan sudah cukup dan jauh lebih cepat: "minimal" untuk kalimat
     * pelafalan, "low" untuk soal pilihan ganda (supaya kunci jawabannya tetap teliti).
     */
    private function panggilModelGemini(
        string $apiKey,
        string $model,
        string $prompt,
        string $label,
        int $timeout = 90,
        ?string $levelThinking = 'low'
    ): ?string {
        $konfigurasi = ['responseMimeType' => 'application/json'];
        if ($levelThinking !== null) {
            $konfigurasi['thinkingConfig'] = ['thinkingLevel' => $levelThinking];
        }

        try {
            $response = Http::connectTimeout(15)
                ->timeout($timeout)
                ->retry(2, 3000, function ($exception, $request) {
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
                        'generationConfig' => $konfigurasi,
                    ]
                );
        } catch (\Throwable $e) {
            $status = null;
            $body = null;
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $status = $e->response->status();
                $body = $e->response->json();
            }

            // Model menolak level thinking ini? Turun satu tingkat: minimal -> low -> tanpa pengaturan.
            if ($status === 400 && $levelThinking !== null && stripos((string) json_encode($body), 'thinking') !== false) {
                $berikutnya = $levelThinking === 'minimal' ? 'low' : null;
                Log::warning("Model {$model} menolak thinkingLevel '{$levelThinking}', diulang dengan " . ($berikutnya ?? 'tanpa pengaturan thinking') . " ({$label}).");
                return $this->panggilModelGemini($apiKey, $model, $prompt, $label, $timeout, $berikutnya);
            }

            $this->lastGeminiStatus = $status;
            $this->lastGeminiRetryAfter = $this->ambilRetryDelay($body);
            $this->lastGeminiKoneksi = null;
            if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                $this->lastGeminiKoneksi = stripos($e->getMessage(), 'timed out') !== false ? 'timeout' : 'koneksi';
            }

            Log::error("Gemini API exception ({$label}, model {$model})", [
                'message' => $e->getMessage(),
                'status' => $status,
            ]);
            return null;
        }

        if (!$response->successful()) {
            Log::error("Gemini API gagal ({$label}, model {$model})", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->lastGeminiStatus = $response->status();
            $this->lastGeminiRetryAfter = $this->ambilRetryDelay($response->json());
            return null;
        }

        $rawText = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!is_string($rawText) || trim($rawText) === '') {
            Log::error("Respons Gemini kosong ({$label}, model {$model})", ['body' => $response->body()]);
            return null;
        }

        return $rawText;
    }

    /**
     * Inti generate AI, dipakai previewAI (hasil ke form) dan generateAI (simpan langsung).
     * Hasil: ['ok' => bool, ...]. Kalau ok = false, ada 'message' dan 'status'.
     */
    private function siapkanItemAI(Request $request): array
    {
        // Dua percobaan model (utama lalu cadangan) bisa memakan waktu lebih dari batas bawaan PHP.
        @set_time_limit(240);

        $bab = $request->bab;
        $tipe = $request->tipe;
        $jumlah = (int) $request->jumlah;
        $tingkat = $request->tingkatan_kesulitan;
        $metode = $request->input('metode', 'ai') ?: 'ai';

        $teksSumber = trim((string) $request->input('teks_sumber', ''));

        if ($request->hasFile('file')) {
            [$teksFile, $galat] = $this->bacaFileSumber($request->file('file'));
            if ($galat) {
                return ['ok' => false, 'status' => 422, 'message' => $galat];
            }
            $teksSumber = trim($teksSumber . "\n" . $teksFile);
        }

        $teksSumber = $this->rapikanTeks($teksSumber);

        $terpotong = mb_strlen($teksSumber) > self::MAKS_TEKS_SUMBER;
        if ($terpotong) {
            $teksSumber = mb_substr($teksSumber, 0, self::MAKS_TEKS_SUMBER);
        }

        // Mode salin soal: wajib ada isi yang disalin. Jangan diam-diam memakai materi bab.
        if ($metode === 'pdf' && $teksSumber === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Mode "salin soal" butuh file atau teks yang berisi soalnya. Unggah file atau tempel teksnya dulu.',
            ];
        }

        // Mode buat soal baru tanpa bahan: pakai deskripsi materi bab.
        if ($teksSumber === '') {
            foreach ($this->firestore->collection('materi_vokasi')->documents() as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    if (($data['judul'] ?? '') === $bab) {
                        $teksSumber = trim($data['deskripsi'] ?? '');
                        break;
                    }
                }
            }
        }

        // Soal yang sudah ada di bab ini: dikirim ke AI supaya dihindari,
        // dan dipakai lagi di bawah untuk membuang hasil yang tetap kembar.
        $sudahAda = $tipe === 'kuis'
            ? $this->soalKuisYangAda($bab)
            : $this->pelafalanYangAda($bab);

        // Reset status Gemini dari percobaan sebelumnya, supaya tidak membaca sisa yang lama.
        $this->lastGeminiStatus = null;
        $this->lastGeminiRetryAfter = null;
        $this->lastGeminiKoneksi = null;

        $items = $this->generateQuizItemsWithAI(
            $bab,
            $teksSumber,
            $tipe,
            $jumlah,
            $tingkat,
            $metode,
            array_slice($sudahAda['daftar'], -50)
        );

        if (!$items) {
            return $this->hasilGagalAI();
        }

        // Tes pelafalan harus berupa KALIMAT. Buang hasil yang ternyata hanya kata tunggal
        // atau frasa terlalu pendek untuk tingkat kesulitan yang dipilih.
        $terlaluPendek = 0;
        if ($tipe === 'suara') {
            [$minKata] = $this->batasKataSuara($tingkat);
            // Mode salin dari dokumen dibuat lebih longgar, karena panjang kalimat mengikuti isi dokumen.
            $minKata = $metode === 'pdf' ? 3 : $minKata;

            $items = array_values(array_filter($items, function ($item) use ($minKata, &$terlaluPendek) {
                $ok = is_array($item) && $this->hitungKata($item['teks_target'] ?? '') >= $minKata;
                if (!$ok) {
                    $terlaluPendek++;
                }
                return $ok;
            }));

            if (empty($items)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'AI hanya menghasilkan kata atau frasa pendek, belum berupa kalimat yang sesuai tingkat ' . $tingkat . '. Coba generate lagi.',
                ];
            }
        }

        // Buang hasil yang sudah ada di bab ini atau kembar di dalam hasil AI itu sendiri.
        $kunciTerpakai = $sudahAda['kunci'];
        $unik = [];
        $dilewati = $terlaluPendek;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kunci = $tipe === 'kuis'
                ? $this->kunciKuis($item['konteks_bacaan'] ?? '', $item['pertanyaan'] ?? '')
                : $this->normalisasi($item['teks_target'] ?? '');

            if (isset($kunciTerpakai[$kunci])) {
                $dilewati++;
                continue;
            }

            $kunciTerpakai[$kunci] = true;
            $unik[] = $item;
        }

        if (empty($unik)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Semua hasil sudah ada di bab ini, jadi tidak ada yang ditambahkan. Coba lagi atau ganti materi/dokumennya.',
            ];
        }

        return [
            'ok' => true,
            'items' => $unik,
            'dilewati' => $dilewati,
            'terpotong' => $terpotong,
            'bab' => $bab,
            'tipe' => $tipe,
            'tingkat' => $tingkat,
        ];
    }

    /** Hasil AI dikirim ke form (belum disimpan). Dipakai halaman tambah/edit. */
    public function previewAI(Request $request)
    {
        $request->validate($this->aturanGenerate());

        $hasil = $this->siapkanItemAI($request);

        if (!$hasil['ok']) {
            return $this->jsonGagal($hasil);
        }

        return response()->json([
            'success' => true,
            'items' => $hasil['items'],
            'dilewati' => $hasil['dilewati'],
            'terpotong' => $hasil['terpotong'],
        ]);
    }

    /** Hasil AI langsung disimpan ke bank soal. Dipakai tombol "Buat dengan AI" di halaman index. */
    public function generateAI(Request $request)
    {
        $request->validate($this->aturanGenerate());

        $hasil = $this->siapkanItemAI($request);

        if (!$hasil['ok']) {
            return $this->jsonGagal($hasil);
        }

        // Kelas soal mengikuti kelas materi (bab) yang dipilih.
        $kelasBab = $this->kelasUntukBab($hasil['bab']) ?? 'X';

        $tersimpan = 0;
        $tidakValid = 0;

        foreach ($hasil['items'] as $item) {
            if ($hasil['tipe'] === 'kuis') {
                $pertanyaan = trim((string) ($item['pertanyaan'] ?? ''));
                $pilihan = array_map(
                    fn ($p) => trim((string) $p),
                    array_slice((array) ($item['pilihan'] ?? []), 0, 3)
                );
                $kunci = $item['jawaban_benar'] ?? null;

                $valid = $pertanyaan !== ''
                    && count($pilihan) === 3
                    && !in_array('', $pilihan, true)
                    && is_numeric($kunci)
                    && in_array((int) $kunci, [0, 1, 2], true);

                if (!$valid) {
                    $tidakValid++;
                    continue;
                }

                $this->firestore->collection('quiz')->add([
                    'materi_id' => $hasil['bab'],
                    'konteks_bacaan' => trim((string) ($item['konteks_bacaan'] ?? '')),
                    'pertanyaan' => $pertanyaan,
                    'pilih_jawaban' => [0 => $pilihan[0], 1 => $pilihan[1], 2 => $pilihan[2]],
                    'jawaban_benar' => (int) $kunci,
                    'tingkatan_kelas' => $kelasBab,
                    'tingkatan_kesulitan' => $hasil['tingkat'],
                    'dibuat_pada' => now()->toIso8601String(),
                ]);
            } else {
                $teks = trim((string) ($item['teks_target'] ?? ''));
                $arti = trim((string) ($item['panduan_arti'] ?? ''));

                if ($teks === '' || $arti === '') {
                    $tidakValid++;
                    continue;
                }

                $this->firestore->collection('kuis_suara')->add([
                    'judul_latihan' => 'Pelafalan ' . Str::limit($teks, 25, ''),
                    'materi_id' => $hasil['bab'],
                    'teks_target' => $teks,
                    'panduan_arti' => $arti,
                    'tingkatan_kelas' => $kelasBab,
                    'tingkatan_kesulitan' => $hasil['tingkat'],
                    'dibuat_pada' => now()->toIso8601String(),
                ]);
            }

            $tersimpan++;
        }

        if ($tersimpan === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Hasil AI tidak lengkap sehingga tidak ada yang disimpan. Coba buat lagi.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'jumlah_tersimpan' => $tersimpan,
            'dilewati' => $hasil['dilewati'] + $tidakValid,
            'terpotong' => $hasil['terpotong'],
        ]);
    }

    private function generateQuizItemsWithAI(
        string $bab,
        string $deskripsiBab,
        string $tipe,
        int $jumlah,
        string $tingkat,
        string $metode,
        array $sudahAda = []
    ): ?array {
        if ($metode === 'pdf') {
            if ($tipe === 'kuis') {
                $skema = <<<SKEMA
[
  {
    "konteks_bacaan": "Teks cerita, paragraf, atau dialog bahasa Inggris dari dokumen (kosongkan jika tidak ada)",
    "pertanyaan": "question text in English extracted from document",
    "pilihan": ["option A in English", "option B in English", "option C in English"],
    "jawaban_benar": 0
  }
]
SKEMA;
                $instruksi = "Salin paling banyak {$jumlah} soal pilihan ganda yang SUDAH ADA di teks dokumen di bawah ini. Salin pertanyaan dan pilihannya apa adanya, jangan membuat soal baru. Jika soal punya lebih dari 3 pilihan, ambil jawaban yang benar ditambah 2 pengecoh yang paling masuk akal, lalu susun jadi 3 opsi (A, B, C). Jika kunci jawaban tertera di dokumen, pakai itu. Jika tidak ada, pilih jawaban yang paling benar. Jika ada teks cerita/bacaan/dialog, masukkan ke `konteks_bacaan`. Lewati soal yang tidak lengkap. Pertanyaan HARUS dalam bahasa Inggris.";
            } else {
                $skema = <<<SKEMA
[
  {
    "teks_target": "A complete English sentence taken from the document",
    "panduan_arti": "terjemahan seluruh kalimat dalam bahasa Indonesia"
  }
]
SKEMA;
                $panduanTingkat = $this->panduanTingkatSuara($tingkat);
                $instruksi = "Salin paling banyak {$jumlah} KALIMAT LENGKAP bahasa Inggris yang SUDAH ADA di teks dokumen di bawah ini untuk dijadikan latihan pelafalan, beserta panduan arti (terjemahan seluruh kalimat) dalam bahasa Indonesia. "
                    . "Yang diambil harus KALIMAT utuh, BUKAN kata tunggal dan BUKAN daftar kosakata. "
                    . "Pilih kalimat yang panjang dan kerumitannya paling sesuai dengan tingkat kesulitan berikut. {$panduanTingkat} "
                    . "Jangan membuat kalimat baru di luar dokumen, dan jangan ambil kalimat yang sama dua kali.";
            }
        } else {
            if ($tipe === 'kuis') {
                $skema = <<<SKEMA
[
  {
    "konteks_bacaan": "Teks cerita, paragraf, atau dialog bahasa Inggris (kosongkan string jika tidak ada)",
    "pertanyaan": "question text in English",
    "pilihan": ["option A in English", "option B in English", "option C in English"],
    "jawaban_benar": 0
  }
]
SKEMA;
                $instruksi = "Buatkan {$jumlah} soal pilihan ganda (3 opsi: A, B, C) baru untuk kuis bahasa Inggris vokasi SMK berdasarkan topik materi. Jika relevan dengan cerita, sertakan `konteks_bacaan`. Pertanyaan HARUS dalam bahasa Inggris.";
            } else {
                $skema = <<<SKEMA
[
  {
    "teks_target": "A complete English sentence to read aloud",
    "panduan_arti": "terjemahan seluruh kalimat dalam bahasa Indonesia"
  }
]
SKEMA;
                $panduanTingkat = $this->panduanTingkatSuara($tingkat);
                $instruksi = "Buatkan {$jumlah} latihan tes pelafalan berupa KALIMAT LENGKAP bahasa Inggris (BUKAN kata tunggal, BUKAN frasa pendek, BUKAN daftar kosakata) yang relevan dengan topik materi, beserta panduan arti berupa terjemahan seluruh kalimat dalam bahasa Indonesia. "
                    . "Kalimat boleh panjang, yang terpenting panjang dan kerumitannya sesuai dengan tingkat kesulitan. {$panduanTingkat} "
                    . "Setiap kalimat harus natural, berbeda satu sama lain (topik dan struktur bervariasi), diawali huruf kapital, dan diakhiri tanda baca.";
            }
        }

        // Daftar yang sudah ada, supaya AI tidak membuat soal yang sama atau mirip.
        $blokSudahAda = '';
        if (!empty($sudahAda)) {
            $baris = array_map(fn ($t) => '- ' . Str::limit(preg_replace('/\s+/', ' ', (string) $t), 120), $sudahAda);
            $blokSudahAda = "\n\nYang SUDAH ADA di bab ini (JANGAN buat yang sama atau mirip dengan daftar ini, buat yang berbeda):\n"
                . implode("\n", $baris);
        }

        $prompt = <<<PROMPT
Kamu adalah asisten guru bahasa Inggris vokasi. {$instruksi}

Tingkat kesulitan: {$tingkat}.
Bab/topik: {$bab}.
Isi Dokumen / Teks Sumber:
{$deskripsiBab}{$blokSudahAda}

Balas HANYA dalam format JSON array PERSIS seperti skema ini, tanpa teks lain di luar JSON:
{$skema}
PROMPT;

        // Key khusus Bank Soal (Kuis), supaya tidak berebut kuota dengan Kamus/Materi.
        // Kalau GEMINI_API_KEY_KUIS belum diisi di .env, otomatis pakai GEMINI_API_KEY biasa.
        $apiKey = env('GEMINI_API_KEY_KUIS') ?: config('services.gemini.key');

        if (empty($apiKey)) {
            Log::error('GEMINI_API_KEY_KUIS / GEMINI_API_KEY kosong / tidak terbaca.');
            return null;
        }

        $rawText = $this->mintaJsonDariGemini($apiKey, $prompt, 'Quiz Preview', $tipe === 'suara' ? 'minimal' : 'low');
        if ($rawText === null) {
            return null;
        }

        $cleanText = trim($rawText);
        $cleanText = preg_replace('/^```json\s*/i', '', $cleanText);
        $cleanText = preg_replace('/^```\s*/i', '', $cleanText);
        $cleanText = preg_replace('/\s*```$/i', '', $cleanText);

        $parsed = json_decode($cleanText, true);

        if (!is_array($parsed) || empty($parsed)) {
            Log::error('Gagal parsing hasil Gemini (Quiz Preview) jadi JSON', ['raw_text' => $rawText]);
            return null;
        }

        // Kalau AI membalas satu objek saja (bukan array), bungkus jadi array.
        if (array_keys($parsed) !== range(0, count($parsed) - 1)) {
            $parsed = [$parsed];
        }

        return array_slice($parsed, 0, $jumlah);
    }
}