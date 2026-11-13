@extends('layouts.app')

@section('content')
<div class="topbar">
  <div><h2>Progres Murid</h2><p>Pantau level dan penyelesaian materi tiap murid dari Firebase.</p></div>
  <div class="search-box">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" placeholder="Cari nama murid...">
  </div>
</div>

<div class="panel">
  <table>
    <thead>
      <tr>
        <th>Nama</th>
        <th>Email / Role</th>
        <th>Level</th>
        <th>Progres Materi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($muridList as $id => $murid)
      <tr>
        <td style="font-weight:700;">{{ $murid['nama'] ?? ($murid['name'] ?? '-') }}</td>
        <td><span class="pill pill-x">{{ $murid['email'] ?? '-' }}</span></td>
        <td>Level {{ $murid['level'] ?? 1 }}</td>
        <td>
          <div class="progress-track"><div class="progress-fill" style="width: {{ $murid['progres'] ?? 0 }}%;"></div></div>
          {{ $murid['progres'] ?? 0 }}%
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" style="text-align:center; color:var(--ink-soft); padding:20px;">Tidak ada data murid di database Firebase.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection