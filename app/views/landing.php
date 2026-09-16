<?php
/** @var string $base */
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notepad Share</title>
  <style>
    :root{--bg-0:#0a0a0f;--bg-1:#12121a;--border:#2a2a3a;--text-0:#f0f0f5;--text-1:#b0b0c0;--text-2:#707080;--accent:#6366f1}
    *{box-sizing:border-box} body{margin:0;min-height:100vh;font-family:Inter,Segoe UI,sans-serif;background:var(--bg-0);color:var(--text-0);display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(440px,100%);background:var(--bg-1);border:1px solid var(--border);border-radius:20px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
    .mark{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#a855f7);display:grid;place-items:center;font-weight:800;margin-bottom:14px}
    h1{margin:0 0 6px;font-size:22px} p{color:var(--text-1);line-height:1.55;font-size:14px}
    label{display:block;font-size:12px;color:var(--text-2);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
    input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:#0a0a0f;color:var(--text-0);font-size:15px}
    button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:10px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer;font-size:15px}
    .hint{margin-top:16px;font-size:13px;color:var(--text-2)}
  </style>
</head>
<body>
  <div class="card">
    <div class="mark">NS</div>
    <h1>Notepad Share</h1>
    <p>Kolaborasi Markdown atau teks pada satu URL. Tanpa akun — identitas dari session.</p>
    <form id="go">
      <label for="slug">URL kustom</label>
      <input id="slug" name="slug" placeholder="rapat-tim" pattern="[a-z0-9][a-z0-9-]{1,62}" required>
      <button type="submit">Buka / buat ruang</button>
    </form>
    <p class="hint">Contoh: <?php echo $base; ?>/n/rapat-tim — share tautan yang sama ke rekan. Enkripsi PQC opsional di dalam editor.</p>
  </div>
  <script>
    document.getElementById('go').addEventListener('submit', (e) => {
      e.preventDefault();
      const slug = document.getElementById('slug').value.trim().toLowerCase();
      if (!/^[a-z0-9][a-z0-9-]{1,62}$/.test(slug)) return;
      location.href = <?php echo json_encode($base); ?> + '/n/' + encodeURIComponent(slug);
    });
  </script>
</body>
</html>
