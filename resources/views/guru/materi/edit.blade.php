@extends('layouts.app')

@section('content')
<div class="mb-6 flex justify-between items-center">
  <div>
    <h2 class="text-2xl font-bold text-gray-800">Edit Materi Vokasi</h2>
    <p class="text-sm text-gray-600">Form untuk mengubah data materi lengkap beserta sub pembahasan dinamis.</p>
  </div>
  <a href="{{ route('guru.materi') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-xs font-bold hover:bg-gray-300 transition">
    Kembali
  </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
  <form action="{{ route('guru.materi.update', $materi['id']) }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="space-y-4">
      <!-- AI Generate Ulang -->
      <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 space-y-3">
        <p class="text-xs font-bold text-indigo-700 uppercase">Generate Ulang (Opsional)</p>
        <p class="text-xs text-indigo-600">Klik untuk membuat ulang seluruh isi materi dari Judul memakai AI. <span class="font-bold">Ini akan mengganti sub pembahasan yang sudah ada</span>, jadi pastikan sudah yakin. Kalau cuma ada sub yang kurang lengkap, pakai tombol "Lengkapi dengan AI" di sub tersebut (isi yang sudah ada tidak dihapus).</p>
        <button type="button" id="btnGenerateAI" onclick="generateWithAI()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition flex items-center gap-1">
          <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Ulang dengan AI
        </button>
        <p id="aiStatus" class="text-xs text-gray-500"></p>
      </div>

      <!-- Judul Materi -->
      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Judul Materi</label>
        <input type="text" name="judul" id="judulInput" value="{{ $materi['judul'] ?? '' }}" required class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh: Dasar Otomotif">
      </div>

      <!-- Deskripsi -->
      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Deskripsi</label>
        <textarea name="deskripsi" rows="3" required class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 resize-none" placeholder="Deskripsi singkat mengenai materi">{{ $materi['deskripsi'] ?? '' }}</textarea>
      </div>

      <!-- Tingkat Kelas -->
      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tingkat Kelas</label>
        <select name="tingkat_kelas" required class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 bg-white">
          <option value="X" {{ ($materi['tingkat_kelas'] ?? '') == 'X' ? 'selected' : '' }}>Kelas X</option>
          <option value="XI" {{ ($materi['tingkat_kelas'] ?? '') == 'XI' ? 'selected' : '' }}>Kelas XI</option>
          <option value="XII" {{ ($materi['tingkat_kelas'] ?? '') == 'XII' ? 'selected' : '' }}>Kelas XII</option>
        </select>
      </div>

      <hr class="my-6 border-gray-200">
      <div class="flex flex-wrap gap-2 justify-between items-center">
        <h3 class="text-sm font-bold text-indigo-600 uppercase">Sub Pembahasan & Contoh</h3>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="addSubWithAI()" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 transition flex items-center gap-1">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Tambah Sub dengan AI
          </button>
          <button type="button" onclick="addSubPembahasan()" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition flex items-center gap-1">
            <i class="fa-solid fa-plus"></i> Tambah Sub Pembahasan
          </button>
        </div>
      </div>

      <!-- Container Sub Pembahasan Dinamis -->
      <div id="sub-pembahasan-container" class="space-y-6">
        @if(isset($materi['sub_pembahasan']) && is_array($materi['sub_pembahasan']) && count($materi['sub_pembahasan']) > 0)
          @foreach($materi['sub_pembahasan'] as $subIdx => $sub)
            <div class="sub-item border border-gray-200 rounded-xl p-4 bg-gray-50 relative space-y-3" data-idx="{{ $subIdx }}">
              @if($subIdx > 0)
              <button type="button" onclick="this.parentElement.remove()" class="absolute top-4 right-4 text-red-500 hover:text-red-700 text-sm font-bold" title="Hapus Sub">
                <i class="fa-solid fa-trash"></i>
              </button>
              @endif
              <div>
                <div class="flex items-center justify-between gap-2 mb-1 {{ $subIdx > 0 ? 'pr-8' : '' }}">
                  <label class="block text-xs font-bold text-gray-700 uppercase">Nama Sub Pembahasan</label>
                  <button type="button" onclick="generateSubAI(this)" class="btn-sub-ai px-3 py-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-lg text-[11px] font-bold hover:bg-indigo-100 transition flex items-center gap-1" title="Melengkapi sub ini dengan isi baru (isi yang sudah ada tidak dihapus). Kosongkan nama sub untuk minta saran sub baru.">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Lengkapi dengan AI
                  </button>
                </div>
                <input type="text" name="sub_bahasan[{{ $subIdx }}][nama_sub]" value="{{ $sub['nama_sub'] ?? '' }}" class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh: Pengenalan Mesin">
                <p class="sub-ai-status text-[11px] mt-1"></p>
              </div>

              <!-- Contoh Kalimat -->
              <div class="space-y-2 contoh-container">
                <label class="block text-xs font-bold text-gray-700 uppercase">Contoh Kalimat & Arti</label>
                <p class="text-[11px] text-gray-400">Tips: copy 2 kolom dari Excel/Spreadsheet lalu paste di sini, otomatis kebagi jadi banyak baris.</p>
                @if(isset($sub['contoh_kalimat']) && is_array($sub['contoh_kalimat']) && count($sub['contoh_kalimat']) > 0)
                  @foreach($sub['contoh_kalimat'] as $ck)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                      <input type="text" name="sub_bahasan[{{ $subIdx }}][kalimat][]" value="{{ $ck['kalimat'] ?? '' }}" class="kalimat-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh kalimat">
                      <input type="text" name="sub_bahasan[{{ $subIdx }}][arti][]" value="{{ $ck['arti'] ?? '' }}" class="arti-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Arti kalimat">
                    </div>
                  @endforeach
                @else
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <input type="text" name="sub_bahasan[{{ $subIdx }}][kalimat][]" class="kalimat-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh kalimat">
                    <input type="text" name="sub_bahasan[{{ $subIdx }}][arti][]" class="arti-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Arti kalimat">
                  </div>
                @endif
              </div>
              <button type="button" onclick="addContohKalimat(this, {{ $subIdx }})" class="text-xs text-indigo-600 font-semibold hover:underline mt-1 inline-block">+ Tambah Contoh Kalimat</button>

              <!-- Poin Penting Dinamis -->
              <div class="space-y-2 poin-container mt-3">
                <label class="block text-xs font-bold text-gray-700 uppercase">Poin Penting</label>
                <p class="text-[11px] text-gray-400">Tips: paste banyak baris sekaligus, tiap baris otomatis jadi 1 poin.</p>
                @if(isset($sub['poin_penting']) && is_array($sub['poin_penting']) && count($sub['poin_penting']) > 0)
                  @foreach($sub['poin_penting'] as $poin)
                    <div class="flex gap-2 mt-2">
                      <input type="text" name="sub_bahasan[{{ $subIdx }}][poin_penting][]" value="{{ $poin }}" class="poin-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Tuliskan poin penting...">
                    </div>
                  @endforeach
                @else
                  <div class="flex gap-2">
                    <input type="text" name="sub_bahasan[{{ $subIdx }}][poin_penting][]" class="poin-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Tuliskan poin penting...">
                  </div>
                @endif
              </div>
              <button type="button" onclick="addPoinPenting(this, {{ $subIdx }})" class="text-xs text-indigo-600 font-semibold hover:underline mt-1 inline-block">+ Tambah Poin Penting</button>
            </div>
          @endforeach
        @else
          <!-- Default Kosong jika belum ada sub pembahasan -->
          <div class="sub-item border border-gray-200 rounded-xl p-4 bg-gray-50 relative space-y-3" data-idx="0">
            <div>
              <div class="flex items-center justify-between gap-2 mb-1">
                <label class="block text-xs font-bold text-gray-700 uppercase">Nama Sub Pembahasan</label>
                <button type="button" onclick="generateSubAI(this)" class="btn-sub-ai px-3 py-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-lg text-[11px] font-bold hover:bg-indigo-100 transition flex items-center gap-1" title="Isi nama sub lalu klik untuk melengkapi. Kosongkan nama sub untuk minta saran sub baru.">
                  <i class="fa-solid fa-wand-magic-sparkles"></i> Lengkapi dengan AI
                </button>
              </div>
              <input type="text" name="sub_bahasan[0][nama_sub]" class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh: Pengenalan Mesin">
              <p class="sub-ai-status text-[11px] mt-1"></p>
            </div>

            <div class="space-y-2 contoh-container">
              <label class="block text-xs font-bold text-gray-700 uppercase">Contoh Kalimat & Arti</label>
              <p class="text-[11px] text-gray-400">Tips: copy 2 kolom dari Excel/Spreadsheet lalu paste di sini, otomatis kebagi jadi banyak baris.</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <input type="text" name="sub_bahasan[0][kalimat][]" class="kalimat-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh kalimat">
                <input type="text" name="sub_bahasan[0][arti][]" class="arti-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Arti kalimat">
              </div>
            </div>
            <button type="button" onclick="addContohKalimat(this, 0)" class="text-xs text-indigo-600 font-semibold hover:underline mt-1 inline-block">+ Tambah Contoh Kalimat</button>

            <div class="space-y-2 poin-container mt-3">
              <label class="block text-xs font-bold text-gray-700 uppercase">Poin Penting</label>
              <p class="text-[11px] text-gray-400">Tips: paste banyak baris sekaligus, tiap baris otomatis jadi 1 poin.</p>
              <div class="flex gap-2">
                <input type="text" name="sub_bahasan[0][poin_penting][]" class="poin-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Tuliskan poin penting...">
              </div>
            </div>
            <button type="button" onclick="addPoinPenting(this, 0)" class="text-xs text-indigo-600 font-semibold hover:underline mt-1 inline-block">+ Tambah Poin Penting</button>
          </div>
        @endif
      </div>

      <!-- Tombol Simpan -->
      <div class="pt-4 flex gap-3">
        <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition shadow-sm">
          Perbarui Materi
        </button>
        <a href="{{ route('guru.materi') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 rounded-xl text-xs font-bold hover:bg-gray-300 transition">
          Batal
        </a>
      </div>
    </div>
  </form>
</div>

<script>
// max(..., 0) supaya tidak jadi -1 kalau sub_pembahasan kosong (kartu default memakai idx 0)
let subIndex = {{ isset($materi['sub_pembahasan']) && is_array($materi['sub_pembahasan']) ? max(count($materi['sub_pembahasan']) - 1, 0) : 0 }};

function addSubPembahasan() {
    subIndex++;
    let container = document.getElementById('sub-pembahasan-container');
    let html = `
        <div class="sub-item border border-gray-200 rounded-xl p-4 bg-gray-50 relative space-y-3" data-idx="${subIndex}">
          <button type="button" onclick="this.parentElement.remove()" class="absolute top-4 right-4 text-red-500 hover:text-red-700 text-sm font-bold" title="Hapus Sub">
            <i class="fa-solid fa-trash"></i>
          </button>
          <div>
            <div class="flex items-center justify-between gap-2 mb-1 pr-8">
              <label class="block text-xs font-bold text-gray-700 uppercase">Nama Sub Pembahasan</label>
              <button type="button" onclick="generateSubAI(this)" class="btn-sub-ai px-3 py-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-lg text-[11px] font-bold hover:bg-indigo-100 transition flex items-center gap-1" title="Isi nama sub lalu klik untuk melengkapi. Kosongkan nama sub untuk minta saran sub baru.">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Lengkapi dengan AI
              </button>
            </div>
            <input type="text" name="sub_bahasan[${subIndex}][nama_sub]" class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh: Pengenalan Mesin">
            <p class="sub-ai-status text-[11px] mt-1"></p>
          </div>

          <div class="space-y-2 contoh-container">
            <label class="block text-xs font-bold text-gray-700 uppercase">Contoh Kalimat & Arti</label>
            <p class="text-[11px] text-gray-400">Tips: copy 2 kolom dari Excel/Spreadsheet lalu paste di sini, otomatis kebagi jadi banyak baris.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
              <input type="text" name="sub_bahasan[${subIndex}][kalimat][]" class="kalimat-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh kalimat">
              <input type="text" name="sub_bahasan[${subIndex}][arti][]" class="arti-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Arti kalimat">
            </div>
          </div>
          <button type="button" onclick="addContohKalimat(this, ${subIndex})" class="text-xs text-indigo-600 font-semibold hover:underline mt-1 inline-block">+ Tambah Contoh Kalimat</button>

          <div class="space-y-2 poin-container mt-3">
            <label class="block text-xs font-bold text-gray-700 uppercase">Poin Penting</label>
            <p class="text-[11px] text-gray-400">Tips: paste banyak baris sekaligus, tiap baris otomatis jadi 1 poin.</p>
            <div class="flex gap-2">
              <input type="text" name="sub_bahasan[${subIndex}][poin_penting][]" class="poin-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Tuliskan poin penting...">
            </div>
          </div>
          <button type="button" onclick="addPoinPenting(this, ${subIndex})" class="text-xs text-indigo-600 font-semibold hover:underline mt-1 inline-block">+ Tambah Poin Penting</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

// ==== Tambah 1 baris Contoh Kalimat ke sub-item dengan idx tertentu ====
function addContohKalimatRow(idx) {
    const subItem = document.querySelector(`.sub-item[data-idx="${idx}"]`);
    if (!subItem) return;
    const contohContainer = subItem.querySelector('.contoh-container');
    const html = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
          <input type="text" name="sub_bahasan[${idx}][kalimat][]" class="kalimat-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Contoh kalimat">
          <input type="text" name="sub_bahasan[${idx}][arti][]" class="arti-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Arti kalimat">
        </div>
    `;
    contohContainer.insertAdjacentHTML('beforeend', html);
}

function addContohKalimat(btn, idx) {
    addContohKalimatRow(idx);
}

// ==== Tambah 1 baris Poin Penting ke sub-item dengan idx tertentu ====
function addPoinPentingRow(idx) {
    const subItem = document.querySelector(`.sub-item[data-idx="${idx}"]`);
    if (!subItem) return;
    const poinContainer = subItem.querySelector('.poin-container');
    const html = `
        <div class="flex gap-2 mt-2">
          <input type="text" name="sub_bahasan[${idx}][poin_penting][]" class="poin-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600" placeholder="Tuliskan poin penting...">
        </div>
    `;
    poinContainer.insertAdjacentHTML('beforeend', html);
}

function addPoinPenting(btn, idx) {
    addPoinPentingRow(idx);
}

// ==== Ambil idx sub-item dari elemen input ====
function getSubIdx(el) {
    const subItem = el.closest('.sub-item');
    return subItem ? subItem.getAttribute('data-idx') : null;
}

// ==== Fitur paste banyak baris untuk Contoh Kalimat & Arti ====
function handleKalimatPaste(e) {
    const text = (e.clipboardData || window.clipboardData).getData('text');
    if (!text || (!text.includes('\n') && !text.includes('\t'))) return; // biarkan paste normal kalau cuma 1 kata/kalimat

    e.preventDefault();
    const idx = getSubIdx(e.target);
    if (idx === null) return;

    const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
    if (lines.length === 0) return;

    const subItem = e.target.closest('.sub-item');
    const contohContainer = subItem.querySelector('.contoh-container');
    const currentRow = e.target.closest('.grid');

    // baris pertama isi ke baris yang sedang difokus
    const [k0, a0] = lines[0].split('\t');
    currentRow.querySelector('.kalimat-input').value = (k0 || '').trim();
    currentRow.querySelector('.arti-input').value = (a0 || '').trim();

    // baris berikutnya otomatis bikin baris baru
    for (let i = 1; i < lines.length; i++) {
        const [k, a] = lines[i].split('\t');
        addContohKalimatRow(idx);
        const allRows = contohContainer.querySelectorAll('.grid');
        const newRow = allRows[allRows.length - 1];
        newRow.querySelector('.kalimat-input').value = (k || '').trim();
        newRow.querySelector('.arti-input').value = (a || '').trim();
    }
}

// ==== Fitur paste banyak baris untuk Poin Penting ====
function handlePoinPaste(e) {
    const text = (e.clipboardData || window.clipboardData).getData('text');
    if (!text || !text.includes('\n')) return; // biarkan paste normal kalau cuma 1 baris

    e.preventDefault();
    const idx = getSubIdx(e.target);
    if (idx === null) return;

    const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
    if (lines.length === 0) return;

    const subItem = e.target.closest('.sub-item');
    const poinContainer = subItem.querySelector('.poin-container');

    // baris pertama isi ke input yang sedang difokus
    e.target.value = lines[0].trim();

    for (let i = 1; i < lines.length; i++) {
        addPoinPentingRow(idx);
        const allRows = poinContainer.querySelectorAll('.poin-input');
        allRows[allRows.length - 1].value = lines[i].trim();
    }
}

// Event delegation supaya input yang ditambahkan secara dinamis juga kena listener paste
document.addEventListener('paste', function (e) {
    if (e.target.classList && e.target.classList.contains('kalimat-input')) {
        handleKalimatPaste(e);
    } else if (e.target.classList && e.target.classList.contains('poin-input')) {
        handlePoinPaste(e);
    }
}, true);

// ==== Isi ulang form dari hasil AI (generate ulang) ====
function fillGeneratedContent(data) {
    if (data.judul) {
        document.getElementById('judulInput').value = data.judul;
    }
    if (data.deskripsi) {
        document.querySelector('textarea[name="deskripsi"]').value = data.deskripsi;
    }

    const container = document.getElementById('sub-pembahasan-container');
    container.innerHTML = '';
    subIndex = -1; // addSubPembahasan() akan increment jadi mulai dari 0

    (data.sub_bahasan || []).forEach(sub => {
        addSubPembahasan();
        const idx = subIndex;
        const subItem = document.querySelector(`.sub-item[data-idx="${idx}"]`);

        subItem.querySelector('input[name$="[nama_sub]"]').value = sub.nama_sub || '';

        subItem.querySelectorAll('.contoh-container .grid').forEach(el => el.remove());
        subItem.querySelectorAll('.poin-container .flex.gap-2').forEach(el => el.remove());

        (sub.contoh_kalimat || []).forEach(ck => {
            addContohKalimatRow(idx);
            const rows = subItem.querySelectorAll('.contoh-container .grid');
            const row = rows[rows.length - 1];
            row.querySelector('.kalimat-input').value = ck.kalimat || '';
            row.querySelector('.arti-input').value = ck.arti || '';
        });

        (sub.poin_penting || []).forEach(poin => {
            addPoinPentingRow(idx);
            const rows = subItem.querySelectorAll('.poin-container .poin-input');
            rows[rows.length - 1].value = poin;
        });
    });
}

function getCsrfToken() {
    const el = document.querySelector('input[name="_token"]');
    return el ? el.value : '';
}

// ==== Generate / lengkapi SATU sub pembahasan dengan AI ====
function setSubStatus(subItem, text, type) {
    const el = subItem.querySelector('.sub-ai-status');
    if (!el) return;
    el.textContent = text;
    el.className = 'sub-ai-status text-[11px] mt-1 ' + (
        type === 'error' ? 'text-red-500' : (type === 'success' ? 'text-green-600' : 'text-gray-500')
    );
}

function applySubResult(subItem, data) {
    const idx = subItem.getAttribute('data-idx');
    const namaInput = subItem.querySelector('input[name$="[nama_sub]"]');

    // kalau nama sub kosong (mode saran), pakai nama dari AI
    if (!namaInput.value.trim() && data.nama_sub) {
        namaInput.value = data.nama_sub;
    }

    // buang baris kosong supaya hasil AI mengisi tempat yang pas
    subItem.querySelectorAll('.contoh-container .grid').forEach(row => {
        const k = row.querySelector('.kalimat-input').value.trim();
        const a = row.querySelector('.arti-input').value.trim();
        if (!k && !a) row.remove();
    });
    subItem.querySelectorAll('.poin-container .poin-input').forEach(inp => {
        if (!inp.value.trim()) inp.parentElement.remove();
    });

    // daftar yang sudah ada, untuk mencegah duplikat
    const kalimatAda = new Set(
        [...subItem.querySelectorAll('.kalimat-input')].map(el => el.value.trim().toLowerCase())
    );
    const poinAda = new Set(
        [...subItem.querySelectorAll('.poin-input')].map(el => el.value.trim().toLowerCase())
    );

    (data.contoh_kalimat || []).forEach(ck => {
        const k = (ck.kalimat || '').trim();
        if (!k || kalimatAda.has(k.toLowerCase())) return;
        addContohKalimatRow(idx);
        const rows = subItem.querySelectorAll('.contoh-container .grid');
        const row = rows[rows.length - 1];
        row.querySelector('.kalimat-input').value = k;
        row.querySelector('.arti-input').value = ck.arti || '';
    });

    (data.poin_penting || []).forEach(p => {
        const poin = (p || '').trim();
        if (!poin || poinAda.has(poin.toLowerCase())) return;
        addPoinPentingRow(idx);
        const rows = subItem.querySelectorAll('.poin-container .poin-input');
        rows[rows.length - 1].value = poin;
    });

    // kalau semua baris sempat terhapus dan AI tidak mengisi apa pun, sisakan 1 baris kosong
    if (!subItem.querySelector('.kalimat-input')) addContohKalimatRow(idx);
    if (!subItem.querySelector('.poin-input')) addPoinPentingRow(idx);
}

function generateSubAI(btn) {
    const subItem = btn.closest('.sub-item');
    const judul = document.getElementById('judulInput').value.trim();

    if (!judul) {
        setSubStatus(subItem, 'Isi Judul Materi dulu sebelum generate.', 'error');
        return;
    }

    const namaInput = subItem.querySelector('input[name$="[nama_sub]"]');
    const namaSub = namaInput.value.trim();

    // nama sub lain (selain sub ini) supaya AI tidak mengusulkan yang dobel
    const subLain = [...document.querySelectorAll('.sub-item input[name$="[nama_sub]"]')]
        .filter(el => el !== namaInput)
        .map(el => el.value.trim())
        .filter(Boolean);

    const kalimatAda = [...subItem.querySelectorAll('.kalimat-input')].map(el => el.value.trim()).filter(Boolean);
    const poinAda = [...subItem.querySelectorAll('.poin-input')].map(el => el.value.trim()).filter(Boolean);

    btn.disabled = true;
    btn.classList.add('opacity-60', 'cursor-not-allowed');
    setSubStatus(subItem, namaSub ? 'Sedang melengkapi sub pembahasan...' : 'Sedang mencari saran sub pembahasan...', 'info');

    fetch('{{ route("guru.materi.generateSubAI") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            judul: judul,
            deskripsi: document.querySelector('textarea[name="deskripsi"]').value,
            tingkat_kelas: document.querySelector('select[name="tingkat_kelas"]').value,
            nama_sub: namaSub,
            sub_yang_ada: subLain,
            kalimat_ada: kalimatAda,
            poin_ada: poinAda,
        }),
    })
    .then(res => res.json())
    .then(result => {
        btn.disabled = false;
        btn.classList.remove('opacity-60', 'cursor-not-allowed');
        if (result.success) {
            applySubResult(subItem, result.data);
            setSubStatus(subItem, 'Berhasil! Cek dan edit hasilnya sebelum disimpan.', 'success');
        } else {
            setSubStatus(subItem, result.message || 'Gagal generate, coba lagi.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-60', 'cursor-not-allowed');
        setSubStatus(subItem, 'Terjadi kesalahan koneksi, coba lagi.', 'error');
    });
}

// ==== Tambah sub baru kosong, lalu minta AI mengusulkan isinya ====
function addSubWithAI() {
    addSubPembahasan();
    const subs = document.querySelectorAll('.sub-item');
    const last = subs[subs.length - 1];
    generateSubAI(last.querySelector('.btn-sub-ai'));
}

// ==== Generate ulang materi otomatis dari judul ====
function generateWithAI() {
    const judul = document.getElementById('judulInput').value.trim();
    const status = document.getElementById('aiStatus');
    const btn = document.getElementById('btnGenerateAI');

    if (!judul) {
        status.textContent = 'Isi Judul Materi dulu sebelum generate.';
        status.className = 'text-xs text-red-500';
        return;
    }

    if (!confirm('Ini akan mengganti semua sub pembahasan yang sudah ada dengan hasil AI. Lanjutkan?')) {
        return;
    }

    btn.disabled = true;
    status.textContent = 'Sedang generate materi, mohon tunggu...';
    status.className = 'text-xs text-gray-500';

    fetch('{{ route("guru.materi.generateAI") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            judul: judul,
            tingkat_kelas: document.querySelector('select[name="tingkat_kelas"]').value,
        }),
    })
    .then(res => res.json())
    .then(result => {
        btn.disabled = false;
        if (result.success) {
            fillGeneratedContent(result.data);
            status.textContent = 'Berhasil! Cek dan edit hasilnya sebelum disimpan.';
            status.className = 'text-xs text-green-600';
        } else {
            status.textContent = result.message || 'Gagal generate, coba lagi.';
            status.className = 'text-xs text-red-500';
        }
    })
    .catch(() => {
        btn.disabled = false;
        status.textContent = 'Terjadi kesalahan koneksi, coba lagi.';
        status.className = 'text-xs text-red-500';
    });
}
</script>
@endsection