@extends('layouts.app')

@section('content')
@php
  // Baris awal: kembalikan isian lama kalau validasi gagal, atau satu baris kosong.
  $barisAwal = collect(old('kamus', [['kata' => '', 'arti_id' => '']]))
      ->map(fn ($b) => ['kata' => $b['kata'] ?? '', 'arti_id' => $b['arti_id'] ?? ''])
      ->values();
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  .kv-wrap { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
  .kv-display { font-family: 'Fraunces', Georgia, serif; letter-spacing: -0.02em; }
</style>

<div class="kv-wrap max-w-3xl mx-auto pb-16">

  <a href="{{ route('guru.kamus_vokasi.index') }}"
     class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg transition-colors">
    <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke kamus
  </a>

  <h1 class="kv-display mt-3 text-3xl md:text-4xl font-bold text-slate-900">Tambah kosakata</h1>
  <p class="mt-1.5 text-sm text-slate-500">
    Ketik kata satu per satu atau biarkan AI membuatkan kosakata sesuai judul bab ini.
  </p>

  @if ($errors->any())
    <div role="alert" class="mt-6 p-4 bg-rose-50 border border-rose-100 text-rose-800 text-sm rounded-2xl">
      <p class="font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation text-rose-500"></i> Ada isian yang perlu diperbaiki
      </p>
      <ul class="mt-2 ml-6 list-disc space-y-0.5">
        @foreach (collect($errors->all())->unique() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('guru.kamus_vokasi.store') }}" method="POST" class="mt-8 space-y-5">
    @csrf

    <!-- Bab (sudah dipilih di halaman kamus) -->
    <div class="bg-white rounded-3xl border border-slate-200/70 p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)] flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-semibold text-slate-500">Bab terkait</p>
        <p class="kv-display text-lg font-bold text-slate-900 break-words">{{ $selectedBab }}</p>
      </div>
      <a href="{{ route('guru.kamus_vokasi.index') }}"
         class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
        Ganti bab
      </a>
      <input type="hidden" id="materi_id" name="materi_id" value="{{ old('materi_id', $selectedMateriId) }}">
    </div>

    <!-- Isi kata (manual atau AI) -->
    <div class="bg-white rounded-3xl border border-slate-200/70 p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-800">Isi kosakata</h2>
          <p class="text-xs text-slate-400 mt-0.5"><span id="jumlahBaris">0</span> kata siap disimpan</p>
        </div>
        <button type="button" id="tambahBaris"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 hover:border-indigo-300 hover:text-indigo-700 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
          <i class="fa-solid fa-plus text-xs"></i> Tambah kata manual
        </button>
      </div>

      <!-- Bar generate AI -->
      <div class="mt-4 p-4 rounded-2xl bg-indigo-50/60 border border-indigo-100">
        <p class="text-sm font-semibold text-indigo-900 flex items-center gap-2">
          <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Buat otomatis dengan AI
        </p>
        <p class="mt-0.5 text-xs text-indigo-800/80">
          AI membuatkan kata, frasa, atau kalimat bahasa Inggris beserta artinya, sesuai judul bab yang dipilih (misalnya bab "Job Interview" menghasilkan kosakata seputar wawancara kerja). Kata yang sudah ada di kamus tidak diulang.
        </p>

        <div class="mt-3 flex flex-wrap items-end gap-3">
          <div>
            <label for="saranJumlah" class="block text-xs font-semibold text-indigo-900 mb-1">Jumlah</label>
            <input id="saranJumlah" type="number" min="1" max="20" value="10"
                   class="w-24 px-4 py-2.5 bg-white border border-indigo-100 rounded-2xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
          </div>
          <button type="button" id="saranTombol"
                  class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
            <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Buat &amp; isi otomatis
          </button>
        </div>

        <p id="saranStatus" class="mt-3 text-sm text-slate-500" role="status"></p>
      </div>

      <!-- Daftar baris kata -->
      <div id="kamus-container" class="mt-5 space-y-3"></div>

      <p id="kosongInfo" class="hidden mt-5 text-center text-sm text-slate-400">
        Belum ada baris. Klik "Tambah kata manual" atau "Buat &amp; isi otomatis".
      </p>
    </div>

    <div class="flex flex-wrap justify-end gap-3 pt-2">
      <a href="{{ route('guru.kamus_vokasi.index') }}"
         class="px-5 py-2.5 rounded-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors">
        Batal
      </a>
      <button type="submit"
              class="px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
        Simpan semua kosakata
      </button>
    </div>
  </form>
</div>

<script>
  (function () {
    var container = document.getElementById('kamus-container');
    var jumlahEl  = document.getElementById('jumlahBaris');
    var kosongEl  = document.getElementById('kosongInfo');
    var tombolTambah = document.getElementById('tambahBaris');
    var tombolSaran  = document.getElementById('saranTombol');
    var statusEl  = document.getElementById('saranStatus');
    var selBab    = document.getElementById('materi_id'); // input hidden, bab dipilih di halaman index
    var idx = 0;

    var kelasInput = 'w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white';

    function csrf() {
      var el = document.querySelector('meta[name="csrf-token"]');
      return el ? el.getAttribute('content') : '';
    }

    function norm(t) {
      return (t || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function pesan(teks, warna) {
      statusEl.textContent = teks;
      statusEl.className = 'mt-3 text-sm ' + (warna || 'text-slate-500');
    }

    function perbaruiPenghitung() {
      var baris = container.querySelectorAll('.kamus-baris');
      var terisi = 0;
      baris.forEach(function (b) {
        if (b.querySelector('.in-kata').value.trim() && b.querySelector('.in-arti').value.trim()) terisi++;
      });
      jumlahEl.textContent = terisi;
      kosongEl.classList.toggle('hidden', baris.length > 0);

      // Beri nomor ulang supaya urutannya rapi setelah baris dihapus.
      baris.forEach(function (b, i) {
        b.querySelector('.no-baris').textContent = i + 1;
      });
    }

    function tambahBaris(data) {
      data = data || {};
      var i = idx++;

      var wrap = document.createElement('div');
      wrap.className = 'kamus-baris p-4 rounded-2xl border border-slate-100 bg-slate-50/60';

      var kepala = document.createElement('div');
      kepala.className = 'flex items-center justify-between gap-3 mb-3';

      var nomor = document.createElement('span');
      nomor.className = 'no-baris w-7 h-7 rounded-full bg-white border border-slate-200 flex items-center justify-center text-xs font-semibold text-slate-600';
      kepala.appendChild(nomor);

      var kanan = document.createElement('div');
      kanan.className = 'flex items-center gap-2';

      var hapus = document.createElement('button');
      hapus.type = 'button';
      hapus.className = 'text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 rounded';
      hapus.innerHTML = '<i class="fa-solid fa-trash"></i> Hapus';
      hapus.addEventListener('click', function () {
        wrap.remove();
        perbaruiPenghitung();
      });
      kanan.appendChild(hapus);
      kepala.appendChild(kanan);
      wrap.appendChild(kepala);

      var grid = document.createElement('div');
      grid.className = 'grid grid-cols-1 md:grid-cols-2 gap-3';

      function buatKolom(label, nama, kelas, nilai, placeholder) {
        var kol = document.createElement('div');
        var lb = document.createElement('label');
        lb.className = 'block text-xs font-semibold text-slate-700 mb-1';
        lb.textContent = label;
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.name = 'kamus[' + i + '][' + nama + ']';
        inp.className = kelasInput + ' ' + kelas;
        inp.placeholder = placeholder;
        inp.maxLength = 255;
        inp.value = nilai || '';
        inp.addEventListener('input', perbaruiPenghitung);
        kol.appendChild(lb);
        kol.appendChild(inp);
        return kol;
      }

      grid.appendChild(buatKolom('Kata target', 'kata', 'in-kata', data.kata, 'Contoh: curriculum vitae'));
      grid.appendChild(buatKolom('Arti dalam Bahasa Indonesia', 'arti_id', 'in-arti', data.arti_id, 'Contoh: daftar riwayat hidup'));
      wrap.appendChild(grid);

      container.appendChild(wrap);
      perbaruiPenghitung();
      return wrap;
    }

    // Baris awal dari server (isian lama kalau validasi gagal, atau satu baris kosong).
    @json($barisAwal).forEach(function (b) { tambahBaris(b); });

    tombolTambah.addEventListener('click', function () {
      var b = tambahBaris({});
      b.querySelector('.in-kata').focus();
    });

    // Minta saran AI sesuai judul bab, lalu isi baris kosong dulu, sisanya jadi baris baru.
    tombolSaran.addEventListener('click', function () {
      if (!selBab.value) {
        pesan('Bab belum dipilih. Kembali ke halaman kamus lalu pilih bab lewat tombol "Tambah kosakata".', 'text-amber-700');
        return;
      }

      var data = new FormData();
      data.append('materi_id', selBab.value);
      data.append('jumlah', document.getElementById('saranJumlah').value || 10);

      tombolSaran.disabled = true;
      pesan('AI sedang membuat kosakata sesuai judul bab...');

      fetch('{{ route("guru.kamus_vokasi.saran") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        body: data
      })
      .then(function (res) { return res.json(); })
      .then(function (hasil) {
        tombolSaran.disabled = false;

        if (!(hasil.success && hasil.items && hasil.items.length)) {
          pesan(hasil.message || 'Tidak ada saran.', 'text-rose-600');
          return;
        }

        // Kata yang sudah ada di form tidak dimasukkan dua kali.
        var sudahDiForm = {};
        container.querySelectorAll('.in-kata').forEach(function (el) {
          if (el.value.trim()) sudahDiForm[norm(el.value)] = true;
        });

        var dimasukkan = 0;
        hasil.items.forEach(function (item) {
          var k = norm(item.kata);
          if (sudahDiForm[k]) return;
          sudahDiForm[k] = true;

          // Pakai baris yang masih kosong lebih dulu.
          var kosong = null;
          container.querySelectorAll('.kamus-baris').forEach(function (b) {
            if (!kosong && !b.querySelector('.in-kata').value.trim() && !b.querySelector('.in-arti').value.trim()) {
              kosong = b;
            }
          });

          if (kosong) {
            kosong.remove();
          }
          tambahBaris(item);
          dimasukkan++;
        });

        if (dimasukkan === 0) {
          pesan('Semua saran sudah ada di form. Coba buat lagi untuk mendapat kosakata baru.', 'text-amber-700');
          return;
        }

        var info = dimasukkan + ' kata ditambahkan ke form. Periksa dulu, ubah kalau perlu, lalu simpan.';
        if (hasil.dilewati) info += ' ' + hasil.dilewati + ' dilewati karena sudah ada di kamus.';
        pesan(info, 'text-emerald-600');
      })
      .catch(function () {
        tombolSaran.disabled = false;
        pesan('Terjadi kesalahan koneksi, coba lagi.', 'text-rose-600');
      });
    });
  })();
</script>
@endsection