(function () {
  "use strict";

  const CSRF = window.TUGASKU.csrf;
  const $ = id => document.getElementById(id);

  const pad = n => String(n).padStart(2, "0");
  const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const parse = s => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
  const hariIni = new Date(); hariIni.setHours(0, 0, 0, 0);
  const tgl = n => { const d = new Date(hariIni); d.setDate(d.getDate() + n); return iso(d); };
  const selisih = s => Math.round((parse(s) - hariIni) / 86400000);
  const fmt = s => parse(s).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" });
  const NAMA_BULAN = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];

  const PALET_MK = ["#5BC0C0", "#7FB2E5", "#9B8FE0", "#E3C07B", "#7DD3A8", "#E09AB0", "#E8A27C", "#B7C4D6"];
  let tugas = [], matkul = [];
  let setting = { aktif: true, hari: [0, 1, 3] };
  let idEdit = null, tampilan = "kartu", sudahSambut = false;
  const bobot = { High: 0, Medium: 1, Low: 2 };
  const aktif = () => tugas.filter(t => !t.dihapus);
  const mk = id => matkul.find(m => m.id === id) || { id: 0, nama: "Umum", warna: "#93A4B8" };
  const pakaiMk = id => tugas.filter(t => t.mk === id).length;
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;" }[c]));
  const tagMk = t => { const m = mk(t.mk); return `<span class="tag"><i class="titik" style="background:${esc(m.warna)}"></i>${esc(m.nama)}</span>`; };

  async function api(aksi, body) {
    const opsi = { credentials: "same-origin", headers: { "X-CSRF-Token": CSRF } };
    if (body !== undefined) {
      opsi.method = "POST";
      opsi.headers["Content-Type"] = "application/json";
      opsi.body = JSON.stringify(body);
    }
    let res;
    try { res = await fetch("api.php?action=" + aksi, opsi); }
    catch (e) { throw new Error("Tidak bisa terhubung ke server."); }
    if (res.status === 401) { location.href = "login.php"; throw new Error("Sesi berakhir."); }
    let data;
    try { data = await res.json(); } catch (e) { throw new Error("Respons server tidak valid."); }
    if (!data.ok) throw new Error(data.error || "Terjadi kesalahan.");
    return data;
  }
  async function muat() {
    const d = await api("init");
    tugas = d.tugas; matkul = d.matkul; setting = d.pengaturan;
    renderMkSelect(); render();
  }
  async function jalankan(aksi, body, pesan, labelUndo, fnUndo) {
    try {
      const d = await api(aksi, body);
      await muat();
      if (pesan) toast(pesan, labelUndo, fnUndo);
      return d;
    } catch (e) { toast(e.message); return null; }
  }

  let timerToast;
  function toast(msg, labelAksi, fn) {
    const t = $("toast"); t.innerHTML = "<span></span>"; t.firstChild.textContent = msg;
    if (labelAksi) {
      const b = document.createElement("button");
      b.type = "button"; b.className = "toast-btn"; b.textContent = labelAksi;
      b.onclick = () => { fn(); t.classList.remove("tampil"); };
      t.appendChild(b);
    }
    t.classList.add("tampil");
    clearTimeout(timerToast); timerToast = setTimeout(() => t.classList.remove("tampil"), labelAksi ? 6000 : 2800);
  }
  let aksiTanya = null;
  function tanya(judul, teks, fn) {
    $("judulTanya").textContent = judul; $("teksHapus").textContent = teks; aksiTanya = fn;
    $("modalHapus").classList.remove("hidden");
  }
  $("batalHapus").onclick = () => $("modalHapus").classList.add("hidden");
  $("yaHapus").onclick = () => { $("modalHapus").classList.add("hidden"); if (aksiTanya) aksiTanya(); aksiTanya = null; };

  function labelDeadline(t) {
    if (t.selesai) return `<span class="tag ok">Selesai</span>`;
    const s = selisih(t.deadline);
    if (s < 0) return `<span class="tag telat">Terlambat ${-s} hari</span>`;
    if (s === 0) return `<span class="tag telat">Hari ini!</span>`;
    return `<span class="tag">${s} hari lagi</span>`;
  }

  function kartuHTML(t) {
    return `
      <div class="card ${t.prioritas} ${t.selesai ? "selesai" : ""}" data-id="${t.id}">
        <div class="baris">
          <span class="tag ${t.prioritas}">${t.prioritas}</span>
          ${tagMk(t)}
          ${labelDeadline(t)}
        </div>
        <h3>${esc(t.judul)}</h3>
        <p>${esc(t.deskripsi || "-")}</p>
        <div class="baris"><span class="tag">Deadline: ${fmt(t.deadline)}</span></div>
        <div class="aksi">
          <button type="button" class="cek" data-act="cek">${t.selesai ? "Batalkan" : "✔ Selesai"}</button>
          <button type="button" class="edit" data-act="edit">Edit</button>
          <button type="button" class="hapus" data-act="hapus">Hapus</button>
        </div>
      </div>`;
  }
  function barisHTML(t) {
    return `
      <tr class="${t.selesai ? "selesai" : ""}" data-id="${t.id}">
        <td><button type="button" class="ag-cek" data-act="cek" title="Tandai selesai">${t.selesai ? "✔" : ""}</button></td>
        <td class="td-judul"><b>${esc(t.judul)}</b><small>${esc(t.deskripsi || "-")}</small></td>
        <td>${tagMk(t)}</td>
        <td>${fmt(t.deadline)}</td>
        <td><span class="tag ${t.prioritas}">${t.prioritas}</span></td>
        <td>${labelDeadline(t)}</td>
        <td><div class="aksi sebaris">
          <button type="button" class="edit" data-act="edit">Edit</button>
          <button type="button" class="hapus" data-act="hapus">Hapus</button>
        </div></td>
      </tr>`;
  }

  function render() {
    const A = aktif();
    const q = $("cari").value.toLowerCase(), fs = $("fStatus").value, fp = $("fPrio").value, fm = $("fMk").value, u = $("urut").value;
    const list = A.filter(t =>
      (t.judul + " " + mk(t.mk).nama).toLowerCase().includes(q) &&
      (!fs || (fs === "selesai") === t.selesai) &&
      (!fp || t.prioritas === fp) &&
      (!fm || String(t.mk) === fm));

    if (u === "deadline") list.sort((a, b) => a.deadline.localeCompare(b.deadline));
    if (u === "prioritas") list.sort((a, b) => bobot[a.prioritas] - bobot[b.prioritas]);
    if (u === "terbaru") list.sort((a, b) => b.id - a.id);

    if (tampilan === "kartu") {
      $("daftar").className = "grid";
      $("daftar").innerHTML = list.map(kartuHTML).join("");
    } else {
      $("daftar").className = "tabel-wrap";
      $("daftar").innerHTML = list.length ? `
        <table class="tabel">
          <thead><tr><th></th><th>Tugas</th><th>Mata Kuliah</th><th>Deadline</th><th>Prioritas</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>${list.map(barisHTML).join("")}</tbody>
        </table>` : "";
    }
    $("kosong").textContent = A.length ? "Tidak ada tugas yang cocok." : "Belum ada tugas. Klik \"+ Tambah Tugas\" untuk mulai.";
    $("kosong").classList.toggle("hidden", list.length > 0);

    const total = A.length, sel = A.filter(t => t.selesai).length;
    const telat = A.filter(t => !t.selesai && selisih(t.deadline) < 0).length;
    const persen = total ? Math.round(sel / total * 100) : 0;
    $("sTotal").textContent = total; $("sSelesai").textContent = sel;
    $("sPending").textContent = total - sel; $("sTelat").textContent = telat;
    $("persen").textContent = persen + "%"; $("barIsi").style.width = persen + "%";

    renderKal(); renderBel(); renderSampah();
    if (!$("modalMk").classList.contains("hidden")) renderMkModal();
  }
  ["cari", "fStatus", "fPrio", "fMk", "urut"].forEach(id => $(id).addEventListener("input", render));

  function renderMkSelect() {
    const v = $("fMk").value;
    $("fMk").innerHTML = `<option value="">Semua mata kuliah</option>` + matkul.map(m => `<option value="${m.id}">${esc(m.nama)}</option>`).join("");
    $("fMk").value = matkul.some(m => String(m.id) === v) ? v : "";
  }

  function setTampilan(v) {
    tampilan = v;
    $("segKartu").classList.toggle("on", v === "kartu"); $("segTabel").classList.toggle("on", v === "tabel");
    render();
  }
  $("segKartu").onclick = () => setTampilan("kartu");
  $("segTabel").onclick = () => setTampilan("tabel");

  $("daftar").onclick = e => {
    const btn = e.target.closest("button"); if (!btn || !btn.dataset.act) return;
    const id = +btn.closest("[data-id]").dataset.id;
    const t = tugas.find(x => x.id === id); if (!t) return;
    if (btn.dataset.act === "cek") {
      jalankan("task_toggle", { id }, t.selesai ? "Status dikembalikan ke pending" : "Tugas ditandai selesai");
    }
    if (btn.dataset.act === "edit") bukaForm(t);
    if (btn.dataset.act === "hapus") {
      jalankan("task_trash", { id }, "Tugas dipindahkan ke sampah", "Urungkan", () => jalankan("task_restore", { id }, "Tugas dipulihkan"));
    }
  };

  function renderSampah() {
    const s = tugas.filter(t => t.dihapus);
    $("btnSampah").textContent = `Sampah (${s.length})`;
    $("listSampah").innerHTML = s.length ? s.map(t => `
      <div class="ag" style="cursor:default" data-id="${t.id}">
        <div class="ag-isi"><b>${esc(t.judul)}</b><small>${esc(mk(t.mk).nama)} · Deadline ${fmt(t.deadline)}</small></div>
        <button type="button" class="btn-utama kecil" data-act="pulih">Pulihkan</button>
        <button type="button" class="btn-garis kecil hapus-kecil" data-act="permanen">Hapus</button>
      </div>`).join("") : `<div class="kosong-kecil">Sampah kosong.</div>`;
    $("btnKosongkan").classList.toggle("hidden", !s.length);
  }
  $("btnSampah").onclick = () => $("modalSampah").classList.remove("hidden");
  $("btnTutupSampah").onclick = () => $("modalSampah").classList.add("hidden");
  $("listSampah").onclick = e => {
    const btn = e.target.closest("button"); if (!btn) return;
    const id = +btn.closest("[data-id]").dataset.id;
    const t = tugas.find(x => x.id === id); if (!t) return;
    if (btn.dataset.act === "pulih") jalankan("task_restore", { id }, "Tugas dipulihkan");
    if (btn.dataset.act === "permanen") {
      tanya("Hapus permanen?", `"${t.judul}" tidak bisa dikembalikan.`, () => jalankan("task_delete", { id }, "Tugas dihapus permanen"));
    }
  };
  $("btnKosongkan").onclick = () => {
    const n = tugas.filter(t => t.dihapus).length;
    tanya("Kosongkan sampah?", `${n} tugas akan dihapus permanen.`, () => jalankan("task_empty", {}, "Sampah dikosongkan"));
  };

  function buatCSV() {
    const baris = [["Judul", "Mata Kuliah", "Deskripsi", "Deadline", "Prioritas", "Status"]];
    aktif().sort((a, b) => a.deadline.localeCompare(b.deadline)).forEach(t =>
      baris.push([t.judul, mk(t.mk).nama, t.deskripsi || "", t.deadline, t.prioritas, t.selesai ? "Selesai" : "Pending"]));
    // awalan ' mencegah sel diartikan sebagai rumus di Excel
    const aman = c => { c = String(c); if (/^[=+\-@]/.test(c)) c = "'" + c; return `"${c.replace(/"/g, '""')}"`; };
    return "﻿" + baris.map(r => r.map(aman).join(",")).join("\r\n");
  }
  $("btnCSV").onclick = () => {
    if (!aktif().length) { toast("Belum ada tugas untuk diekspor"); return; }
    try {
      const url = URL.createObjectURL(new Blob([buatCSV()], { type: "text/csv;charset=utf-8" }));
      const a = document.createElement("a"); a.href = url; a.download = `tugasku-${iso(hariIni)}.csv`;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      toast("File CSV diunduh");
    } catch (e) { toast("Unduhan tidak bisa dijalankan di sini"); }
  };

  let idMkEdit = null, warnaMk = PALET_MK[0];
  function resetFormMk() { idMkEdit = null; $("namaMk").value = ""; warnaMk = PALET_MK[matkul.length % PALET_MK.length]; }
  function renderMkModal() {
    $("listMk").innerHTML = matkul.length ? matkul.map(m => `
      <div class="mk-row" data-id="${m.id}">
        <i class="titik besar" style="background:${esc(m.warna)}"></i>
        <div class="mk-nama">${esc(m.nama)}<small>${pakaiMk(m.id)} tugas</small></div>
        <button type="button" class="btn-garis kecil" data-act="edit">Edit</button>
        <button type="button" class="btn-garis kecil hapus-kecil" data-act="hapus">Hapus</button>
      </div>`).join("") : `<div class="kosong-kecil">Belum ada mata kuliah.</div>`;
    $("swatch").innerHTML = PALET_MK.map(w =>
      `<button type="button" class="sw-warna ${w === warnaMk ? "on" : ""}" data-w="${w}" style="background:${w}" aria-label="Warna ${w}"></button>`).join("");
    $("judulFormMk").textContent = idMkEdit ? "Edit mata kuliah" : "Tambah mata kuliah";
    $("btnBatalMk").classList.toggle("hidden", !idMkEdit);
  }
  $("btnMk").onclick = () => { resetFormMk(); renderMkModal(); $("modalMk").classList.remove("hidden"); };
  $("btnTutupMk").onclick = () => $("modalMk").classList.add("hidden");
  $("btnBatalMk").onclick = () => { resetFormMk(); renderMkModal(); };
  $("swatch").onclick = e => { const b = e.target.closest(".sw-warna"); if (!b) return; warnaMk = b.dataset.w; renderMkModal(); };
  $("listMk").onclick = async e => {
    const btn = e.target.closest("button"); if (!btn) return;
    const id = +btn.closest("[data-id]").dataset.id, m = matkul.find(x => x.id === id); if (!m) return;
    if (btn.dataset.act === "edit") { idMkEdit = id; $("namaMk").value = m.nama; warnaMk = m.warna; renderMkModal(); $("namaMk").focus(); }
    if (btn.dataset.act === "hapus") {
      const n = pakaiMk(id);
      if (n > 0) { toast(`Tidak bisa dihapus: masih dipakai ${n} tugas`); return; }
      if (await jalankan("mk_delete", { id }, "Mata kuliah dihapus")) { resetFormMk(); renderMkModal(); }
    }
  };
  $("btnSimpanMk").onclick = async () => {
    const nama = $("namaMk").value.trim();
    if (!nama) { toast("Nama mata kuliah wajib diisi"); return; }
    const ok = await jalankan("mk_save", { id: idMkEdit, nama, warna: warnaMk }, idMkEdit ? "Mata kuliah diperbarui" : "Mata kuliah ditambahkan");
    if (ok) { resetFormMk(); renderMkModal(); }
  };

  let calTahun = hariIni.getFullYear(), calBulan = hariIni.getMonth(), tglDipilih = null;

  function renderKal() {
    const A = aktif();
    $("kalJudul").textContent = NAMA_BULAN[calBulan] + " " + calTahun;
    const offset = (new Date(calTahun, calBulan, 1).getDay() + 6) % 7; // minggu dimulai Senin
    const jmlHari = new Date(calTahun, calBulan + 1, 0).getDate();
    const hariIniStr = iso(hariIni);

    let h = ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"].map(n => `<div class="hr">${n}</div>`).join("");
    for (let i = 0; i < offset; i++) h += `<div></div>`;
    for (let d = 1; d <= jmlHari; d++) {
      const s = `${calTahun}-${pad(calBulan + 1)}-${pad(d)}`;
      const list = A.filter(t => t.deadline === s);
      const dots = list.slice(0, 3).map(t =>
        `<i class="dot ${t.selesai ? "done" : (selisih(t.deadline) < 0 ? "telat" : t.prioritas)}"></i>`).join("");
      const lebih = list.length > 3 ? `<small>+${list.length - 3}</small>` : "";
      h += `<button type="button" class="hari ${s === hariIniStr ? "today" : ""} ${s === tglDipilih ? "dipilih" : ""}" data-tgl="${s}">
              <span>${d}</span><div class="dots">${dots}${lebih}</div></button>`;
    }
    $("kalGrid").innerHTML = h;

    const prefix = `${calTahun}-${pad(calBulan + 1)}-`;
    const daftar = (tglDipilih ? A.filter(t => t.deadline === tglDipilih) : A.filter(t => t.deadline.startsWith(prefix)))
      .sort((a, b) => a.deadline.localeCompare(b.deadline));
    $("agendaJudul").textContent = tglDipilih ? "Tugas " + fmt(tglDipilih) : "Tugas bulan ini";
    $("btnTambahTgl").classList.toggle("hidden", !tglDipilih);
    $("agenda").innerHTML = daftar.length ? daftar.map(t => `
      <div class="ag ${t.selesai ? "selesai" : ""}" data-id="${t.id}">
        <button type="button" class="ag-cek" title="Tandai selesai">${t.selesai ? "✔" : ""}</button>
        <div class="ag-isi"><b>${esc(t.judul)}</b><small>${tglDipilih ? "" : fmt(t.deadline) + " · "}${esc(mk(t.mk).nama)}</small></div>
        <span class="tag ${t.prioritas}">${t.prioritas}</span>
      </div>`).join("") : `<div class="kosong-kecil">Tidak ada tugas.</div>`;
  }

  function geserBulan(n) {
    calBulan += n;
    if (calBulan < 0) { calBulan = 11; calTahun--; }
    if (calBulan > 11) { calBulan = 0; calTahun++; }
    tglDipilih = null; renderKal();
  }
  $("kalPrev").onclick = () => geserBulan(-1);
  $("kalNext").onclick = () => geserBulan(1);
  $("kalHariIni").onclick = () => {
    calTahun = hariIni.getFullYear(); calBulan = hariIni.getMonth(); tglDipilih = iso(hariIni); renderKal();
  };
  $("kalGrid").onclick = e => {
    const b = e.target.closest(".hari"); if (!b) return;
    tglDipilih = tglDipilih === b.dataset.tgl ? null : b.dataset.tgl; renderKal();
  };
  $("agenda").onclick = e => {
    const row = e.target.closest(".ag"); if (!row) return;
    const id = +row.dataset.id, t = tugas.find(x => x.id === id); if (!t) return;
    if (e.target.closest(".ag-cek")) jalankan("task_toggle", { id }, t.selesai ? "Status dikembalikan ke pending" : "Tugas ditandai selesai");
    else bukaForm(t);
  };
  $("btnTambahTgl").onclick = () => bukaForm(null, tglDipilih);

  const PILIHAN_H = [0, 1, 2, 3, 5, 7];
  const sudahNotif = new Set();

  function hitungPengingat() {
    if (!setting.aktif) return [];
    return aktif().filter(t => !t.selesai)
      .map(t => ({ t, s: selisih(t.deadline) }))
      .filter(x => x.s < 0 || setting.hari.includes(x.s))
      .sort((a, b) => a.s - b.s);
  }
  function labelH(s) {
    if (s < 0) return `Terlambat ${-s} hari`;
    if (s === 0) return "Hari ini (H-0)";
    return `H-${s} · ${s === 1 ? "besok" : s + " hari lagi"}`;
  }
  function renderBel() {
    const p = hitungPengingat();
    $("badgeBel").textContent = p.length; $("badgeBel").classList.toggle("hidden", p.length === 0);
    $("listBel").innerHTML = !setting.aktif
      ? `<div class="kosong-kecil">Pengingat sedang dimatikan.</div>`
      : p.length ? p.map(x => `<div class="bel-item ${x.s < 0 ? "telat" : ""}"><b>${esc(x.t.judul)}</b><small>${labelH(x.s)} · ${esc(mk(x.t.mk).nama)}</small></div>`).join("")
      : `<div class="kosong-kecil">Tidak ada pengingat saat ini.</div>`;
  }
  function kirimBrowser(judul, isi) {
    try { if ("Notification" in window && Notification.permission === "granted") new Notification(judul, { body: isi }); } catch (e) {}
  }
  function cekPengingat() {
    const p = hitungPengingat();
    if (!p.length) return;
    toast(`${p.length} tugas perlu perhatianmu. Cek ikon lonceng.`);
    p.forEach(x => {
      const k = x.t.id + "_" + x.s;
      if (!sudahNotif.has(k)) { sudahNotif.add(k); kirimBrowser("TugasKu: " + x.t.judul, labelH(x.s)); }
    });
  }
  function simpanSetting() {
    api("settings_save", { aktif: setting.aktif, hari: setting.hari }).catch(e => toast(e.message));
  }

  $("btnBel").onclick = e => { e.stopPropagation(); $("popBel").classList.toggle("hidden"); };
  document.addEventListener("click", e => { if (!e.target.closest(".bel-wrap")) $("popBel").classList.add("hidden"); });

  function statusIzin() {
    let teks = "Belum diizinkan";
    try {
      if (!("Notification" in window)) teks = "Tidak didukung";
      else if (Notification.permission === "granted") teks = "Aktif";
      else if (Notification.permission === "denied") teks = "Diblokir";
    } catch (e) { teks = "Tidak tersedia"; }
    $("statusIzin").textContent = teks;
  }
  function renderChips() {
    $("swAktif").checked = setting.aktif;
    $("chips").innerHTML = PILIHAN_H.map(h =>
      `<button type="button" class="chip ${setting.hari.includes(h) ? "on" : ""}" data-h="${h}">${h === 0 ? "Hari-H" : "H-" + h}</button>`).join("");
  }
  $("btnAtur").onclick = () => { $("popBel").classList.add("hidden"); renderChips(); statusIzin(); $("modalBel").classList.remove("hidden"); };
  $("btnTutupBel").onclick = () => { $("modalBel").classList.add("hidden"); toast("Pengaturan pengingat disimpan"); };
  $("swAktif").onchange = () => { setting.aktif = $("swAktif").checked; simpanSetting(); renderBel(); };
  $("chips").onclick = e => {
    const b = e.target.closest(".chip"); if (!b) return;
    const h = +b.dataset.h;
    setting.hari = setting.hari.includes(h) ? setting.hari.filter(x => x !== h) : [...setting.hari, h].sort((a, b) => a - b);
    simpanSetting(); renderChips(); renderBel();
  };
  $("btnIzin").onclick = async () => {
    try {
      if (!("Notification" in window)) { toast("Browser ini tidak mendukung notifikasi"); return; }
      const r = await Notification.requestPermission(); statusIzin();
      toast(r === "granted" ? "Notifikasi browser diaktifkan" : "Izin notifikasi tidak diberikan");
    } catch (e) { toast("Notifikasi tidak bisa diaktifkan di sini"); }
  };
  $("btnTes").onclick = () => { toast("Ini contoh pengingat dari TugasKu"); kirimBrowser("TugasKu", "Ini contoh pengingat tugas"); };

  function bukaForm(t, tglAwal) {
    idEdit = t ? t.id : null;
    $("judulModal").textContent = t ? "Edit Tugas" : "Tambah Tugas";
    $("fMatkul").innerHTML = `<option value="">Tanpa mata kuliah</option>` + matkul.map(m => `<option value="${m.id}">${esc(m.nama)}</option>`).join("");
    $("fMatkul").value = t ? (t.mk ? String(t.mk) : "") : $("fMk").value;
    $("fJudul").value = t ? t.judul : "";
    $("fDesk").value = t ? t.deskripsi : ""; $("fDeadline").value = t ? t.deadline : (tglAwal || tgl(3));
    $("fPrioritas").value = t ? t.prioritas : "Medium";
    $("modalForm").classList.remove("hidden"); $("fJudul").focus();
  }
  $("btnTambah").onclick = () => bukaForm(null);
  $("batalForm").onclick = () => $("modalForm").classList.add("hidden");
  $("btnSimpan").onclick = async () => {
    if (!$("fJudul").value.trim() || !$("fDeadline").value) { toast("Judul dan deadline wajib diisi"); return; }
    const data = {
      id: idEdit, judul: $("fJudul").value.trim(), matkul_id: $("fMatkul").value ? +$("fMatkul").value : null,
      deskripsi: $("fDesk").value.trim(), deadline: $("fDeadline").value, prioritas: $("fPrioritas").value
    };
    const ok = await jalankan("task_save", data, idEdit ? "Tugas berhasil diperbarui" : "Tugas berhasil ditambahkan");
    if (ok) $("modalForm").classList.add("hidden");
  };

  muat().then(() => { if (!sudahSambut) { sudahSambut = true; cekPengingat(); } })
    .catch(e => toast(e.message));

  // dipakai oleh pengujian
  window.__tugasku = { buatCSV, hitungPengingat };
})();
