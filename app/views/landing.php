<?php
/** @var string $base */
/** @var string|null $prefillSlug */
$prefillSlug = $prefillSlug ?? '';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notepad Share</title>
  <style>
    :root{--bg-0:#0a0a0f;--bg-1:#12121a;--border:#2a2a3a;--text-0:#f0f0f5;--text-1:#b0b0c0;--text-2:#707080;--accent:#6366f1;--danger:#ef4444}
    *{box-sizing:border-box} body{margin:0;min-height:100vh;font-family:Inter,Segoe UI,sans-serif;background:var(--bg-0);color:var(--text-0);display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(440px,100%);background:var(--bg-1);border:1px solid var(--border);border-radius:20px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
    .mark{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#a855f7);display:grid;place-items:center;font-weight:800;margin-bottom:14px}
    h1{margin:0 0 6px;font-size:22px} p{color:var(--text-1);line-height:1.55;font-size:14px}
    label{display:block;font-size:12px;color:var(--text-2);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
    input,select{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:#0a0a0f;color:var(--text-0);font-size:15px}
    button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:10px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer;font-size:15px}
    button.secondary{background:transparent;border:1px solid var(--border);color:var(--text-1);margin-top:8px}
    .hint{margin-top:16px;font-size:13px;color:var(--text-2)}
    .err{margin-top:12px;color:var(--danger);font-size:13px;display:none}
    .step{display:none} .step.active{display:block}
  </style>
</head>
<body>
  <div class="card">
    <div class="mark">NS</div>
    <h1>Notepad Share</h1>
    <p>Kolaborasi Markdown atau teks pada satu URL. Tanpa akun — identitas dari session.</p>

    <div id="step-slug" class="step active">
      <form id="form-slug">
        <label for="slug">URL kustom</label>
        <input id="slug" name="slug" placeholder="rapat-tim" required value="<?php echo htmlspecialchars($prefillSlug, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">Lanjut</button>
      </form>
    </div>

    <div id="step-retention" class="step">
      <form id="form-retention">
        <label for="retention">Lama history / sesi</label>
        <select id="retention">
          <option value="3600000">1 jam</option>
          <option value="21600000">6 jam</option>
          <option value="43200000">12 jam</option>
          <option value="86400000" selected>1 hari</option>
          <option value="259200000">3 hari</option>
          <option value="604800000">7 hari</option>
          <option value="0">Permanen</option>
        </select>
        <p class="hint">Setelah waktu ini ruang dihapus otomatis dan URL yang sama bisa dibuat ulang. Ruang dibuat terbuka; kunci dan password diatur di editor.</p>
        <button type="submit">Buat ruang</button>
        <button type="button" class="secondary" id="back-ret">Kembali</button>
      </form>
    </div>

    <div id="step-unlock" class="step">
      <form id="form-unlock">
        <label for="unlock-pass">Password ruang</label>
        <input id="unlock-pass" type="password" autocomplete="current-password" required>
        <p class="hint">Ruang ini terkunci. Masukkan password dari pembuat ruang.</p>
        <button type="submit">Masuk</button>
        <button type="button" class="secondary" id="back-unlock">Kembali</button>
      </form>
    </div>

    <div id="step-forbidden" class="step">
      <p>Ruang dikunci dan tidak dapat diakses. Pembuat tidak memasang password, jadi hanya session pembuat yang boleh masuk.</p>
      <button type="button" class="secondary" id="back-forb">Kembali</button>
    </div>

    <p class="err" id="err"></p>
    <p class="hint" id="home-hint">Contoh: <?php echo $base; ?>/n/rapat-tim — share tautan yang sama ke rekan.</p>
  </div>
  <script>
    const BASE = <?php echo json_encode($base); ?>;
    const prefill = <?php echo json_encode($prefillSlug); ?>;
    let csrf = '';
    let slug = (prefill || '').toLowerCase();

    const $ = (id) => document.getElementById(id);
    function show(id) {
      document.querySelectorAll('.step').forEach((s) => s.classList.remove('active'));
      $(id).classList.add('active');
      $('err').style.display = 'none';
    }
    function fail(msg) {
      $('err').textContent = msg;
      $('err').style.display = 'block';
    }
    async function api(path, opts = {}) {
      const headers = { 'Accept': 'application/json' };
      if (opts.json) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-Token'] = csrf;
      }
      const r = await fetch(BASE + path, {
        method: opts.method || 'GET',
        headers,
        credentials: 'same-origin',
        body: opts.json ? JSON.stringify(opts.json) : undefined,
      });
      const data = await r.json().catch(() => ({}));
      if (!r.ok) {
        const err = new Error(data.error?.message || r.statusText);
        err.status = r.status;
        throw err;
      }
      return data;
    }
    function goRoom() {
      location.href = BASE + '/n/' + encodeURIComponent(slug);
    }

    async function inspectSlug(value) {
      slug = value.trim().toLowerCase();
      if (!/^[a-z0-9][a-z0-9-]{1,62}$/.test(slug)) {
        fail('Slug tidak valid');
        return;
      }
      const meta = await api('/api/notes/' + encodeURIComponent(slug) + '/access');
      if (!meta.exists) {
        show('step-retention');
        return;
      }
      if (meta.is_owner || !meta.locked) {
        goRoom();
        return;
      }
      if (meta.has_password) {
        show('step-unlock');
        return;
      }
      show('step-forbidden');
    }

    $('form-slug').addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await inspectSlug($('slug').value); }
      catch (err) { fail(err.message); }
    });
    $('form-retention').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('/api/notes', {
          method: 'POST',
          json: {
            slug,
            retention_ms: parseInt($('retention').value, 10),
          },
        });
        goRoom();
      } catch (err) { fail(err.message); }
    });
    $('form-unlock').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('/api/notes/' + encodeURIComponent(slug) + '/unlock', {
          method: 'POST',
          json: { password: $('unlock-pass').value },
        });
        goRoom();
      } catch (err) { fail(err.message); }
    });
    $('back-ret').addEventListener('click', () => show('step-slug'));
    $('back-unlock').addEventListener('click', () => show('step-slug'));
    $('back-forb').addEventListener('click', () => show('step-slug'));

    (async () => {
      try {
        const s = await api('/api/session');
        csrf = s.csrf;
        if (prefill) await inspectSlug(prefill);
      } catch (err) { fail(err.message); }
    })();
  </script>
</body>
</html>
