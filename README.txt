TUGASKU - Sistem Pencatat Tugas 

CARA PASANG (XAMPP)
1. Jalankan Apache dan MySQL dari XAMPP Control Panel.
2. Salin folder "tugasku" ke  C:\xampp\htdocs\   (jadi C:\xampp\htdocs\tugasku\)
3. Buka  http://localhost/phpmyadmin  -> tab "Import" -> pilih file  database.sql  -> klik "Import".
   (Database "tugasku" dan semua tabel dibuat otomatis, isinya kosong.)
4. Buka  config.php  dan cek DB_USER / DB_PASS. Bawaan XAMPP: root dan password kosong.
5. Buka  http://localhost/tugasku/  -> klik "Daftar" untuk membuat akun pertama.

STRUKTUR FILE
  database.sql     Skema MySQL: users, matkul, tasks, pengaturan
  config.php       Koneksi database, session aman, token CSRF
  login.php        Halaman masuk
  register.php     Halaman daftar akun baru
  logout.php       Keluar (POST + token CSRF)
  index.php        Halaman utama setelah login
  api.php          Semua operasi data (CRUD) dalam format JSON
  assets/style.css Tampilan (palet navy-teal)
  assets/app.js    Logika tampilan: kartu/tabel, kalender, pengingat, ekspor CSV

FITUR
  - Daftar, masuk, keluar (tiap akun hanya melihat datanya sendiri)
  - CRUD tugas: judul, deskripsi, deadline, prioritas (Low/Medium/High), status (Pending/Selesai)
  - Mata kuliah dengan warna (relasi ke tugas)
  - Tampilan kartu dan tabel, pencarian, filter, pengurutan
  - Kalender bulanan + agenda per tanggal
  - Pengingat H-n yang bisa diatur (tersimpan di database)
  - Sampah: hapus bisa diurungkan, pulihkan, hapus permanen
  - Ekspor CSV
