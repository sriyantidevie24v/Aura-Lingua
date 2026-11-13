<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseAuthController extends Controller
{
    // Menampilkan halaman login
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // Menampilkan halaman register guru
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    // Memproses pendaftaran guru baru ke Firebase Auth & Firestore
    public function register(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        try {
            $auth = Firebase::auth();

            // 1. Buat akun di Firebase Authentication
            $createdUser = $auth->createUser([
                'email' => $request->email,
                'password' => $request->password,
                'displayName' => $request->nama,
            ]);
            
            $uid = $createdUser->uid;

            // 2. Akses database Firestore melalui instance database()
            $firestore = Firebase::firestore();
            $database = $firestore->database();

            // Simpan profil ke koleksi 'guru' di Firestore menggunakan UID Auth sebagai ID dokumen
            $database->collection('guru')->document($uid)->set([
                'nama' => $request->nama,
                'email' => $request->email,
                'dibuat_pada' => now()->toIso8601String(),
            ]);

            return redirect()->route('login')->with('success', 'Akun guru berhasil didaftarkan! Silakan login.');

        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Gagal mendaftar: ' . $e->getMessage()]);
        }
    }

    // Memproses login guru
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $auth = Firebase::auth();
            
            // Verifikasi kredensial ke Firebase Auth
            $signInResult = $auth->signInWithEmailAndPassword($request->email, $request->password);
            
            // Simpan sesi login guru
            session(['firebase_user_id' => $signInResult->firebaseUserId()]);
            session(['teacher_email' => $request->email]);

            return redirect()->route('guru.ringkasan');
            
        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Email atau password salah.']);
        }
    }

    // Logout dari sesi web
    public function logout(Request $request)
    {
        session()->forget(['firebase_user_id', 'teacher_email']);
        return redirect('/login');
    }
}