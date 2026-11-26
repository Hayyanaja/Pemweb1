<?php
require __DIR__ . '/config.php';
wajib_login();

$username = $_SESSION['uname'] ?? '';
$konfig = [
    'csrf'     => csrf_token(),
    'username' => $username,
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TugasKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<section id="app">
  <div class="topbar">
    <h2>TugasKu</h2>
    <div class="user">
      <div class="bel-wrap">
        <button type="button" class="btn-garis btn-bel" id="btnBel" aria-label="Pengingat">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="badge-bel hidden" id="badgeBel">0</span>
        </button>
        <div class="popover hidden" id="popBel">
          <div class="pop-head"><b>Pengingat</b><button type="button" class="btn-garis kecil" id="btnAtur">Atur</button></div>
          <div id="listBel"></div>
        </div>
      </div>
      <span>Halo, <b><?= e($username) ?></b></span>
      <form method="post" action="logout.php" class="logout-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn-garis">Keluar</button>
      </form>
    </div>
  </div>

  <div class="wrap">
    <div class="layout">

      <div>
        <div class="stats">
          <div class="stat"><small>Total Tugas</small><b id="sTotal">0</b></div>
          <div class="stat"><small>Pending</small><b id="sPending">0</b></div>
          <div class="stat aksen"><small>Selesai</small><b id="sSelesai">0</b></div>
          <div class="stat telat"><small>Terlambat</small><b id="sTelat">0</b></div>
        </div>

        <div class="progres">
          <div class="progres-info"><span>Progres penyelesaian</span><span id="persen">0%</span></div>
          <div class="bar"><div id="barIsi"></div></div>
        </div>

        <div class="toolbar">
          <input id="cari" type="search" placeholder="Cari judul / mata kuliah...">
          <select id="fStatus">
            <option value="">Semua status</option>
            <option value="pending">Pending</option>
            <option value="selesai">Selesai</option>
          </select>
          <select id="fPrio">
            <option value="">Semua prioritas</option>
            <option>High</option><option>Medium</option><option>Low</option>
          </select>
          <select id="fMk"></select>
          <select id="urut">
            <option value="deadline">Urut: Deadline terdekat</option>
            <option value="prioritas">Urut: Prioritas</option>
            <option value="terbaru">Urut: Terbaru</option>
          </select>
        </div>

        <div class="toolbar2">
          <div class="seg">
            <button type="button" id="segKartu" class="on">Kartu</button>
            <button type="button" id="segTabel">Tabel</button>
          </div>
          <div class="aksi-kanan">
            <button type="button" class="btn-garis" id="btnMk">Mata Kuliah</button>
            <button type="button" class="btn-garis" id="btnSampah">Sampah (0)</button>
            <button type="button" class="btn-garis" id="btnCSV">Ekspor CSV</button>
            <button type="button" class="btn-utama" id="btnTambah">+ Tambah Tugas</button>
          </div>
        </div>

        <div class="grid" id="daftar"></div>
        <div class="kosong hidden" id="kosong">Tidak ada tugas yang cocok.</div>
      </div>

      <aside class="side">
        <div class="kal">
          <div class="kal-head">
            <button type="button" class="nav" id="kalPrev" aria-label="Bulan sebelumnya">‹</button>
            <b class="kal-judul" id="kalJudul"></b>
            <button type="button" class="nav" id="kalNext" aria-label="Bulan berikutnya">›</button>
          </div>
          <div class="kal-grid" id="kalGrid"></div>
          <div class="legend">
            <span><i class="dot High"></i>High</span>
            <span><i class="dot Medium"></i>Medium</span>
            <span><i class="dot Low"></i>Low</span>
            <span><i class="dot telat"></i>Terlambat</span>
          </div>
          <div class="kal-aksi"><button type="button" class="btn-garis kecil" id="kalHariIni">Ke hari ini</button></div>
        </div>

        <div class="kal">
          <div class="agenda-head">
            <b id="agendaJudul"></b>
            <button type="button" class="btn-utama kecil hidden" id="btnTambahTgl">+ Tambah</button>
          </div>
          <div class="agenda" id="agenda"></div>
        </div>
      </aside>

    </div>
  </div>
</section>

<div class="overlay hidden" id="modalForm">
  <div class="modal" id="formTugas">
    <h3 id="judulModal">Tambah Tugas</h3>

    <label>Judul Tugas</label>
    <input id="fJudul" maxlength="60">

    <label>Mata Kuliah</label>
    <select id="fMatkul"></select>

    <label>Deskripsi Singkat</label>
    <textarea id="fDesk" rows="3" maxlength="1000"></textarea>

    <div class="dua-kolom">
      <div><label>Deadline</label><input id="fDeadline" type="date"></div>
      <div><label>Prioritas</label>
        <select id="fPrioritas"><option>Low</option><option selected>Medium</option><option>High</option></select>
      </div>
    </div>

    <div class="modal-aksi">
      <button type="button" class="btn-garis" id="batalForm">Batal</button>
      <button type="button" class="btn-utama" id="btnSimpan">Simpan</button>
    </div>
  </div>
</div>

<div class="overlay hidden" id="modalMk">
  <div class="modal lebar">
    <h3>Mata Kuliah</h3>
    <p class="catatan" style="margin-top:0">Atur daftar mata kuliah dan warnanya. Mata kuliah yang masih dipakai tugas tidak bisa dihapus.</p>
    <div class="list-scroll" id="listMk"></div>

    <h4 id="judulFormMk">Tambah mata kuliah</h4>
    <label>Nama</label>
    <input id="namaMk" maxlength="40" placeholder="Contoh: Pemrograman Web">
    <label>Warna</label>
    <div class="warna-pilih" id="swatch"></div>

    <div class="modal-aksi">
      <button type="button" class="btn-garis hidden" id="btnBatalMk">Batal edit</button>
      <button type="button" class="btn-utama" id="btnSimpanMk">Simpan</button>
    </div>
    <div class="modal-aksi" style="margin-top:8px">
      <button type="button" class="btn-garis" id="btnTutupMk">Tutup</button>
    </div>
  </div>
</div>

<div class="overlay hidden" id="modalSampah">
  <div class="modal lebar">
    <h3>Sampah</h3>
    <p class="catatan" style="margin-top:0">Tugas yang dihapus disimpan di sini. Pulihkan kalau terhapus tidak sengaja.</p>
    <div class="list-scroll" id="listSampah"></div>
    <div class="modal-aksi">
      <button type="button" class="btn-garis hapus-kecil hidden" id="btnKosongkan">Kosongkan sampah</button>
      <button type="button" class="btn-utama" id="btnTutupSampah">Tutup</button>
    </div>
  </div>
</div>

<div class="overlay hidden" id="modalHapus">
  <div class="modal">
    <h3 id="judulTanya">Hapus permanen?</h3>
    <p style="color:var(--redup)" id="teksHapus"></p>
    <div class="modal-aksi">
      <button type="button" class="btn-garis" id="batalHapus">Batal</button>
      <button type="button" class="btn-utama" id="yaHapus" style="background:var(--bahaya)">Ya, hapus</button>
    </div>
  </div>
</div>

<div class="overlay hidden" id="modalBel">
  <div class="modal">
    <h3>Pengaturan Pengingat</h3>

    <label class="sw" style="margin-top:14px"><input type="checkbox" id="swAktif"> Aktifkan pengingat tugas</label>

    <label>Ingatkan pada (boleh pilih lebih dari satu)</label>
    <div class="chips" id="chips"></div>
    <p class="catatan">Tugas yang sudah lewat deadline dan belum selesai selalu muncul sebagai pengingat.</p>

    <label>Notifikasi browser</label>
    <div class="baris-izin">
      <span>Status: <b id="statusIzin">-</b></span>
      <button type="button" class="btn-garis kecil" id="btnIzin">Izinkan</button>
    </div>

    <div class="modal-aksi">
      <button type="button" class="btn-garis" id="btnTes">Tes notifikasi</button>
      <button type="button" class="btn-utama" id="btnTutupBel">Selesai</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>window.TUGASKU = <?= json_encode($konfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/app.js"></script>
</body>
</html>
