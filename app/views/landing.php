<?php
/** @var string $base */
/** @var string|null $prefillSlug */
$prefillSlug = $prefillSlug ?? '';
?>
<!DOCTYPE html>

<html class="dark" lang="id"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Notepad Share — Kolaborasi Markdown Seketika</title>
<!-- Material Symbols Outlined -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Fonts: Geist Sans & JetBrains Mono -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&amp;family=JetBrains+Mono:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Tailwind CSS with Forms and Container Queries -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
            "on-secondary": "#131e8c",
            "on-secondary-container": "#a8afff",
            "surface-bright": "#34394a",
            "surface-container-low": "#161b2b",
            "on-background": "#dee1f9",
            "secondary-container": "#2f3aa3",
            "tertiary-fixed-dim": "#7bd0ff",
            "tertiary-container": "#009bd1",
            "primary-fixed-dim": "#c0c1ff",
            "secondary-fixed-dim": "#bdc2ff",
            "primary-container": "#8083ff",
            "surface": "#0e1323",
            "primary": "#c0c1ff",
            "surface-tint": "#c0c1ff",
            "surface-container": "#1a1f30",
            "outline": "#908fa0",
            "surface-container-high": "#25293a",
            "on-secondary-fixed-variant": "#2f3aa3",
            "secondary-fixed": "#e0e0ff",
            "secondary": "#bdc2ff",
            "tertiary": "#7bd0ff",
            "on-tertiary": "#00354a",
            "on-tertiary-fixed": "#001e2c",
            "inverse-on-surface": "#2b3041",
            "on-secondary-fixed": "#000767",
            "error": "#ffb4ab",
            "on-primary-fixed-variant": "#2f2ebe",
            "error-container": "#93000a",
            "on-surface-variant": "#c7c4d7",
            "surface-dim": "#0e1323",
            "outline-variant": "#464554",
            "on-error-container": "#ffdad6",
            "on-tertiary-container": "#002d40",
            "on-error": "#690005",
            "on-tertiary-fixed-variant": "#004c69",
            "surface-variant": "#2f3446",
            "tertiary-fixed": "#c4e7ff",
            "on-primary-container": "#0d0096",
            "surface-container-lowest": "#080d1d",
            "on-primary": "#1000a9",
            "inverse-primary": "#494bd6",
            "on-surface": "#dee1f9",
            "surface-container-highest": "#2f3446",
            "inverse-surface": "#dee1f9",
            "on-primary-fixed": "#07006c",
            "background": "#0e1323",
            "primary-fixed": "#e1e0ff"
          },
          "borderRadius": {
            "DEFAULT": "0.125rem",
            "lg": "0.25rem",
            "xl": "0.5rem",
            "full": "0.75rem"
          },
          "spacing": {
            "gutter-desktop": "1.5rem",
            "margin-desktop": "3rem",
            "margin": "1rem",
            "space-lg": "1.5rem",
            "space-xl": "2.5rem",
            "space-md": "1rem",
            "margin-tablet": "2rem",
            "space-xs": "0.25rem",
            "gutter": "1rem",
            "space-sm": "0.5rem"
          },
          "fontFamily": {
            "headline-xl-mobile": ["Geist"],
            "headline-lg-mobile": ["Geist"],
            "headline-md": ["Geist"],
            "headline-xl": ["Geist"],
            "body-lg": ["Geist"],
            "body-md": ["Geist"],
            "body-sm": ["Geist"],
            "label-sm": ["JetBrains Mono"],
            "label-md": ["JetBrains Mono"],
            "headline-lg": ["Geist"],
            "code-inline": ["JetBrains Mono"]
          },
          "fontSize": {
            "headline-xl-mobile": ["28px", { "lineHeight": "36px", "letterSpacing": "-0.02em", "fontWeight": "600" }],
            "headline-lg-mobile": ["22px", { "lineHeight": "30px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
            "headline-md": ["20px", { "lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "500" }],
            "headline-xl": ["36px", { "lineHeight": "44px", "letterSpacing": "-0.03em", "fontWeight": "600" }],
            "body-lg": ["17px", { "lineHeight": "28px", "letterSpacing": "-0.005em", "fontWeight": "400" }],
            "body-md": ["15px", { "lineHeight": "24px", "fontWeight": "400" }],
            "body-sm": ["13px", { "lineHeight": "20px", "fontWeight": "400" }],
            "label-sm": ["11px", { "lineHeight": "16px", "letterSpacing": "0.02em", "fontWeight": "500" }],
            "label-md": ["13px", { "lineHeight": "18px", "letterSpacing": "-0.01em", "fontWeight": "500" }],
            "headline-lg": ["28px", { "lineHeight": "36px", "letterSpacing": "-0.02em", "fontWeight": "600" }],
            "code-inline": ["13px", { "lineHeight": "20px", "fontWeight": "400" }]
          }
        }
      }
    }
  </script>
<style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      display: inline-block;
      vertical-align: middle;
      line-height: 1;
    }
    /* Subtle dot grid backdrop */
    .bg-grid-dots {
      background-image: radial-gradient(rgba(144, 143, 160, 0.1) 1px, transparent 1px);
      background-size: 24px 24px;
    }
  </style>
</head>
<body class="bg-surface-container-lowest text-on-surface antialiased min-h-screen flex flex-col selection:bg-secondary-container selection:text-on-background">
<!-- TopNavBar (Shared Component) -->
<header class="w-full bg-surface border-b border-outline-variant/30 sticky top-0 z-50">
<div class="flex justify-between items-center w-full px-6 md:px-12 py-3 max-w-7xl mx-auto">
<!-- Left Brand & Identifier -->
<div class="flex items-center gap-3">
<a class="flex items-center gap-2.5 group" href="#">
<div class="w-8 h-8 rounded-lg bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary font-label-md text-label-md group-hover:border-primary/50 transition-colors duration-150 shadow-inner">
            NS
          </div>
<span class="font-headline-md text-headline-md font-semibold text-on-surface tracking-tight">Notepad Share</span>
</a>
<!-- Live Realtime & Privacy Badges -->
<div class="hidden sm:flex items-center gap-1.5 ml-2">
<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-surface-container border border-outline-variant/30 text-on-surface-variant font-label-sm text-label-sm">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            Realtime • Tanpa Login
          </span>
</div>
</div>
<!-- Navigation Links -->
<nav class="hidden md:flex items-center gap-6">
<a class="text-on-surface-variant hover:text-on-surface transition-colors duration-150 font-body-sm text-body-sm" href="#dokumentasi">Dokumentasi</a>
<a class="text-on-surface-variant hover:text-on-surface transition-colors duration-150 font-body-sm text-body-sm" href="#markdown">Format Markdown</a>
<a class="text-primary font-medium transition-colors duration-150 font-body-sm text-body-sm" href="#contoh">Contoh Publik</a>
</nav>
<!-- Trailing Action & Icon Cluster -->
<div class="flex items-center gap-3">
<a class="p-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors duration-150" href="https://github.com" rel="noopener noreferrer" target="_blank" title="Source Code">
<span class="material-symbols-outlined text-[20px]" data-icon="code">code</span>
</a>
<button class="p-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors duration-150" id="theme-toggle" title="Mode Tampilan">
<span class="material-symbols-outlined text-[20px]" data-icon="dark_mode">dark_mode</span>
</button>
<a class="hidden sm:inline-flex items-center justify-center px-3.5 py-1.5 rounded-lg bg-surface-container-high hover:bg-surface-bright text-on-surface border border-outline-variant/40 font-body-sm text-body-sm transition-all duration-150 active:scale-[0.98]" href="#buat-catatan">
          Buat Catatan
        </a>
</div>
</div>
</header>
<!-- Main Canvas -->
<main class="flex-1 w-full relative bg-grid-dots">
<!-- Ambient subtle indigo glow in center -->
<div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[340px] bg-secondary-container/15 blur-[120px] pointer-events-none rounded-full"></div>
<div class="max-w-7xl mx-auto px-6 md:px-12 py-12 md:py-16 flex flex-col items-center relative z-10">
<!-- Hero Statement -->
<div class="text-center max-w-2xl mx-auto mb-10">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface-container border border-outline-variant/30 text-primary font-label-sm text-label-sm mb-4">
<span class="material-symbols-outlined text-[15px]" data-icon="lock_open">lock_open</span>
<span>Instan, terenkripsi, langsung bisa dibagikan</span>
</div>
<h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight mb-3">
          Notepad Share
        </h1>
<p class="font-body-lg text-body-lg text-on-surface-variant">
          kolaborasi markdown pada satu tautan, tanpa akun.
        </p>
</div>
<!-- Main Step Action Card (Hero Card) -->
<div class="w-full max-w-[620px] bg-surface-container-low border border-outline-variant/40 rounded-xl p-6 sm:p-8 shadow-2xl relative backdrop-blur-sm transition-all duration-200">
<!-- Card Top Icon Badge & Step Tracker -->
<div class="flex items-center justify-between pb-5 border-b border-outline-variant/20 mb-6">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-lg bg-surface-container-high border border-outline-variant/40 flex items-center justify-center text-primary font-label-md text-label-md shadow-inner">
              NS
            </div>
<div>
<p class="font-body-sm text-body-sm text-on-surface font-medium">Buat Scratchpad Baru</p>
<p class="font-label-sm text-label-sm text-on-surface-variant">Sinkronisasi seketika via WebSockets</p>
</div>
</div>
<span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-surface-container border border-outline-variant/30 font-label-sm text-label-sm text-primary">
            Langkah 1 dari 2 • Tentukan Tautan
          </span>
</div>
<!-- Step 1: Slug Input & Interactive Check -->
<div class="space-y-4">
<div class="flex items-center justify-between">
<label class="font-label-md text-label-md text-on-surface flex items-center gap-1.5" for="slug-input">
              URL Kustom
              <span class="material-symbols-outlined text-[15px] text-outline cursor-help" data-icon="help" title="Gunakan nama unik untuk sesi dokumen Markdown Anda">help</span>
</label>
<span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-emerald-400">
<span class="material-symbols-outlined text-[14px]" data-icon="check_circle">check_circle</span>
              Tersedia
            </span>
</div>
<!-- Input Group: Monospace prefix & Custom Slug -->
<div class="relative flex items-center rounded-lg bg-surface-container-lowest border border-outline-variant focus-within:border-primary-container focus-within:ring-2 focus-within:ring-primary-container/20 transition-all overflow-hidden group">
<div class="px-3.5 py-2.5 bg-surface-container-low border-r border-outline-variant/30 font-label-md text-label-md text-outline select-none flex items-center gap-1">
<span class="material-symbols-outlined text-[16px] text-on-surface-variant" data-icon="link">link</span>
<span>notepad.sh/</span>
</div>
<input autocomplete="off" class="w-full bg-transparent border-0 px-3.5 py-2.5 font-label-md text-label-md text-on-surface placeholder:text-outline focus:ring-0 focus:outline-none" id="slug" name="slug" placeholder="rapat-tim" spellcheck="false" type="text" value="<?php echo htmlspecialchars($prefillSlug, ENT_QUOTES, 'UTF-8'); ?>"/>
<button type="button" id="btn-random" class="px-3 py-1.5 mr-2 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded-lg font-label-sm text-label-sm transition-colors flex items-center gap-1" title="Buat URL Acak">
<span class="material-symbols-outlined text-[15px]" data-icon="shuffle">shuffle</span>
              Acak
            </button>
</div>
<!-- Primary CTA Button -->
<button type="button" id="btn-next" class="w-full mt-2 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-primary text-on-primary font-headline-md text-headline-md font-medium hover:bg-primary-fixed-dim transition-all duration-150 active:scale-[0.99] shadow-md shadow-primary/10">
<span>Lanjut</span>
<span class="material-symbols-outlined text-[18px]" data-icon="arrow_forward">arrow_forward</span>
</button>
</div>
<!-- Step 2 Preview: Retention Options (Quiet Hint) -->
<div class="mt-7 pt-6 border-t border-outline-variant/20">
<div class="flex items-center justify-between mb-3">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-outline"></span>
<span class="font-label-sm text-label-sm text-on-surface-variant font-medium">Langkah 2: Lama Penyimpanan</span>
</div>
<span class="font-label-sm text-label-sm text-outline">1 jam sampai 7 hari</span>
</div>
<!-- Segmented Retention Badges (Muted State) -->
<select id="retention" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-3 py-2 font-label-sm text-label-sm text-on-surface">
<option value="3600000">1 jam</option>
<option value="21600000">6 jam</option>
<option value="43200000">12 jam</option>
<option value="86400000" selected>1 hari</option>
<option value="259200000">3 hari</option>
<option value="604800000">7 hari</option>
</select>
<button type="button" id="btn-create" class="w-full mt-3 hidden items-center justify-center gap-2 px-5 py-3 rounded-lg bg-primary text-on-primary font-medium">Buat ruang</button>
<div id="step-unlock" class="hidden mt-4 space-y-2">
<label class="font-label-sm text-label-sm text-on-surface-variant" for="unlock-pass">Password ruang</label>
<input id="unlock-pass" type="password" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-3 py-2 text-on-surface" autocomplete="current-password">
<button type="button" id="btn-unlock" class="w-full py-2 rounded-lg bg-primary text-on-primary font-medium">Masuk</button>
</div>
<p id="step-forbidden" class="hidden mt-4 text-body-sm text-on-surface-variant">Ruang dikunci dan tidak dapat diakses.</p>
<p id="err" class="hidden mt-3 text-error text-body-sm"></p>
</div>
<!-- Live URL preview footer box -->
<div class="mt-6 p-3 rounded-lg bg-surface-container-lowest border border-outline-variant/30 flex items-center justify-between">
<div class="flex items-center gap-2 truncate pr-2">
<span class="font-label-sm text-label-sm text-outline select-none">Tautan Anda:</span>
<span class="font-label-sm text-label-sm text-primary truncate" id="live-url-preview"><?php echo htmlspecialchars($base, ENT_QUOTES, 'UTF-8'); ?>/n/rapat-tim</span>
</div>
<button class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-surface-container hover:bg-surface-bright text-on-surface-variant hover:text-on-surface font-label-sm text-label-sm transition-colors shrink-0" id="copy-btn">
<span class="material-symbols-outlined text-[14px]" data-icon="content_copy">content_copy</span>
            Salin
          </button>
</div>
</div>
<!-- Live Markdown Scratchpad Showcase (Editorial Split-Snippet) -->
<div class="w-full max-w-4xl mt-14">
<div class="text-center mb-6">
<p class="font-label-sm text-label-sm text-outline tracking-wider uppercase">Pratinjau Antarmuka Editor</p>
</div>
<div class="rounded-xl border border-outline-variant/30 bg-surface-container-lowest overflow-hidden shadow-2xl">
<!-- Editor Titlebar -->
<div class="bg-surface-container-low px-4 py-2.5 border-b border-outline-variant/30 flex items-center justify-between">
<div class="flex items-center gap-2">
<div class="w-2.5 h-2.5 rounded-full bg-outline-variant"></div>
<div class="w-2.5 h-2.5 rounded-full bg-outline-variant"></div>
<div class="w-2.5 h-2.5 rounded-full bg-outline-variant"></div>
<span class="ml-2 font-label-sm text-label-sm text-on-surface-variant">notepad.sh/rapat-tim.md</span>
</div>
<!-- Presence indicator tokens -->
<div class="flex items-center gap-3">
<div class="flex items-center -space-x-1.5">
<span class="w-5 h-5 rounded-full bg-primary/20 border border-primary text-[10px] font-label-sm flex items-center justify-center text-primary" title="Budi (Aktif)">B</span>
<span class="w-5 h-5 rounded-full bg-secondary-container border border-secondary text-[10px] font-label-sm flex items-center justify-center text-secondary" title="Siti (Aktif)">S</span>
</div>
<span class="font-label-sm text-label-sm text-outline">2 penyunting</span>
</div>
</div>
<!-- Editor Body: Side-by-Side Raw Markdown & Rendered Output -->
<div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-outline-variant/30">
<!-- Left: Raw Markdown Input -->
<div class="p-5 font-code-inline text-code-inline space-y-2 bg-surface-container-lowest text-on-surface-variant">
<div class="text-outline select-none font-label-sm pb-1 border-b border-outline-variant/20 flex justify-between">
<span>INPUT MARKDOWN</span>
<span>UTF-8</span>
</div>
<p class="text-primary font-medium"># Agenda Sinkronisasi Tim</p>
<p class="text-outline-variant">---</p>
<p class="text-on-surface">Berikut target rilis sprint minggu ini:</p>
<p class="text-on-surface-variant pl-2 border-l-2 border-primary/40 font-code-inline">
                &gt; Catatan terenkripsi otomatis sisi klien.
              </p>
<p class="text-on-surface font-code-inline">- [x] Rilis v1.0 ke staging</p>
<p class="text-on-surface font-code-inline">- [ ] Validasi protokol WebRTC</p>
<p class="text-on-surface font-code-inline">- [ ] Migrasi dokumentasi API</p>
<div class="pt-2 text-outline text-label-sm"></div></div></div></div></div></div></main><script>
    const BASE = <?php echo json_encode($base); ?>;
    const prefill = <?php echo json_encode($prefillSlug); ?>;
    let csrf = '';
    let slug = (prefill || '').toLowerCase();
    const $ = (id) => document.getElementById(id);
    function fail(msg) {
      $('err').textContent = msg;
      $('err').classList.remove('hidden');
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
      if (!r.ok) throw new Error(data.error?.message || r.statusText);
      return data;
    }
    function goRoom() { location.href = BASE + '/n/' + encodeURIComponent(slug); }
    function paintUrl() {
      const v = ($('slug').value || 'rapat-tim').trim().toLowerCase();
      $('live-url-preview').textContent = location.host + BASE + '/n/' + v;
    }
    async function inspectSlug(value) {
      slug = value.trim().toLowerCase();
      if (!/^[a-z0-9][a-z0-9-]{1,62}$/.test(slug)) { fail('Slug tidak valid'); return; }
      const meta = await api('/api/notes/' + encodeURIComponent(slug) + '/access');
      $('err').classList.add('hidden');
      $('step-unlock').classList.add('hidden');
      $('step-forbidden').classList.add('hidden');
      $('btn-create').classList.add('hidden');
      if (!meta.exists) { $('btn-create').classList.remove('hidden'); $('btn-create').classList.add('inline-flex'); return; }
      if (meta.is_owner || !meta.locked) { goRoom(); return; }
      if (meta.has_password) { $('step-unlock').classList.remove('hidden'); return; }
      $('step-forbidden').classList.remove('hidden');
    }
    $('slug').addEventListener('input', paintUrl);
    $('btn-random').addEventListener('click', () => {
      $('slug').value = 'ruang-' + Math.random().toString(36).slice(2, 8);
      paintUrl();
    });
    $('btn-next').addEventListener('click', () => inspectSlug($('slug').value).catch((e) => fail(e.message)));
    $('btn-create').addEventListener('click', async () => {
      try {
        await api('/api/notes', { method: 'POST', json: { slug, retention_ms: parseInt($('retention').value, 10) } });
        goRoom();
      } catch (e) { fail(e.message); }
    });
    $('btn-unlock').addEventListener('click', async () => {
      try {
        await api('/api/notes/' + encodeURIComponent(slug) + '/unlock', { method: 'POST', json: { password: $('unlock-pass').value } });
        goRoom();
      } catch (e) { fail(e.message); }
    });
    $('copy-btn').addEventListener('click', () => navigator.clipboard.writeText(location.origin + BASE + '/n/' + ($('slug').value || 'rapat-tim')));
    (async () => {
      try {
        const s = await api('/api/session');
        csrf = s.csrf;
        paintUrl();
        if (prefill) await inspectSlug(prefill);
      } catch (e) { fail(e.message); }
    })();
  </script>
</body></html>