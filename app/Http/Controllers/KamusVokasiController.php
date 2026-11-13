<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KamusVokasiController extends Controller
{
    protected $firestore;
    protected $lastGeminiStatus = null;
    protected $lastGeminiRetryAfter = null; // detik, kalau Google kasih tahu kapan boleh coba lagi

    public function __construct()
    {
        $this->firestore = app('firebase.firestore')->database();
    }

    // ==================== HELPER ====================

    /**
     * Daftar bab materi yang aktif saat ini, langsung dari koleksi materi_vokasi.
     * Format: [idMateri => judul]. Bab baru otomatis muncul, bab yang dihapus otomatis hilang.
     */
    private function ambilBabAktif(): array
    {
        $bab = [];

        foreach ($this->firestore->collection('materi_vokasi')->documents() as $doc) {
            if ($doc->exists()) {
                $judul = trim($doc->data()['judul'] ?? '');
                if ($judul !== '') {
                    $bab[$doc->id()] = $judul;
                }
            }
        }

        return $bab;
    }

    /** Ubah [id => judul] jadi daftar untuk dropdown: [['id' => ..., 'judul' => ...], ...] */
    private function keDaftarPilihan(array $babAktif): array
    {
        $daftar = [];
        foreach ($babAktif as $id => $judul) {
            $daftar[] = ['id' => $id, 'judul' => $judul];
        }
        return $daftar;
    }

    /**
     * Cari bab yang dituju sebuah dokumen kamus.
     * Utamakan materi_id (tetap benar walau judul bab diganti), lalu cocokkan lewat judul
     * untuk data lama yang belum punya materi_id.
     * Mengembalikan [materiId, judul] atau null jika bab-nya sudah tidak ada.
     */
    private function cariBab(array $data, array $babAktif): ?array
    {
        $materiId = $data['materi_id'] ?? null;
        if ($materiId && isset($babAktif[$materiId])) {
            return [$materiId, $babAktif[$materiId]];
        }

        $judulLama = trim($data['bab'] ?? '');
        if ($judulLama !== '') {
            $id = array_search($judulLama, $babAktif, true);
            if ($id !== false) {
                return [$id, $judulLama];
            }
        }

        return null;
    }

    private function normalisasi(?string $teks): string
    {
        $teks = mb_strtolower(trim((string) $teks));
        return trim(preg_replace('/\s+/', ' ', $teks));
    }

    /**
     * Kosakata yang sudah ada pada satu bab.
     * Hasil: ['kunci' => [kata ternormalisasi => true], 'daftar' => [kata, ...]]
     */
    private function kataYangAda(string $materiId, array $babAktif, ?string $kecualiId = null): array
    {
        $kunci = [];
        $daftar = [];

        foreach ($this->firestore->collection('kamus_vokasi')->documents() as $doc) {
            if (!$doc->exists() || $doc->id() === $kecualiId) {
                continue;
            }

            $data = $doc->data();
            $bab = $this->cariBab($data, $babAktif);

            if ($bab && $bab[0] === $materiId) {
                $kata = $data['kata'] ?? '';
                $kunci[$this->normalisasi($kata)] = true;
                if ($kata !== '') {
                    $daftar[] = $kata;
                }
            }
        }

        return ['kunci' => $kunci, 'daftar' => $daftar];
    }

    private function kataSudahAda(string $materiId, string $kata, array $babAktif, ?string $kecualiId = null): bool
    {
        $ada = $this->kataYangAda($materiId, $babAktif, $kecualiId);
        return isset($ada['kunci'][$this->normalisasi($kata)]);
    }

    // ==================== CRUD ====================

    public function index()
    {
        $babAktif = $this->ambilBabAktif();
        $kamusList = [];
        $jumlahYatim = 0;

        foreach ($this->firestore->collection('kamus_vokasi')->documents() as $doc) {
            if (!$doc->exists()) {
                continue;
            }

            $data = $doc->data();
            $bab = $this->cariBab($data, $babAktif);

            // Bab-nya sudah dihapus dari materi: sembunyikan dari kamus.
            if (!$bab) {
                $jumlahYatim++;
                continue;
            }

            $data['id'] = $doc->id();
            $data['materi_id'] = $bab[0];
            $data['bab'] = $bab[1];
            $kamusList[] = $data;
        }

        usort($kamusList, fn ($a, $b) => strcmp(mb_strtolower($a['kata'] ?? ''), mb_strtolower($b['kata'] ?? '')));

        $activeChapters = $this->keDaftarPilihan($babAktif);

        return view('guru.kamus_vokasi.index', compact('kamusList', 'activeChapters', 'jumlahYatim'));
    }

    /**
     * Halaman tambah kosakata. Bab dipilih dulu di halaman index (lewat modal atau
     * tombol "Tambah ke bab ini"), lalu dikirim lewat ?materi_id=...
     * Kalau bab tidak ada / tidak valid, kembali ke index.
     */
    public function create(Request $request)
    {
        $babAktif = $this->ambilBabAktif();
        $selectedMateriId = (string) $request->query('materi_id', '');

        if ($selectedMateriId === '' || !isset($babAktif[$selectedMateriId])) {
            return redirect()->route('guru.kamus_vokasi.index')
                ->with('error', 'Pilih bab dulu lewat tombol "Tambah kosakata".');
        }

        $selectedBab = $babAktif[$selectedMateriId];

        return view('guru.kamus_vokasi.create', compact('selectedMateriId', 'selectedBab'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'materi_id' => 'required|string',
            'kamus' => 'required|array|min:1',
            'kamus.*.kata' => 'required|string|max:255',
            'kamus.*.arti_id' => 'required|string|max:255',
        ], [
            'kamus.required' => 'Tambahkan minimal satu kosakata.',
            'kamus.*.kata.required' => 'Kata target tidak boleh kosong.',
            'kamus.*.arti_id.required' => 'Arti kata tidak boleh kosong.',
        ]);

        $babAktif = $this->ambilBabAktif();

        if (!isset($babAktif[$request->materi_id])) {
            return redirect()->route('guru.kamus_vokasi.index')
                ->with('error', 'Bab materi tidak ditemukan atau sudah dihapus. Pilih bab yang tersedia.');
        }

        $ada = $this->kataYangAda($request->materi_id, $babAktif)['kunci'];
        $tersimpan = 0;
        $dilewati = 0;

        foreach ($request->kamus as $item) {
            $kata = trim($item['kata']);
            $kunci = $this->normalisasi($kata);

            // Lewati kalau sudah ada di bab ini, atau kembar dengan baris lain di form yang sama.
            if (isset($ada[$kunci])) {
                $dilewati++;
                continue;
            }
            $ada[$kunci] = true;

            $this->firestore->collection('kamus_vokasi')->add([
                'kata' => $kata,
                'arti_id' => trim($item['arti_id']),
                'bab' => $babAktif[$request->materi_id],
                'materi_id' => $request->materi_id,
                'dibuat_pada' => now()->toIso8601String(),
            ]);
            $tersimpan++;
        }

        if ($tersimpan === 0) {
            return back()->withInput()->withErrors([
                'kamus' => "Tidak ada kosakata yang disimpan: {$dilewati} kata sudah ada di bab ini.",
            ]);
        }

        $pesan = "{$tersimpan} kosakata berhasil ditambahkan ke kamus!";
        if ($dilewati > 0) {
            $pesan .= " {$dilewati} dilewati karena sudah ada di bab ini.";
        }

        return redirect()->route('guru.kamus_vokasi.index')->with('success', $pesan);
    }

    public function edit($id)
    {
        $docRef = $this->firestore->collection('kamus_vokasi')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.kamus_vokasi.index')->with('error', 'Data kamus tidak ditemukan!');
        }

        $kamus = $snapshot->data();
        $kamus['id'] = $snapshot->id();

        $babAktif = $this->ambilBabAktif();
        $bab = $this->cariBab($kamus, $babAktif);

        // Kalau bab-nya sudah dihapus, biarkan kosong supaya guru memilih bab baru.
        $selectedMateriId = $bab ? $bab[0] : '';
        $activeChapters = $this->keDaftarPilihan($babAktif);

        return view('guru.kamus_vokasi.edit', compact('kamus', 'activeChapters', 'selectedMateriId'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'kata' => 'required|string|max:255',
            'arti_id' => 'required|string|max:255',
            'materi_id' => 'required|string',
        ]);

        $babAktif = $this->ambilBabAktif();

        if (!isset($babAktif[$request->materi_id])) {
            return back()->withInput()->withErrors([
                'materi_id' => 'Bab materi tidak ditemukan atau sudah dihapus. Pilih bab yang tersedia.',
            ]);
        }

        if ($this->kataSudahAda($request->materi_id, $request->kata, $babAktif, $id)) {
            return back()->withInput()->withErrors([
                'kata' => 'Kata "' . trim($request->kata) . '" sudah ada di bab ini.',
            ]);
        }

        $docRef = $this->firestore->collection('kamus_vokasi')->document($id);
        if ($docRef->snapshot()->exists()) {
            $docRef->set([
                'kata' => trim($request->kata),
                'arti_id' => trim($request->arti_id),
                'bab' => $babAktif[$request->materi_id],
                'materi_id' => $request->materi_id,
                'diperbarui_pada' => now()->toIso8601String(),
            ], ['merge' => true]);
        }

        return redirect()->route('guru.kamus_vokasi.index')
            ->with('success', 'Data kamus vokasi berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $docRef = $this->firestore->collection('kamus_vokasi')->document($id);
        if ($docRef->snapshot()->exists()) {
            $docRef->delete();
        }

        return redirect()->route('guru.kamus_vokasi.index')
            ->with('success', 'Kosakata kamus vokasi berhasil dihapus!');
    }

    // ==================== SARAN KOSAKATA DARI AI (BERDASARKAN JUDUL BAB) ====================

    /**
     * Cari saran kata/frasa/kalimat bahasa Inggris untuk satu bab, lengkap dengan arti
     * Indonesia. TIDAK lagi mengambil dari isi materi atau soal kuis -- AI bebas membuat
     * kosakata apa saja yang relevan dengan JUDUL/topik bab itu sendiri (mis. bab "Job
     * Interview" -> kosakata umum seputar wawancara kerja). Hasilnya hanya saran: tidak ada
     * yang disimpan di sini. Dipakai oleh form create dan edit.
     */
    public function saran(Request $request)
    {
        $request->validate([
            'materi_id' => 'required|string',
            'jumlah' => 'nullable|integer|min:1|max:20',
            'kecuali_id' => 'nullable|string',
        ]);

        $jumlah = (int) $request->input('jumlah', 10);

        $babAktif = $this->ambilBabAktif();
        if (!isset($babAktif[$request->materi_id])) {
            return response()->json([
                'success' => false,
                'message' => 'Bab materi tidak ditemukan atau sudah dihapus.',
            ], 422);
        }

        $judul = $babAktif[$request->materi_id];
        $sudahAda = $this->kataYangAda($request->materi_id, $babAktif, $request->input('kecuali_id'));

        // Reset status Gemini dari percobaan sebelumnya, supaya tidak membaca sisa yang lama.
        $this->lastGeminiStatus = null;
        $this->lastGeminiRetryAfter = null;

        $items = $this->cariSaranDenganAI($judul, $jumlah, array_slice($sudahAda['daftar'], -100));

        if ($items === null) {
            if ($this->lastGeminiStatus === 429) {
                return response()->json(['success' => false, 'message' => $this->pesanLimitAI()], 429);
            }

            $pesan = $this->lastGeminiStatus === 503
                ? 'Server AI (Gemini) sedang sibuk. Tunggu sebentar lalu coba lagi.'
                : 'Gagal mengambil saran kosakata dari AI.';
            return response()->json(['success' => false, 'message' => $pesan], 500);
        }

        // Buang yang sudah ada di kamus bab ini atau kembar di dalam hasil AI.
        $terpakai = $sudahAda['kunci'];
        $unik = [];
        $dilewati = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kata = trim((string) ($item['kata'] ?? ''));
            $arti = trim((string) ($item['arti_id'] ?? ''));
            if ($kata === '' || $arti === '') {
                continue;
            }

            $kunci = $this->normalisasi($kata);
            if (isset($terpakai[$kunci])) {
                $dilewati++;
                continue;
            }
            $terpakai[$kunci] = true;

            $unik[] = [
                'kata' => Str::limit($kata, 255, ''),
                'arti_id' => Str::limit($arti, 255, ''),
            ];
        }

        if (empty($unik)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada saran baru. Kata yang ditemukan sudah ada di kamus bab ini.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'items' => array_slice($unik, 0, $jumlah),
            'dilewati' => $dilewati,
        ]);
    }

    /**
     * Panggil Gemini dengan model utama (GEMINI_MODEL). Kalau kena limit (429), otomatis
     * coba sekali lagi dengan model cadangan (GEMINI_FALLBACK_MODEL) -- karena tiap model
     * biasanya punya jatah limit gratis sendiri-sendiri yang terpisah, jadi kalau satu model
     * habis, model lain dengan key yang SAMA masih sering bisa dipakai.
     * Mengembalikan teks JSON mentah dari AI, atau null kalau gagal total.
     */
    private function mintaJsonDariGemini(string $apiKey, string $prompt, string $label): ?string
    {
        $modelUtama = config('services.gemini.model', 'gemini-flash-latest');
        $modelCadangan = config('services.gemini.fallback_model');

        $rawText = $this->panggilModelGemini($apiKey, $modelUtama, $prompt, $label);

        if ($rawText === null && $this->lastGeminiStatus === 429 && $modelCadangan && $modelCadangan !== $modelUtama) {
            Log::info("Model {$modelUtama} kena limit, coba model cadangan {$modelCadangan} ({$label}).");
            $this->lastGeminiStatus = null;
            $this->lastGeminiRetryAfter = null;
            $rawText = $this->panggilModelGemini($apiKey, $modelCadangan, $prompt, $label);
        }

        return $rawText;
    }

    /** Satu kali panggilan nyata ke Gemini, untuk satu model tertentu. */
    private function panggilModelGemini(string $apiKey, string $model, string $prompt, string $label): ?string
    {
        try {
            $response = Http::timeout(60)
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

    /**
     * Pesan ramah untuk guru saat kuota/limit pemakaian AI (Gemini) habis (HTTP 429),
     * sekaligus perkiraan kapan bisa dipakai lagi.
     */
    private function pesanLimitAI(): string
    {
        // Google kadang kasih tahu persis berapa lama harus menunggu (untuk limit per menit,
        // atau kadang untuk limit harian juga). Dipaksa ke zona waktu WIB secara eksplisit,
        // supaya tidak ikut zona waktu bawaan server (yang sering kali UTC, bukan WIB).
        if ($this->lastGeminiRetryAfter) {
            $detik = $this->lastGeminiRetryAfter;
            $kapan = now('Asia/Jakarta')->addSeconds($detik);

            if ($detik < 60) {
                $durasi = "sekitar {$detik} detik lagi";
            } elseif ($detik < 3600) {
                $durasi = 'sekitar ' . (int) ceil($detik / 60) . ' menit lagi';
            } else {
                $durasi = 'sekitar ' . (int) ceil($detik / 3600) . ' jam lagi';
            }

            return "Batas pemakaian AI (Gemini) untuk saat ini sudah tercapai. Coba lagi {$durasi}, sekitar pukul {$kapan->format('H:i')} WIB ({$kapan->format('d M Y')}).";
        }

        // Tidak ada info jeda spesifik dari Google -> biasanya ini kuota GRATIS HARIAN yang baru
        // di-reset tengah malam waktu Amerika (Pasifik). Dihitung dan dikonversi ke WIB supaya
        // perkiraannya jelas, bukan cuma "coba lagi nanti".
        $resetPasifik = now('America/Los_Angeles')->addDay()->startOfDay();
        $resetWib = $resetPasifik->clone()->setTimezone('Asia/Jakarta');

        return "Batas pemakaian gratis AI (Gemini) untuk hari ini sudah habis. Kuota ini biasanya reset otomatis sekitar pukul {$resetWib->format('H:i')} WIB ({$resetWib->format('d M Y')}). Coba lagi setelah waktu itu, atau tambah kuota di akun Gemini API Anda melalui https://aistudio.google.com/apikey.";
    }

    private function cariSaranDenganAI(string $judul, int $jumlah, array $sudahAda): ?array
    {
        $blokSudahAda = '';
        if (!empty($sudahAda)) {
            $blokSudahAda = "\n\nSudah ada di kamus bab ini (JANGAN diulang):\n"
                . implode("\n", array_map(fn ($k) => '- ' . Str::limit((string) $k, 80), $sudahAda));
        }

        $skema = <<<SKEMA
[
  {
    "kata": "kata, frasa, atau kalimat pendek bahasa Inggris",
    "arti_id": "arti dalam bahasa Indonesia"
  }
]
SKEMA;

        $prompt = <<<PROMPT
Kamu adalah asisten guru bahasa Inggris vokasi SMK yang sedang menyusun kamus kosakata untuk topik/bab berikut: "{$judul}".

Tugasmu: buatkan {$jumlah} kata, frasa, atau kalimat pendek bahasa Inggris yang relevan dan umum dipakai untuk topik "{$judul}" tersebut. Kamu BEBAS membuat kosakata sendiri berdasarkan pengetahuanmu tentang topik itu -- TIDAK perlu dan TIDAK dibatasi oleh materi pelajaran atau soal kuis yang sudah ada di sistem. Anggap saja kamu sedang membuat daftar kosakata/istilah penting dari nol untuk topik ini, seperti menyusun glosarium.

Contoh cara berpikir: kalau topiknya "Job Interview", kamu boleh membuat kosakata seperti "curriculum vitae", "strength and weakness", "I am a hard worker", "could you tell me about yourself?", dan sejenisnya -- semua yang lazim dipakai dalam konteks itu, bukan cuma yang kebetulan pernah ditulis di materi/kuis.

Utamakan istilah vokasi/teknis, frasa, idiom, atau contoh kalimat pendek yang benar-benar dipakai sehari-hari untuk topik ini, yang berguna buat murid SMK. Hindari kata yang sangat umum dan dasar (misalnya the, is, good, and) kecuali memang jadi bagian frasa penting.

Aturan:
- Semua kata/frasa/kalimat harus tetap relevan dan masuk akal untuk topik "{$judul}", jangan keluar topik.
- Beri arti dalam bahasa Indonesia yang singkat dan tepat untuk tiap item.
- Variasikan antara kata tunggal, frasa pendek, dan contoh kalimat singkat supaya kamus lebih kaya.
- Jangan ulangi kata yang sudah ada di kamus bab ini (daftar di bawah, kalau ada).{$blokSudahAda}

Balas HANYA dalam format JSON array PERSIS seperti skema ini, tanpa teks lain di luar JSON:
{$skema}
PROMPT;

        // Key khusus Kamus Vokasi, supaya tidak berebut kuota dengan Kuis/Materi.
        // Kalau GEMINI_API_KEY_KAMUS belum diisi di .env, otomatis pakai GEMINI_API_KEY biasa.
        $apiKey = env('GEMINI_API_KEY_KAMUS') ?: config('services.gemini.key');

        if (empty($apiKey)) {
            Log::error('GEMINI_API_KEY_KAMUS / GEMINI_API_KEY kosong / tidak terbaca (Kamus Saran).');
            return null;
        }

        $rawText = $this->mintaJsonDariGemini($apiKey, $prompt, 'Kamus Saran');
        if ($rawText === null) {
            return null;
        }

        $cleanText = trim($rawText);
        $cleanText = preg_replace('/^```json\s*/i', '', $cleanText);
        $cleanText = preg_replace('/^```\s*/i', '', $cleanText);
        $cleanText = preg_replace('/\s*```$/i', '', $cleanText);

        $parsed = json_decode($cleanText, true);

        if (!is_array($parsed) || empty($parsed)) {
            Log::error('Gagal parsing hasil Gemini (Kamus Saran) jadi JSON', ['raw_text' => $rawText]);
            return null;
        }

        if (array_keys($parsed) !== range(0, count($parsed) - 1)) {
            $parsed = [$parsed];
        }

        return $parsed;
    }
}