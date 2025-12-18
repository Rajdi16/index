<?php
/*
|--------------------------------------------------------------------------
| CONFIGURATION & SECURITY
|--------------------------------------------------------------------------
*/
$base_dir = __DIR__;
// Get the current directory from the URL, default to empty (root)
$request_dir = isset($_GET['dir']) ? $_GET['dir'] : '';

// SECURITY: Prevent access outside the allowed folder (prevent ../../ traversal)
$real_base = realpath($base_dir);
$real_target = realpath($base_dir . '/' . $request_dir);

if ($real_target === false || strpos($real_target, $real_base) !== 0) {
  // If user tries to go outside the project, force back to root
  $request_dir = '';
  $real_target = $real_base;
}

// SMART NAVIGATION:
// If the clicked folder contains an index.php or index.html, 
// open that site instead of showing the file list.
if ($request_dir !== '' && (file_exists($real_target . '/index.php') || file_exists($real_target . '/index.html'))) {
  header("Location: " . $request_dir);
  exit;
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

// Calculate folder size
// NOTE: This can be slow on large folders.
function getDirectorySize($path)
{
  $bytestotal = 0;
  $path = realpath($path);
  if ($path !== false && $path != '' && file_exists($path)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
      // PERFORMANCE: Skip heavy folders here to speed up loading
      if (preg_match('/(node_modules|\.git|vendor)/', $object->getPath())) continue;
      try {
        $bytestotal += $object->getSize();
      } catch (Exception $e) {
      }
    }
  }
  return $bytestotal;
}

// Convert bytes to readable format (KB, MB, GB)
function formatSize($bytes)
{
  if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
  if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
  return $bytes . ' B';
}

// ICON SYSTEM
// Add new file extensions or change colors here.
function getFileIcon($ext)
{
  // 1. Define Colors for specific extensions
  $colors = [
    'php' => '#777bb3',
    'html' => '#e34c26',
    'css' => '#264de4',
    'js' => '#f7df1e',
    'json' => '#000000',
    'sql' => '#e38c00',
    'zip' => '#666',
    'img' => '#d63384'
  ];

  // 2. Default "Code" Icon (used if no specific match found below)
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>';

  // 3. Image Icon Logic
  if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
    return ['color' => '#d63384', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'];
  }

  // 4. Archive/Zip Icon Logic
  if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
    return ['color' => '#db2777', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>'];
  }

  // 5. Code Icon Logic (Uses colors array)
  if (in_array($ext, ['php', 'html', 'css', 'js', 'py', 'json', 'sql'])) {
    $c = isset($colors[$ext]) ? $colors[$ext] : '#64748b';
    return ['color' => $c, 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'];
  }

  return ['color' => '#94a3b8', 'svg' => $svg];
}

/*
|--------------------------------------------------------------------------
| SCANNING LOGIC
|--------------------------------------------------------------------------
*/
$items = scandir($real_target);
$folders = [];
$files = [];

// FILES TO HIDE: Add filenames or folders here to hide them from the dashboard
$ignored = ['.', '..', 'dashboard', 'webalizer', 'xampp', 'img', 'favicon.ico', '.git', '.vscode', '.idea', 'node_modules'];

foreach ($items as $item) {
  // Skip ignored files
  if (in_array($item, $ignored)) continue;
  // Don't show this script itself
  if ($request_dir == '' && $item == basename(__FILE__)) continue;

  $path = $real_target . '/' . $item;
  $relative_path = $request_dir ? $request_dir . '/' . $item : $item;

  if (is_dir($path)) {
    // Add to Folder List
    $folders[] = [
      'name' => $item,
      'path' => '?dir=' . urlencode($relative_path),
      'date' => date("M d, Y", filemtime($path)),
      'size' => formatSize(getDirectorySize($path))
    ];
  } else {
    // Add to File List
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $iconData = getFileIcon(strtolower($ext));
    $files[] = [
      'name' => $item,
      'path' => $relative_path,
      'size' => formatSize(filesize($path)),
      'ext'  => $ext,
      'icon' => $iconData['svg'],
      'color' => $iconData['color']
    ];
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dev Dashboard</title>
  <style>
    /* ========================================
        CSS VARIABLES (THEME CONFIG)
        Change colors here for Dark/Light modes
        ========================================
        */
    :root {
      --radius: 16px;
      /* Card rounded corners */
      --blur: 14px;
      /* Glass effect intensity */
      --transition: 0.25s ease;
      --font: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    }

    /* --- DARK MODE COLORS --- */
    body[data-theme="dark"] {
      --bg: #0b0f1a;
      --bg-soft: rgba(255, 255, 255, 0.05);
      /* Card background */
      --border: rgba(255, 255, 255, 0.08);
      --text: #e5e7eb;
      --text-muted: #9ca3af;
      --accent: #6366f1;
      /* Main Blue/Purple color */
      --accent-glow: rgba(99, 102, 241, 0.35);
    }

    /* --- LIGHT MODE COLORS --- */
    body[data-theme="light"] {
      --bg: #f5f7fb;
      --bg-soft: rgba(255, 255, 255, 0.7);
      --border: rgba(0, 0, 0, 0.06);
      --text: #111827;
      --text-muted: #6b7280;
      --accent: #4f46e5;
      --accent-glow: rgba(79, 70, 229, 0.25);
    }

    /* ========================================
        BASE STYLES
        ========================================
        */
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: var(--font);
      /* Background Mesh Gradient */
      background: radial-gradient(1200px 600px at 10% -10%, var(--accent-glow), transparent),
        radial-gradient(800px 500px at 90% 10%, rgba(14, 165, 233, 0.2), transparent),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ========================================
        NAVBAR & HEADER
        ========================================
        */
    .navbar {
      position: sticky;
      top: 0;
      z-index: 10;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 18px 28px;
      backdrop-filter: blur(var(--blur));
      /* Glass effect */
      background: var(--bg-soft);
      border-bottom: 1px solid var(--border);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 700;
      font-size: 1.1rem;
      letter-spacing: 0.3px;
    }

    .brand svg {
      color: var(--accent);
    }

    /* SEARCH BAR */
    .search-container {
      position: relative;
      margin-right: 14px;
    }

    .search-icon {
      position: absolute;
      top: 50%;
      left: 14px;
      transform: translateY(-50%);
      opacity: 0.5;
    }

    .search-input {
      background: var(--bg-soft);
      border: 1px solid var(--border);
      color: var(--text);
      padding: 12px 14px 12px 42px;
      border-radius: 999px;
      outline: none;
      transition: var(--transition);
    }

    .search-input::placeholder {
      color: var(--text-muted);
    }

    .search-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-glow);
    }

    /* THEME TOGGLE BUTTON */
    .theme-btn {
      background: var(--bg-soft);
      border: 1px solid var(--border);
      border-radius: 50%;
      width: 42px;
      height: 42px;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: var(--transition);
    }

    .theme-btn:hover {
      transform: rotate(8deg) scale(1.05);
      box-shadow: 0 0 0 3px var(--accent-glow);
    }

    .hidden {
      display: none;
    }

    /* ========================================
        MAIN LAYOUT & GRID
        ========================================
        */
    .container {
      max-width: 1300px;
      /* Max width of the dashboard */
      margin: auto;
      padding: 28px;
    }

    /* Breadcrumbs (Navigation links like Root > Folder > Subfolder) */
    .breadcrumbs {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 28px;
      font-size: 0.9rem;
    }

    .bc-item {
      color: var(--text-muted);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: var(--transition);
    }

    .bc-item:hover {
      color: var(--accent);
    }

    .bc-current {
      font-weight: 600;
    }

    .bc-sep {
      opacity: 0.4;
    }

    .section-title {
      margin: 30px 0 14px;
      font-size: 0.75rem;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--text-muted);
    }

    /* GRID SYSTEM: Controls how many cards appear per row */
    .grid {
      display: grid;
      /* Auto-fill: Fit as many 260px cards as possible */
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 18px;
    }

    /* ========================================
        CARD STYLING
        ========================================
        */
    .card {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 18px;
      border-radius: var(--radius);
      background: var(--bg-soft);
      border: 1px solid var(--border);
      text-decoration: none;
      color: var(--text);
      backdrop-filter: blur(var(--blur));
      transition: var(--transition);
    }

    .card:hover {
      transform: translateY(-4px);
      border-color: var(--accent);
      box-shadow: 0 12px 40px -15px var(--accent-glow);
    }

    .card-icon-box {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: rgba(255, 255, 255, 0.08);
    }

    .folder-box {
      color: #facc15;
    }

    .card-info {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 0;
    }

    .card-name {
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .card-meta {
      display: flex;
      gap: 8px;
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    /* EMPTY STATE */
    .empty-state {
      margin-top: 80px;
      text-align: center;
      opacity: 0.7;
    }

    .empty-state svg {
      margin-bottom: 12px;
    }
  </style>

</head>

<body data-theme="dark">

  <nav class="navbar">
    <div class="brand">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-server">
        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
        <line x1="6" y1="6" x2="6.01" y2="6"></line>
        <line x1="6" y1="18" x2="6.01" y2="18"></line>
      </svg>
      <span>Localhost</span>
    </div>

    <div style="display: flex; align-items: center;">
      <div class="search-container">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" class="search-input" id="search" placeholder="Search projects..." autocomplete="off">
      </div>

      <button class="theme-btn" id="themeBtn" title="Toggle Theme">
        <svg id="moonIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
        <svg id="sunIcon" class="hidden" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line>
          <line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line>
          <line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
      </button>
    </div>
  </nav>

  <div class="container">

    <div class="breadcrumbs">
      <a href="?dir=" class="bc-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
      </a>
      <?php if ($request_dir): ?>
        <?php
        $parts = explode('/', $request_dir);
        $path_accum = '';
        foreach ($parts as $i => $part):
          $path_accum .= $part . '/';
          $isLast = $i === count($parts) - 1;
        ?>
          <span class="bc-sep">/</span>
          <?php if (!$isLast): ?>
            <a href="?dir=<?php echo rtrim($path_accum, '/'); ?>" class="bc-item"><?php echo htmlspecialchars($part); ?></a>
          <?php else: ?>
            <span class="bc-current"><?php echo htmlspecialchars($part); ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($folders)): ?>
      <div class="section-title">Folders</div>
      <div class="grid">
        <?php foreach ($folders as $f): ?>
          <a href="<?php echo $f['path']; ?>" class="card search-item">
            <div class="card-icon-box folder-box">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
              </svg>
            </div>
            <div class="card-info">
              <span class="card-name"><?php echo htmlspecialchars($f['name']); ?></span>
              <div class="card-meta">
                <span><?php echo $f['size']; ?></span>
                <span>•</span>
                <span><?php echo $f['date']; ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($files)): ?>
      <div class="section-title">Files</div>
      <div class="grid">
        <?php foreach ($files as $f): ?>
          <a href="<?php echo $f['path']; ?>" class="card search-item" target="_blank">
            <div class="card-icon-box file-box" style="color: <?php echo $f['color']; ?>;">
              <?php echo $f['icon']; ?>
            </div>
            <div class="card-info">
              <span class="card-name"><?php echo htmlspecialchars($f['name']); ?></span>
              <div class="card-meta">
                <span style="text-transform: uppercase;"><?php echo $f['ext']; ?></span>
                <span>•</span>
                <span><?php echo $f['size']; ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($folders) && empty($files)): ?>
      <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
          <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
          <polyline points="13 2 13 9 20 9"></polyline>
        </svg>
        <p>This directory is empty.</p>
      </div>
    <?php endif; ?>

  </div>

  <script>
    const themeBtn = document.getElementById('themeBtn');
    const body = document.body;
    const moonIcon = document.getElementById('moonIcon');
    const sunIcon = document.getElementById('sunIcon');

    /* ----------------------------
    THEME MANAGEMENT LOGIC
    ----------------------------
    */
    const currentTheme = localStorage.getItem('theme') || 'dark'; // Default to dark
    setTheme(currentTheme);

    function setTheme(theme) {
      body.setAttribute('data-theme', theme);
      localStorage.setItem('theme', theme);

      // Toggle Icons
      if (theme === 'dark') {
        moonIcon.classList.add('hidden');
        sunIcon.classList.remove('hidden');
      } else {
        moonIcon.classList.remove('hidden');
        sunIcon.classList.add('hidden');
      }
    }

    themeBtn.addEventListener('click', () => {
      let theme = body.getAttribute('data-theme');
      setTheme(theme === 'dark' ? 'light' : 'dark');
    });

    /* ----------------------------
    LIVE SEARCH LOGIC
    ----------------------------
    */
    document.getElementById('search').addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase();
      let hasResults = false;

      // 1. Loop through all items (files & folders)
      document.querySelectorAll('.search-item').forEach(item => {
        const name = item.querySelector('.card-name').textContent.toLowerCase();
        // Toggle visibility based on match
        if (name.includes(term)) {
          item.style.display = 'flex';
          hasResults = true;
        } else {
          item.style.display = 'none';
        }
      });

      // 2. Hide Section Titles (Folders/Files) if their content is hidden
      document.querySelectorAll('.section-title').forEach(title => {
        const nextGrid = title.nextElementSibling;
        // Count visible items in this grid
        const visibleItems = nextGrid.querySelectorAll('.search-item[style="display: flex;"]').length;
        title.style.display = visibleItems > 0 ? 'block' : 'none';
      });
    });
  </script>
</body>

</html>
