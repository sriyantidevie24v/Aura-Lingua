@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 24px; font-weight: bold; color: #1e1b4b; margin: 0 0 4px 0;">Materi</h2>
    <p style="font-size: 14px; color: #64748b; margin: 0;">Kelola daftar materi belajar untuk tiap kelas.</p>
  </div>
  <a href="{{ route('guru.materi.create') }}" style="background: #4f46e5; color: white; padding: 10px 18px; border-radius: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px;">
    <i class="fa-solid fa-plus"></i> Tambah Materi
  </a>
</div>

@if(session('success'))
  <div style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px;">
    {{ session('success') }}
  </div>
@endif

@if(session('error'))
  <div style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px;">
    {{ session('error') }}
  </div>
@endif

<!-- Panel Konten -->
<div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

  <!-- Tab Pilihan Kelas -->
  <div style="display: flex; gap: 8px; margin-bottom: 20px; background: #f1f5f9; padding: 4px; border-radius: 12px; width: fit-content;">
    <button onclick="switchTab('Kelas X')" id="tab-Kelas X" class="tab-btn" style="padding: 8px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; background: #4f46e5; color: white; font-size: 13px; transition: 0.2s;">Kelas X</button>
    <button onclick="switchTab('Kelas XI')" id="tab-Kelas XI" class="tab-btn" style="padding: 8px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; background: transparent; color: #64748b; font-size: 13px; transition: 0.2s;">Kelas XI</button>
    <button onclick="switchTab('Kelas XII')" id="tab-Kelas XII" class="tab-btn" style="padding: 8px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; background: transparent; color: #64748b; font-size: 13px; transition: 0.2s;">Kelas XII</button>
  </div>

  <!-- Tabel Materi -->
  <table style="width: 100%; border-collapse: collapse;">
    <thead>
      <tr style="border-bottom: 1px solid #e2e8f0; text-align: left;">
        <th style="padding: 12px 12px; font-size: 13px; color: #64748b; font-weight: 600; width: 32%;">Materi</th>
        <th style="padding: 12px 12px; font-size: 13px; color: #64748b; font-weight: 600; width: 18%;">Tingkat</th>
        <th style="padding: 12px 12px; font-size: 13px; color: #64748b; font-weight: 600; width: 30%;">Sub Pembahasan</th>
        <th style="padding: 12px 12px; font-size: 13px; color: #64748b; font-weight: 600; width: 20%; text-align: right;">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($materiList as $materi)
      @php
        $rowId = 'detail-' . $loop->index;
        $subList = $materi['sub_pembahasan'] ?? [];
        $subCount = is_array($subList) ? count($subList) : 0;
      @endphp
      <tr class="materi-row" data-kelas="{{ $materi['tingkat_kelas_formatted'] ?? 'Kelas X' }}" style="border-bottom: 1px solid #f1f5f9;">
        <!-- Kolom Materi & Ikon -->
        <td style="padding: 16px 12px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 38px; height: 38px; background: #e0e7ff; color: #4f46e5; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
            <i class="fa-solid fa-book"></i>
          </div>
          <span style="font-weight: 600; color: #1e1b4b; font-size: 14px;">{{ $materi['judul'] ?? '-' }}</span>
        </td>

        <!-- Kolom Tingkat Kelas -->
        <td style="padding: 16px 12px; font-size: 13px; color: #64748b;">
          {{ $materi['tingkat_kelas_formatted'] ?? 'Kelas X' }}
        </td>

        <!-- Kolom Sub Pembahasan -->
        <td style="padding: 16px 12px; font-size: 13px;">
          @if($subCount > 0)
            <button type="button" onclick="toggleDetail('{{ $rowId }}', this)" style="background: #e0e7ff; color: #4f46e5; border: none; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
              {{ $subCount }} Sub &nbsp;<i class="fa-solid fa-chevron-down"></i>
            </button>
          @else
            <span style="color: #94a3b8;">Belum ada sub</span>
          @endif
        </td>

        <!-- Kolom Tombol Aksi (Edit & Hapus) -->
        <td style="padding: 16px 12px; text-align: right;">
          <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center;">
            <!-- Tombol Edit mengarah ke halaman edit berdasarkan ID dokumen Firebase -->
            <a href="{{ route('guru.materi.edit', $materi['id']) }}" style="color: #64748b; font-size: 14px; text-decoration: none;" title="Edit">
              <i class="fa-solid fa-pen"></i>
            </a>

            <!-- Tombol Hapus -->
            <form action="{{ route('guru.materi.destroy', $materi['id']) }}" method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Yakin ingin menghapus materi ini?')">
              @csrf
              @method('DELETE')
              <button type="submit" style="background: none; border: none; color: #64748b; cursor: pointer; font-size: 14px; padding: 0;" title="Hapus">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>

      <!-- Baris Detail Sub Pembahasan (tersembunyi, dibuka lewat tombol di atas) -->
      @if($subCount > 0)
      <tr id="{{ $rowId }}" class="materi-detail-row" data-kelas="{{ $materi['tingkat_kelas_formatted'] ?? 'Kelas X' }}" style="display: none; border-bottom: 1px solid #f1f5f9;">
        <td colspan="4" style="padding: 0 12px 20px 12px; background: #f8fafc;">
          <div style="display: grid; gap: 12px; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
            @foreach($subList as $sub)
              <div style="border-left: 3px solid #4f46e5; padding-left: 14px;">
                <p style="font-weight: 700; color: #1e1b4b; font-size: 13px; margin: 0 0 6px 0;">
                  {{ $sub['nama_sub'] ?? '-' }}
                </p>

                @if(isset($sub['contoh_kalimat']) && is_array($sub['contoh_kalimat']) && count($sub['contoh_kalimat']) > 0)
                  <p style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 8px 0 4px 0;">Contoh Kalimat</p>
                  <ul style="margin: 0; padding-left: 18px;">
                    @foreach($sub['contoh_kalimat'] as $ck)
                      <li style="font-size: 13px; color: #334155; margin-bottom: 4px;">
                        <span style="font-weight: 600;">{{ $ck['kalimat'] ?? '-' }}</span>
                        @if(!empty($ck['arti']))
                          <span style="color: #94a3b8;"> &mdash; {{ $ck['arti'] }}</span>
                        @endif
                      </li>
                    @endforeach
                  </ul>
                @endif

                @if(isset($sub['poin_penting']) && is_array($sub['poin_penting']) && count($sub['poin_penting']) > 0)
                  <p style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 8px 0 4px 0;">Poin Penting</p>
                  <ul style="margin: 0; padding-left: 18px;">
                    @foreach($sub['poin_penting'] as $poin)
                      <li style="font-size: 13px; color: #334155; margin-bottom: 4px;">{{ $poin }}</li>
                    @endforeach
                  </ul>
                @endif
              </div>
            @endforeach
          </div>
        </td>
      </tr>
      @endif

      @empty
      <tr class="empty-row">
        <td colspan="4" style="text-align: center; color: #94a3b8; padding: 35px; font-size: 14px;">
          Belum ada data materi di Firebase untuk kelas ini.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<script>
  function switchTab(kelas) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.style.background = 'transparent';
      btn.style.color = '#64748b';
    });
    let activeTab = document.getElementById('tab-' + kelas);
    if (activeTab) {
      activeTab.style.background = '#4f46e5';
      activeTab.style.color = 'white';
    }

    // Tampilkan/sembunyikan baris utama sesuai kelas
    document.querySelectorAll('.materi-row').forEach(row => {
      row.style.display = (row.getAttribute('data-kelas') === kelas) ? '' : 'none';
    });

    // Sembunyikan semua baris detail saat pindah tab, reset ikon panah
    document.querySelectorAll('.materi-detail-row').forEach(row => {
      row.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn ~ * .fa-chevron-down, .fa-chevron-up').forEach(icon => {
      icon.classList.remove('fa-chevron-up');
      icon.classList.add('fa-chevron-down');
    });
  }

  function toggleDetail(rowId, btn) {
    let row = document.getElementById(rowId);
    if (!row) return;

    let icon = btn.querySelector('i');
    if (row.style.display === 'none' || row.style.display === '') {
      row.style.display = 'table-row';
      if (icon) {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
      }
    } else {
      row.style.display = 'none';
      if (icon) {
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
      }
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    switchTab('Kelas X');
  });
</script>
@endsection