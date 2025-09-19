<?php
// Simple Project Hub for WAMP or XAMPP
// Features: auto-scan folders/files, search, dark mode, pretty cards, last-mod info.
// No external libs needed. Pure PHP + vanilla JS + CSS.
//by Rajdi

$baseDir = __DIR__;
$baseUrl = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$now = new DateTime();

// Folders/files to ignore
$ignore = [
  '.', '..', 'index.php', 'favicon.ico', '.git', '.github', '.idea', '.vscode',
  'node_modules', 'vendor', '__MACOSX', 'tmp', 'temp', 'cache', 'logs',
  'phpmyadmin', 'dashboard', 'wamp', 'lang', 'cgi-bin'
];

function is_hidden($name) {
  return substr($name, 0, 1) === '.';
}

function dir_last_modified($path) {
  $latest = filemtime($path);
  $items = @scandir($path);
  if (!$items) return $latest;
  foreach ($items as $i) {
    if ($i === '.' || $i === '..') continue;
    $p = $path . DIRECTORY_SEPARATOR . $i;
    $mtime = is_dir($p) ? dir_last_modified($p) : @filemtime($p);
    if ($mtime && $mtime > $latest) $latest = $mtime;
  }
  return $latest;
}

$entries = [];
$scan = scandir($baseDir);
foreach ($scan as $name) {
  if (in_array($name, $ignore, true) || is_hidden($name)) continue;
  $full = $baseDir . DIRECTORY_SEPARATOR . $name;

  // If it's a directory and looks like a project, link to it
  if (is_dir($full)) {
    $url = ($baseUrl ? $baseUrl : '') . '/' . rawurlencode($name) . '/';
    $mtime = dir_last_modified($full);
    $entries[] = [
      'type' => 'dir',
      'name' => $name,
      'url'  => $url,
      'mtime'=> $mtime,
      'size' => null
    ];
    continue;
  }

  // If it's a PHP/HTML file at root (not this hub), link to it
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (in_array($ext, ['php','html','htm'], true)) {
    $url = ($baseUrl ? $baseUrl : '') . '/' . rawurlencode($name);
    $mtime = @filemtime($full) ?: time();
    $entries[] = [
      'type' => 'file',
      'name' => $name,
      'url'  => $url,
      'mtime'=> $mtime,
      'size' => filesize($full)
    ];
  }
}

// Sort: most recently touched first, then A-Z
usort($entries, function($a, $b) {
  if ($a['mtime'] === $b['mtime']) return strcasecmp($a['name'], $b['name']);
  return $b['mtime'] <=> $a['mtime'];
});

function pretty_date($ts) {
  return date('Y-m-d H:i', $ts);
}

function pretty_size($bytes) {
  if ($bytes === null) return '';
  $units = ['B','KB','MB','GB','TB'];
  $i = 0;
  while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
  return sprintf('%.1f %s', $bytes, $units[$i]);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Project Hub — WAMP</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='46' fill='%2300bcd4'/><text x='50' y='58' font-size='46' text-anchor='middle' fill='white' font-family='Arial'>H</text></svg>">
  <style>
    :root{
      --bg: #0b1020;          /* deep space */
      --bg-soft: #121935;     /* softer panel */
      --text: #e6e9f2;        /* near-white */
      --muted: #a6b0d0;       /* muted text */
      --acc: #7c5cff;         /* main accent */
      --acc-2: #00d4ff;       /* secondary accent */
      --card: rgba(255,255,255,0.06);
      --card-hover: rgba(255,255,255,0.10);
      --border: rgba(255,255,255,0.12);
      --shadow: 0 10px 30px rgba(0,0,0,0.35);
    }
    .light{
      --bg:#f6f7fb; --bg-soft:#ffffff; --text:#10131a; --muted:#4a566e;
      --acc:#7c5cff; --acc-2:#0077ff; --card: rgba(0,0,0,0.04); --card-hover: rgba(0,0,0,0.07); --border: rgba(0,0,0,0.08);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Apple Color Emoji","Segoe UI Emoji"; background: radial-gradient(1200px 600px at 20% -10%, rgba(124,92,255,.20), transparent), radial-gradient(1000px 800px at 120% 10%, rgba(0,212,255,.15), transparent), var(--bg); color:var(--text);}
    .container{max-width:1200px; margin: 0 auto; padding: 28px 18px 60px}

    header{display:flex; align-items:center; gap:14px; justify-content:space-between; margin-bottom:18px}
    .title{display:flex; align-items:center; gap:12px}
    .logo{width:42px; height:42px; border-radius:14px; background:linear-gradient(135deg, var(--acc), var(--acc-2)); display:grid; place-items:center; color:white; font-weight:800; box-shadow: var(--shadow)}
    h1{font-size: clamp(20px, 3vw, 28px); margin:0; letter-spacing:.4px}
    .meta{font-size:13px; color:var(--muted)}

    .controls{display:flex; gap:10px; align-items:center}
    .search{position:relative}
    .search input{width: min(380px, 70vw); padding:12px 38px 12px 14px; border-radius:14px; border:1px solid var(--border); background: var(--bg-soft); color:var(--text); outline:none; transition:.2s; box-shadow: var(--shadow)}
    .search svg{position:absolute; right:10px; top:50%; transform:translateY(-50%); opacity:.7}

    .btn{border:1px solid var(--border); background:var(--bg-soft); color:var(--text); padding:10px 14px; border-radius:12px; cursor:pointer; box-shadow: var(--shadow); transition:.2s; font-weight:600}
    .btn:hover{transform: translateY(-1px); background:var(--card-hover)}

    .grid{display:grid; grid-template-columns: repeat(auto-fill, minmax(230px,1fr)); gap:16px; margin-top:16px}
    .card{position:relative; border:1px solid var(--border); background:linear-gradient(180deg, var(--card), transparent); backdrop-filter: blur(8px); padding:16px; border-radius:18px; text-decoration:none; color:inherit; transition: .18s transform, .18s background}
    .card:hover{transform: translateY(-4px); background: var(--card-hover)}
    .badge{font-size:12px; padding:6px 10px; border-radius:999px; border:1px solid var(--border); width:max-content; color:var(--muted)}
    .name{display:flex; align-items:center; gap:10px; margin:12px 0 10px}
    .name b{font-size:16px}
    .sub{font-size:12px; color:var(--muted)}

    .empty{opacity:.7; text-align:center; padding:40px; border:1px dashed var(--border); border-radius:16px; margin-top:20px}

    footer{margin-top:28px; display:flex; justify-content:space-between; gap:10px; align-items:center; color:var(--muted); font-size:12px}
    .kbd{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; border:1px solid var(--border); border-bottom-width:2px; padding:2px 6px; border-radius:6px; background:var(--card)}
  </style>
</head>
<body>
  <div class="container" id="app">
    <header>
      <div class="title">
        <div class="logo">H</div>
        <div>
          <h1>Project Hub</h1>
          <div class="meta">Root: <span style="opacity:.8"><?php echo htmlspecialchars($baseDir); ?></span></div>
        </div>
      </div>
      <div class="controls">
        <div class="search">
          <input id="q" type="text" placeholder="Search projects… (press /)" autocomplete="off" />
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.6"/></svg>
        </div>
        <button class="btn" id="modeBtn" title="Toggle theme (D)">Toggle Theme</button>
      </div>
    </header>

    <div class="grid" id="grid">
      <?php if (empty($entries)): ?>
        <div class="empty">No projects or PHP/HTML files found. Drop folders or .php files here.</div>
      <?php else: ?>
        <?php foreach ($entries as $e): $isDir = $e['type']==='dir'; ?>
          <a class="card" href="<?php echo htmlspecialchars($e['url']); ?>" target="_blank" data-name="<?php echo htmlspecialchars(strtolower($e['name'])); ?>" data-type="<?php echo $e['type']; ?>">
            <span class="badge"><?php echo $isDir ? 'Folder' : strtoupper(pathinfo($e['name'], PATHINFO_EXTENSION)); ?></span>
            <div class="name">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <?php if ($isDir): ?>
                  <path d="M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke="currentColor" stroke-width="1.6" fill="none"/>
                <?php else: ?>
                  <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/>
                  <path d="M7 8h10M7 12h10M7 16h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                <?php endif; ?>
              </svg>
              <b><?php echo htmlspecialchars($e['name']); ?></b>
            </div>
            <div class="sub">Updated: <?php echo pretty_date($e['mtime']); ?><?php if(!$isDir): ?> • Size: <?php echo pretty_size($e['size']); ?><?php endif; ?></div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <footer>
      <div>Tip: Hit <span class="kbd">/</span> to search, <span class="kbd">D</span> to toggle theme.</div>
      <div>Made with ❤️ by <a href="https://github.com/Rajdi16" target="_blank">Rajdi</a> — PHP <?php echo PHP_VERSION; ?></div>
    </footer>
  </div>

  <script>
    // Theme toggle with localStorage
    const root = document.documentElement;
    const modeBtn = document.getElementById('modeBtn');
    const saved = localStorage.getItem('hub-theme');
    if (saved === 'light') document.body.classList.add('light');

    modeBtn.addEventListener('click', () => {
      document.body.classList.toggle('light');
      localStorage.setItem('hub-theme', document.body.classList.contains('light') ? 'light' : 'dark');
    });

    // Keyboard shortcuts: / focus search, D toggle theme
    const q = document.getElementById('q');
    window.addEventListener('keydown', (e) => {
      if (e.key === '/' && document.activeElement !== q) {
        e.preventDefault(); q.focus(); q.select();
      }
      if (e.key.toLowerCase() === 'd' && document.activeElement !== q) {
        e.preventDefault(); modeBtn.click();
      }
    });

    // Client-side filter
    const cards = Array.from(document.querySelectorAll('.card'));
    q.addEventListener('input', () => {
      const k = q.value.trim().toLowerCase();
      cards.forEach(c => {
        const show = c.dataset.name.includes(k);
        c.style.display = show ? '' : 'none';
      });
    });
  </script>
</body>
</html>
