{{--
  Panel saran kosakata dari AI berdasarkan JUDUL bab. Dipakai di halaman edit.
  Butuh elemen form dengan id: kata, arti_id, materi_id.
  Opsional: $kecualiId (id kosakata yang sedang diedit).
--}}
<section class="mt-6 bg-white rounded-3xl border border-slate-200/70 p-6 shadow-[0_12px_30px_-26px_rgba(30,27,75,.45)]" aria-labelledby="saranJudul">
  <div class="min-w-0">
    <h2 id="saranJudul" class="kv-display text-xl font-bold text-slate-900">Saran kosakata dari AI</h2>
    <p class="mt-1 text-sm text-slate-500 max-w-xl">
      AI membuatkan kata, frasa, atau kalimat bahasa Inggris beserta artinya, sesuai judul bab yang dipilih.
      Klik "Pakai" untuk mengisi form di atas.
    </p>
  </div>

  <div class="mt-5 flex flex-wrap items-end gap-3">
    <div>
      <label for="saranJumlah" class="block text-xs font-semibold text-slate-700 mb-1">Jumlah saran</label>
      <input id="saranJumlah" type="number" min="1" max="20" value="10"
             class="w-24 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white">
    </div>

    <button type="button" id="saranTombol"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 transition-colors">
      <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Cari saran
    </button>
  </div>

  <p id="saranStatus" class="mt-4 text-sm text-slate-500" role="status"></p>

  <ul id="saranDaftar" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3"></ul>
</section>

<script>
  (function () {
    var tombol = document.getElementById('saranTombol');
    var status = document.getElementById('saranStatus');
    var daftar = document.getElementById('saranDaftar');
    var jumlah = document.getElementById('saranJumlah');
    var fKata  = document.getElementById('kata');
    var fArti  = document.getElementById('arti_id');
    var fBab   = document.getElementById('materi_id');
    var kecualiId = @json($kecualiId ?? null);

    function csrf() {
      var el = document.querySelector('meta[name="csrf-token"]');
      return el ? el.getAttribute('content') : '';
    }

    function pesan(teks, warna) {
      status.textContent = teks;
      status.className = 'mt-4 text-sm ' + (warna || 'text-slate-500');
    }

    function isiForm(item, tombolPakai) {
      fKata.value = item.kata;
      fArti.value = item.arti_id;
      // Picu pratinjau langsung supaya ikut berubah.
      fKata.dispatchEvent(new Event('input', { bubbles: true }));
      fArti.dispatchEvent(new Event('input', { bubbles: true }));

      daftar.querySelectorAll('button[data-pakai]').forEach(function (b) {
        b.textContent = 'Pakai';
        b.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
      });
      tombolPakai.textContent = 'Dipakai';
      tombolPakai.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');

      fKata.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function buatKartu(item) {
      var li = document.createElement('li');
      li.className = 'p-4 rounded-2xl border border-slate-100 bg-slate-50/60 flex items-start justify-between gap-3';

      var kiri = document.createElement('div');
      kiri.className = 'min-w-0';

      var kata = document.createElement('p');
      kata.className = 'text-[15px] font-semibold text-slate-900 break-words';
      kata.textContent = item.kata;

      var arti = document.createElement('p');
      arti.className = 'mt-0.5 text-sm text-slate-600 break-words';
      arti.textContent = item.arti_id;

      kiri.appendChild(kata);
      kiri.appendChild(arti);

      var pakai = document.createElement('button');
      pakai.type = 'button';
      pakai.setAttribute('data-pakai', '1');
      pakai.className = 'shrink-0 px-4 py-1.5 rounded-full bg-white border border-slate-200 hover:border-indigo-300 hover:text-indigo-700 text-slate-700 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors';
      pakai.textContent = 'Pakai';
      pakai.addEventListener('click', function () { isiForm(item, pakai); });

      li.appendChild(kiri);
      li.appendChild(pakai);
      return li;
    }

    tombol.addEventListener('click', function () {
      if (!fBab.value) {
        pesan('Pilih bab terkait dulu di form di atas.', 'text-amber-700');
        return;
      }

      var data = new FormData();
      data.append('materi_id', fBab.value);
      data.append('jumlah', jumlah.value || 10);
      if (kecualiId) data.append('kecuali_id', kecualiId);

      tombol.disabled = true;
      daftar.innerHTML = '';
      pesan('AI sedang membuat saran sesuai judul bab...');

      fetch('{{ route("guru.kamus_vokasi.saran") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        body: data
      })
      .then(function (res) { return res.json(); })
      .then(function (hasil) {
        tombol.disabled = false;
        if (hasil.success && hasil.items && hasil.items.length) {
          hasil.items.forEach(function (item) { daftar.appendChild(buatKartu(item)); });
          var info = hasil.items.length + ' saran ditemukan.';
          if (hasil.dilewati) info += ' ' + hasil.dilewati + ' dilewati karena sudah ada di kamus.';
          pesan(info, 'text-emerald-600');
        } else {
          pesan(hasil.message || 'Tidak ada saran.', 'text-rose-600');
        }
      })
      .catch(function () {
        tombol.disabled = false;
        pesan('Terjadi kesalahan koneksi, coba lagi.', 'text-rose-600');
      });
    });
  })();
</script>