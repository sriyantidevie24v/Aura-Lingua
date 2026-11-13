<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Guru - Aura Lingua</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- FontAwesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Fraunces (serif ekspresif) + Inter (sans rapi) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Fraunces', serif; }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-enter {
            animation: cardIn 0.5s ease-out both;
        }
        @media (prefers-reduced-motion: reduce) {
            .card-enter { animation: none; }
        }
    </style>
</head>
<body class="min-h-screen bg-[#F7F6F9] text-[#221C2E]">

    <div class="min-h-screen grid md:grid-cols-2">

        <!-- ============ SISI KIRI: Kartu Kosakata (identitas visual) ============ -->
        <div class="relative bg-[#2E1A3D] text-white px-8 py-10 md:px-14 md:py-16 flex flex-col justify-between overflow-hidden">

            <!-- Garis halus dekoratif, mengikuti bentuk kartu kamus -->
            <div class="absolute top-0 right-0 w-64 h-64 border border-white/10 rounded-full translate-x-1/3 -translate-y-1/3 pointer-events-none"></div>
            <div class="absolute bottom-10 right-10 w-24 h-24 border border-[#E8A33D]/30 rounded-full pointer-events-none"></div>

            <!-- Wordmark -->
            <div class="relative flex items-center gap-3">
                {{-- <div class="w-10 h-10 rounded-xl bg-[#E8A33D] flex items-center justify-center text-lg shrink-0">
                    🐹
                </div> --}}
                <span class="font-display text-lg tracking-tight">Aura Lingua</span>
            </div>

            <!-- Kartu kosakata: elemen hero yang jadi ciri khas halaman ini -->
            <div class="relative my-12 md:my-0">
                <h1 class="font-display text-5xl md:text-6xl leading-none">Mulai</h1>
                <div class="flex items-center gap-3 mt-5">
                    <span class="h-px w-8 bg-[#E8A33D]"></span>
                    <p class="text-xl md:text-2xl font-display italic text-[#E8A33D]">Begin</p>
                </div>
            </div>

            <!-- Keterangan singkat, nada bicara biasa -->
            <p class="relative text-sm text-white/60 leading-relaxed max-w-xs">
                Buat akun pengajar untuk mulai menyiapkan materi dan kuis murid SMK.
            </p>
        </div>

        <!-- ============ SISI KANAN: Form Registrasi ============ -->
        <div class="flex items-center justify-center px-6 py-12 md:px-16">
            <div class="w-full max-w-sm card-enter">

                <div class="mb-8">
                    <h2 class="font-display text-2xl text-[#221C2E]">Daftar akun pengajar</h2>
                    <p class="text-sm text-[#6B6577] mt-1.5">Isi data di bawah untuk membuat akun baru.</p>
                </div>

                <!-- Pesan Error Validasi -->
                @if($errors->any())
                    <div class="mb-5 p-3.5 bg-rose-50 text-rose-700 border border-rose-100 rounded-xl text-xs font-medium flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 shrink-0"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <!-- Form Registrasi -->
                <form action="{{ route('register') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="nama" class="block text-sm font-medium text-[#221C2E] mb-1.5">Nama lengkap</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9792A3]">
                                <i class="fa-regular fa-user text-sm"></i>
                            </span>
                            <input type="text" id="nama" name="nama" value="{{ old('nama') }}" required placeholder="Nama lengkap beserta gelar"
                                class="w-full pl-11 pr-4 py-3 bg-white border border-[#E7E4ED] rounded-xl text-sm text-[#221C2E] placeholder:text-[#B3AEC0] focus:outline-none focus:ring-2 focus:ring-[#2E1A3D] transition">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-[#221C2E] mb-1.5">Alamat email</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9792A3]">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </span>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="nama@sekolah.com"
                                class="w-full pl-11 pr-4 py-3 bg-white border border-[#E7E4ED] rounded-xl text-sm text-[#221C2E] placeholder:text-[#B3AEC0] focus:outline-none focus:ring-2 focus:ring-[#2E1A3D] transition">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-[#221C2E] mb-1.5">Kata sandi</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9792A3]">
                                <i class="fa-solid fa-lock text-sm"></i>
                            </span>
                            <input type="password" id="password" name="password" required placeholder="Minimal 6 karakter"
                                class="w-full pl-11 pr-11 py-3 bg-white border border-[#E7E4ED] rounded-xl text-sm text-[#221C2E] placeholder:text-[#B3AEC0] focus:outline-none focus:ring-2 focus:ring-[#2E1A3D] transition">
                            <button type="button" onclick="togglePassword()" aria-label="Tampilkan kata sandi"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#9792A3] hover:text-[#221C2E]">
                                <i id="toggleIcon" class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-[#2E1A3D] hover:bg-[#241530] text-white font-semibold py-3.5 px-4 rounded-xl text-sm transition flex items-center justify-center gap-2 mt-2">
                        <span>Daftar sekarang</span>
                        <i class="fa-solid fa-user-plus text-xs"></i>
                    </button>
                </form>

                <p class="mt-6 text-sm text-[#6B6577]">
                    Sudah punya akun pengajar?
                    <a href="{{ route('login') }}" class="text-[#2E1A3D] font-semibold hover:underline ml-1">Masuk di sini</a>
                </p>
            </div>
        </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
        }
    </script>
</body>
</html>