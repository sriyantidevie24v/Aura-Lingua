<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Google\Cloud\Firestore\FieldValue;

class GuruController extends Controller
{
    protected $database;

    public function __construct()
    {
        $this->database = Firebase::firestore()->database();
    }

    // Helper untuk mengambil data profil guru berdasarkan session login
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
            'emailGuru' => $emailGuru
        ];
    }

    public function ringkasan()
    {
        $muridDocs = $this->database->collection('pengguna')->documents();
        $materiDocs = $this->database->collection('materi_vokasi')->documents();
        $kuisDocs = $this->database->collection('quiz')->documents();
        $tesSuaraDocs = $this->database->collection('kuis_suara')->documents();
        $permintaanMenungguDocs = $this->database->collection('permintaan_kelas')
            ->where('status', '=', 'menunggu')
            ->documents();

        $totalMurid = iterator_count($muridDocs);
        $totalMateri = iterator_count($materiDocs);
        $totalKuis = iterator_count($kuisDocs);
        $totalTesSuara = iterator_count($tesSuaraDocs);
        $totalPermintaanMenunggu = iterator_count($permintaanMenungguDocs);

        $muridRef = [];
        foreach ($muridDocs as $doc) {
            if ($doc->exists()) {
                $muridRef[$doc->id()] = $doc->data();
            }
        }

        $guru = $this->getDataGuru();

        return view('guru.ringkasan', compact(
            'totalMurid',
            'totalMateri',
            'totalKuis',
            'totalTesSuara',
            'totalPermintaanMenunggu',
            'muridRef',
            'guru'
        ));
    }

    public function murid()
    {
        $documents = $this->database->collection('pengguna')->documents();
        $muridList = [];

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $muridList[$doc->id()] = $doc->data();
            }
        }

        $guru = $this->getDataGuru();

        return view('guru.murid.index', compact('muridList', 'guru'));
    }

    public function materi()
    {
        $documents = $this->database->collection('materi_vokasi')->documents();
        $materiList = [];

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $materiList[$doc->id()] = $doc->data();
            }
        }

        $guru = $this->getDataGuru();

        return view('guru.materi.index', compact('materiList', 'guru'));
    }

    public function kuis()
    {
        $documents = $this->database->collection('quiz')->documents();
        $kuisList = [];

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $kuisList[$doc->id()] = $doc->data();
            }
        }

        $guru = $this->getDataGuru();

        return view('guru.kuis.index', compact('kuisList', 'guru'));
    }

    // Method baru untuk menampilkan halaman profil guru
    public function profil()
    {
        $guru = $this->getDataGuru();

        return view('guru.profil', compact('guru'));
    }

    // ==========================================================
    // FITUR: Persetujuan Permintaan Pindah Kelas
    // ==========================================================

    /**
     * Menampilkan daftar semua permintaan pindah kelas (collection: permintaan_kelas).
     * Diurutkan terbaru dulu.
     */
    public function permintaanKelas()
    {
        $documents = $this->database->collection('permintaan_kelas')
            ->orderBy('dibuat_pada', 'DESC')
            ->documents();

        $permintaanList = [];
        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                $data['id'] = $doc->id(); // id dokumen = uid siswa
                $permintaanList[] = $data;
            }
        }

        $guru = $this->getDataGuru();

        return view('guru.permintaan.index', compact('permintaanList', 'guru'));
    }

    /**
     * Menyetujui permintaan pindah kelas: mengaktifkan tingkat_kelas baru
     * pada dokumen pengguna, lalu menandai permintaan sebagai disetujui.
     */
    public function setujuiPermintaanKelas(Request $request, string $uid)
    {
        $permintaanRef = $this->database->collection('permintaan_kelas')->document($uid);
        $snapshot = $permintaanRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.permintaan.index')->with('error', 'Permintaan tidak ditemukan.');
        }

        $data = $snapshot->data();
        $kelasDiminta = $data['kelas_diminta'] ?? null;

        if (empty($kelasDiminta)) {
            return redirect()->route('guru.permintaan.index')->with('error', 'Data kelas yang diminta tidak valid.');
        }

        $guru = $this->getDataGuru();

        // Aktifkan kelas baru pada profil siswa & bersihkan status pengajuan
        $this->database->collection('pengguna')->document($uid)->set([
            'tingkat_kelas' => $kelasDiminta,
            'status_permintaan_kelas' => 'tidak_ada',
            'kelas_diminta' => FieldValue::deleteField(),
            'diminta_pada' => FieldValue::deleteField(),
            'catatan_guru' => FieldValue::deleteField(),
        ], ['merge' => true]);

        // Catat riwayat persetujuan pada dokumen permintaan
        $permintaanRef->set([
            'status' => 'disetujui',
            'diproses_pada' => now()->toIso8601String(),
            'diproses_oleh' => $guru['namaGuru'],
        ], ['merge' => true]);

        return redirect()->route('guru.permintaan.index')
            ->with('success', "Pindah kelas ke {$kelasDiminta} disetujui.");
    }

    /**
     * Menolak permintaan pindah kelas, dengan catatan opsional dari guru.
     */
    public function tolakPermintaanKelas(Request $request, string $uid)
    {
        $request->validate([
            'catatan_guru' => 'nullable|string|max:255',
        ]);

        $permintaanRef = $this->database->collection('permintaan_kelas')->document($uid);
        $snapshot = $permintaanRef->snapshot();

        if (!$snapshot->exists()) {
            return redirect()->route('guru.permintaan.index')->with('error', 'Permintaan tidak ditemukan.');
        }

        $guru = $this->getDataGuru();
        $catatan = trim((string) $request->input('catatan_guru', ''));

        $this->database->collection('pengguna')->document($uid)->set([
            'status_permintaan_kelas' => 'ditolak',
            'catatan_guru' => $catatan,
        ], ['merge' => true]);

        $permintaanRef->set([
            'status' => 'ditolak',
            'diproses_pada' => now()->toIso8601String(),
            'diproses_oleh' => $guru['namaGuru'],
            'catatan_guru' => $catatan,
        ], ['merge' => true]);

        return redirect()->route('guru.permintaan.index')
            ->with('success', 'Permintaan pindah kelas ditolak.');
    }

    // ==========================================================
    // FITUR: Kelola Kode Akses Sekolah (untuk pendaftaran murid baru)
    // ==========================================================

    /**
     * Ambil dokumen sekolah (diasumsikan hanya ada 1 dokumen di collection 'sekolah').
     * Return null kalau belum ada dokumen sama sekali.
     */
    private function getSekolahDoc(): ?array
    {
        $documents = $this->database->collection('sekolah')->limit(1)->documents();

        foreach ($documents as $doc) {
            if ($doc->exists()) {
                return ['id' => $doc->id(), 'data' => $doc->data()];
            }
        }

        return null;
    }

    public function kodeAkses()
    {
        $sekolah = $this->getSekolahDoc();
        $guru = $this->getDataGuru();

        return view('guru.Kode akses', compact('sekolah', 'guru'));
    }

    public function updateKodeAkses(Request $request)
    {
        $request->validate([
            'kode_akses' => 'required|string|max:50',
        ]);

        $sekolah = $this->getSekolahDoc();

        if (!$sekolah) {
            // Belum ada dokumen sekolah -> buat baru
            $this->database->collection('sekolah')->add([
                'nama_sekolah' => 'smk',
                'kode_akses' => trim($request->kode_akses),
                'aktif' => true,
                'dibuat_pada' => now()->toIso8601String(),
            ]);
        } else {
            $this->database->collection('sekolah')->document($sekolah['id'])->set([
                'kode_akses' => trim($request->kode_akses),
            ], ['merge' => true]);
        }

        return redirect()->route('guru.kode-akses.edit')->with('success', 'Kode akses berhasil diperbarui.');
    }
}