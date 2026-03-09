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
function getDirectorySize($path)
{
    $bytestotal = 0;
    $path = realpath($path);
    if ($path !== false && $path != '' && file_exists($path)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
            // PERFORMANCE: Skip heavy folders here to speed up loading
            if (preg_match('/(node_modules|\.git|vendor)/', $object->getPath()))
                continue;
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
    if ($bytes >= 1073741824)
        return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)
        return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)
        return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ICON SYSTEM
function getFileIcon($ext)
{
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

    $svg_default = '<svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>';

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
        return ['color' => '#d63384', 'svg' => '<svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'];
    }

    if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return ['color' => '#db2777', 'svg' => '<svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>'];
    }

    if (in_array($ext, ['php', 'html', 'css', 'js', 'py', 'json', 'sql'])) {
        $c = isset($colors[$ext]) ? $colors[$ext] : '#64748b';
        return ['color' => $c, 'svg' => '<svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'];
    }

    return ['color' => '#94a3b8', 'svg' => $svg_default];
}

/*
|--------------------------------------------------------------------------
| SCANNING LOGIC
|--------------------------------------------------------------------------
*/
$items = scandir($real_target);
$folders = [];
$files = [];

$ignored = ['.', '..', 'dashboard', 'webalizer', 'xampp', 'img', 'favicon.ico', '.git', '.vscode', '.idea', 'node_modules'];

foreach ($items as $item) {
    if (in_array($item, $ignored))
        continue;
    if ($request_dir == '' && $item == basename(__FILE__))
        continue;

    $path = $real_target . '/' . $item;
    $relative_path = $request_dir ? $request_dir . '/' . $item : $item;

    if (is_dir($path)) {
        $folders[] = [
            'name' => $item,
            'path' => '?dir=' . urlencode($relative_path),
            'date' => date("M d, Y", filemtime($path)),
            'size' => formatSize(getDirectorySize($path))
        ];
    } else {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $iconData = getFileIcon(strtolower($ext));
        $files[] = [
            'name' => $item,
            'path' => $relative_path,
            'size' => formatSize(filesize($path)),
            'ext' => $ext,
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
    <title>Workspace | Localhost</title>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        /* 
         * ==========================================
         *  MODERN DESIGN SYSTEM (GLASSMORPHISM 3.0)
         * ==========================================
         */
        :root {
            /* Colors: Dark Mode Default */
            --bg-body: #050505;
            --bg-gradient-1: #4f46e5;
            --bg-gradient-2: #db2777;
            --bg-gradient-3: #2563eb;

            --glass-bg: rgba(20, 20, 25, 0.65);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-highlight: rgba(255, 255, 255, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.36);

            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --text-accent: #818cf8;

            --primary: #6366f1;
            --secondary: #ec4899;
            --success: #10b981;

            /* Spacing & Layout */
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 8px;
            --container-width: 1400px;

            /* Animations */
            --ease-elastic: cubic-bezier(0.34, 1.56, 0.64, 1);
            --ease-smooth: cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ======== DAYLIGHT (Light Mode) ======== */
        body[data-theme="daylight"] {
            --bg-body: #f8f9fc;
            --bg-gradient-1: #c7d2fe;
            --bg-gradient-2: #fbcfe8;
            --bg-gradient-3: #a5f3fc;

            --glass-bg: rgba(255, 255, 255, 0.80);
            --glass-border: rgba(200, 210, 230, 0.5);
            --glass-highlight: rgba(255, 255, 255, 0.6);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);

            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-accent: #4f46e5;
            --primary: #4f46e5;
            --secondary: #ec4899;
            --success: #10b981;
        }

        /* ======== FOREST (Deep Nature Green) ======== */
        body[data-theme="forest"] {
            --bg-body: #0a120a;
            --bg-gradient-1: #166534;
            --bg-gradient-2: #065f46;
            --bg-gradient-3: #14532d;

            --glass-bg: rgba(10, 25, 15, 0.70);
            --glass-border: rgba(34, 197, 94, 0.12);
            --glass-highlight: rgba(34, 197, 94, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);

            --text-main: #e2ffe2;
            --text-muted: #6ee7a0;
            --text-accent: #4ade80;
            --primary: #22c55e;
            --secondary: #a3e635;
            --success: #34d399;
        }

        /* ======== SUNSET (Warm Amber & Coral) ======== */
        body[data-theme="sunset"] {
            --bg-body: #110a05;
            --bg-gradient-1: #ea580c;
            --bg-gradient-2: #dc2626;
            --bg-gradient-3: #f59e0b;

            --glass-bg: rgba(30, 15, 8, 0.70);
            --glass-border: rgba(251, 146, 60, 0.15);
            --glass-highlight: rgba(251, 146, 60, 0.06);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);

            --text-main: #fff7ed;
            --text-muted: #fdba74;
            --text-accent: #fb923c;
            --primary: #f97316;
            --secondary: #ef4444;
            --success: #fbbf24;
        }

        /* ======== TERMINAL (Hacker Green-on-Black) ======== */
        body[data-theme="terminal"] {
            --bg-body: #000000;
            --bg-gradient-1: #00ff41;
            --bg-gradient-2: #008f11;
            --bg-gradient-3: #003b00;

            --glass-bg: rgba(0, 8, 0, 0.80);
            --glass-border: rgba(0, 255, 65, 0.10);
            --glass-highlight: rgba(0, 255, 65, 0.03);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);

            --text-main: #00ff41;
            --text-muted: #00b330;
            --text-accent: #39ff14;
            --primary: #00ff41;
            --secondary: #39ff14;
            --success: #00ff41;
        }

        /* ======== OCEAN (Deep Sea Blue) ======== */
        body[data-theme="ocean"] {
            --bg-body: #020617;
            --bg-gradient-1: #0369a1;
            --bg-gradient-2: #1d4ed8;
            --bg-gradient-3: #0e7490;

            --glass-bg: rgba(5, 15, 35, 0.70);
            --glass-border: rgba(56, 189, 248, 0.12);
            --glass-highlight: rgba(56, 189, 248, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.45);

            --text-main: #e0f2fe;
            --text-muted: #7dd3fc;
            --text-accent: #38bdf8;
            --primary: #0ea5e9;
            --secondary: #06b6d4;
            --success: #2dd4bf;
        }

        /* ======== AURORA (Northern Lights) ======== */
        body[data-theme="aurora"] {
            --bg-body: #050510;
            --bg-gradient-1: #7c3aed;
            --bg-gradient-2: #06b6d4;
            --bg-gradient-3: #10b981;

            --glass-bg: rgba(12, 10, 30, 0.70);
            --glass-border: rgba(139, 92, 246, 0.15);
            --glass-highlight: rgba(6, 182, 212, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);

            --text-main: #f0e7ff;
            --text-muted: #a78bfa;
            --text-accent: #c084fc;
            --primary: #8b5cf6;
            --secondary: #06b6d4;
            --success: #34d399;
        }

        /* ======== ROSE (Elegant Rose Gold) ======== */
        body[data-theme="rose"] {
            --bg-body: #120808;
            --bg-gradient-1: #be185d;
            --bg-gradient-2: #9f1239;
            --bg-gradient-3: #e11d48;

            --glass-bg: rgba(30, 10, 15, 0.70);
            --glass-border: rgba(244, 114, 182, 0.15);
            --glass-highlight: rgba(251, 113, 133, 0.05);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);

            --text-main: #fff1f2;
            --text-muted: #fda4af;
            --text-accent: #fb7185;
            --primary: #f43f5e;
            --secondary: #ec4899;
            --success: #fb923c;
        }

        /* ======== CYBERPUNK (Neon Purple & Cyan) ======== */
        body[data-theme="cyberpunk"] {
            --bg-body: #0a0014;
            --bg-gradient-1: #d946ef;
            --bg-gradient-2: #06b6d4;
            --bg-gradient-3: #a855f7;

            --glass-bg: rgba(15, 5, 30, 0.75);
            --glass-border: rgba(217, 70, 239, 0.18);
            --glass-highlight: rgba(6, 182, 212, 0.06);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);

            --text-main: #f5d0fe;
            --text-muted: #d946ef;
            --text-accent: #e879f9;
            --primary: #d946ef;
            --secondary: #06b6d4;
            --success: #a3e635;
        }

        /* RESET & BASE */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            transition: background-color 0.5s ease, color 0.4s ease;
        }

        /* Terminal theme monospace font override */
        body[data-theme="terminal"] {
            font-family: 'Courier New', 'Consolas', monospace;
        }

        body[data-theme="terminal"] .brand,
        body[data-theme="terminal"] .section-title {
            font-family: 'Courier New', 'Consolas', monospace;
        }

        /* BACKGROUND MESH GRADIENT (uses CSS var colors) */
        .ambient-light {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background:
                radial-gradient(circle at 15% 50%, color-mix(in srgb, var(--bg-gradient-1) 22%, transparent), transparent 45%),
                radial-gradient(circle at 85% 30%, color-mix(in srgb, var(--bg-gradient-2) 22%, transparent), transparent 45%),
                radial-gradient(circle at 50% 80%, color-mix(in srgb, var(--bg-gradient-3) 15%, transparent), transparent 45%);
            filter: blur(80px);
            opacity: 1;
            transition: background 0.8s ease;
        }

        /* NOISE TEXTURE OVERLAY */
        .noise-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        /* NAVIGATION BAR */
        .navbar {
            position: sticky;
            top: 20px;
            margin: 0 auto 40px;
            max-width: var(--container-width);
            width: 95%;
            padding: 12px 24px;
            border-radius: 999px;

            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);

            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            transition: transform 0.3s var(--ease-smooth);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-main);
            text-decoration: none;
            letter-spacing: -0.02em;
        }

        .brand span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* SEARCH BAR */
        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 420px;
            margin: 0 20px;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px 12px 48px;
            border-radius: 999px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s var(--ease-smooth);
        }

        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        /* ACTION BUTTONS */
        .nav-actions {
            display: flex;
            gap: 10px;
        }

        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: all 0.2s var(--ease-smooth);
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .php-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
        }

        .php-btn:hover {
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4);
        }

        /* MAIN LAYOUT */
        .container {
            max-width: var(--container-width);
            width: 95%;
            margin: 0 auto 40px;
            padding: 0 10px;
        }

        /* BREADCRUMBS */
        .breadcrumbs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 32px;
            padding: 8px 0;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .crumb {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .crumb:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.03);
        }

        .crumb.active {
            color: var(--text-main);
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
        }

        .crumb-sep {
            opacity: 0.3;
        }

        /* GRID SYSTEM */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 40px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--glass-border);
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            background: rgba(255, 255, 255, 0.08);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* CARDS */
        .card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            border-radius: var(--radius-lg);

            background: var(--glass-bg);
            border: 1px solid var(--glass-border);

            text-decoration: none;
            color: var(--text-main);

            transition: all 0.3s var(--ease-elastic);
            overflow: hidden;

            /* Entrance Animation */
            animation: fadeSlideUp 0.5s ease backwards;
        }

        /* Staggered Delay for Items (up to 20 items) */
        .card:nth-child(1) {
            animation-delay: 0.05s;
        }

        .card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .card:nth-child(3) {
            animation-delay: 0.15s;
        }

        .card:nth-child(4) {
            animation-delay: 0.2s;
        }

        .card:nth-child(5) {
            animation-delay: 0.25s;
        }

        .card:nth-child(6) {
            animation-delay: 0.3s;
        }

        /* ... more can be added via JS if needed for huge lists ... */

        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0) 20%, rgba(255, 255, 255, 0.05) 50%, rgba(255, 255, 255, 0) 80%);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .card:hover {
            transform: translateY(-5px) scale(1.01);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.08);
        }

        .card:hover::before {
            transform: translateX(100%);
        }

        .icon-box {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.01));
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .folder-icon {
            color: #fbbf24;
        }

        .file-icon {
            color: var(--text-accent);
        }

        .card-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow: hidden;
        }

        .card-name {
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .dot {
            width: 3px;
            height: 3px;
            background: currentColor;
            border-radius: 50%;
            opacity: 0.5;
        }

        /* EMPTY STATE */
        .empty-state {
            grid-column: 1 / -1;
            padding: 80px 20px;
            text-align: center;
            color: var(--text-muted);
            background: var(--glass-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--glass-border);
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--text-main);
        }

        /* THEME PICKER POPUP */
        .theme-picker-overlay {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease;
        }

        .theme-picker-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .theme-picker {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.92);
            z-index: 9999;
            width: 420px;
            max-width: 92vw;
            max-height: 80vh;
            overflow-y: auto;
            padding: 28px;
            border-radius: var(--radius-lg);
            background: var(--glass-bg);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border: 1px solid var(--glass-border);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s var(--ease-elastic);
        }

        .theme-picker.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .theme-picker-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .theme-picker-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .theme-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .theme-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.03);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--text-main);
        }

        .theme-option:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .theme-option.active {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 30%, transparent);
        }

        .theme-swatch {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .theme-swatch::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 10px;
            border: 2px solid rgba(255, 255, 255, 0.15);
        }

        .theme-option-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .theme-option-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .theme-option-desc {
            font-size: 0.7rem;
            color: var(--text-muted);
            opacity: 0.7;
        }

        .theme-option .check-icon {
            margin-left: auto;
            width: 20px;
            height: 20px;
            opacity: 0;
            color: var(--primary);
            transition: opacity 0.2s;
        }

        .theme-option.active .check-icon {
            opacity: 1;
        }

        /* FOOTER */
        footer {
            margin-top: auto;
            border-top: 1px solid var(--glass-border);
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        footer a {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 500;
        }

        footer a:hover {
            text-decoration: underline;
        }

        .kbd {
            display: inline-block;
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            color: var(--text-main);
            vertical-align: baseline;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 16px;
                padding: 16px;
                border-radius: var(--radius-lg);
            }

            .search-wrapper {
                margin: 0;
                max-width: 100%;
            }

            .nav-actions {
                width: 100%;
                justify-content: center;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body data-theme="midnight">

    <div class="ambient-light"></div>
    <div class="noise-overlay"></div>

    <nav class="navbar">
        <a href="?dir=" class="brand">
            <svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
            <span>Localhost</span>
        </a>

        <div class="search-wrapper">
            <svg class="search-icon"
                xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="search" class="search-input" placeholder="Search files & folders..."
                autocomplete="off">
        </div>

        <div class="nav-actions">
            <!-- PHPMYADMIN BTN -->
            <a href="http://localhost/phpmyadmin5.2.3" target="_blank" class="icon-btn php-btn" title="Open PHPMyAdmin">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/>
                    <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/>
                </svg>
            </a>

            <!-- THEME TOGGLE -->
            <button id="themeBtn" class="icon-btn" title="Change theme (D)">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.04-.24-.3-.39-.65-.39-1.04 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.96-4.5-9-10-9z"/>
                </svg>
                <span id="themeLabel"
                    style="margin-left:6px;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;opacity:0.7;">Midnight</span>
            </button>
        </div>
    </nav>

    <main class="container">

        <div class="breadcrumbs">
            <a href="?dir=" class="crumb">
                <svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                    width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Home
            </a>
            <?php if ($request_dir): ?>
                <?php
                $parts = explode('/', $request_dir);
                $path_accum = '';
                foreach ($parts as $i => $part):
                    $path_accum .= $part . '/';
                    $isLast = $i === count($parts) - 1;
                    ?>
                    <span class="crumb-sep">/</span>
                    <?php if (!$isLast): ?>
                        <a href="?dir=<?php echo rtrim($path_accum, '/'); ?>"
                            class="crumb"><?php echo htmlspecialchars($part); ?></a>
                    <?php else: ?>
                        <span class="crumb active"><?php echo htmlspecialchars($part); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($folders)): ?>
            <div class="section-container">
                <header class="section-header">
                    <h2 class="section-title">
                        <svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                            width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Directories
                    </h2>
                    <span class="badge"><?php echo count($folders); ?></span>
                </header>
                <div class="grid">
                    <?php foreach ($folders as $f): ?>
                        <a href="<?php echo $f['path']; ?>" class="card search-item" target="_blank">
                            <div class="icon-box folder-icon">
                                <svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                                    width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="fill: currentColor; fill-opacity: 0.2;">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z">
                                    </path>
                                </svg>
                            </div>
                            <div class="card-details">
                                <span class="card-name"><?php echo htmlspecialchars($f['name']); ?></span>
                                <div class="card-meta">
                                    <span><?php echo $f['size']; ?></span>
                                    <span class="dot"></span>
                                    <span>Folder</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($files)): ?>
            <div class="section-container">
                <header class="section-header">
                    <h2 class="section-title">
                        <svg xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                            width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                        Files
                    </h2>
                    <span class="badge"><?php echo count($files); ?></span>
                </header>
                <div class="grid">
                    <?php foreach ($files as $f): ?>
                        <a href="<?php echo $f['path']; ?>" class="card search-item" target="_blank">
                            <div class="icon-box file-icon" style="color: <?php echo $f['color']; ?>;">
                                <?php echo $f['icon']; ?>
                            </div>
                            <div class="card-details">
                                <span class="card-name"><?php echo htmlspecialchars($f['name']); ?></span>
                                <div class="card-meta">
                                    <span><?php echo $f['size']; ?></span>
                                    <span class="dot"></span>
                                    <span style="text-transform: uppercase"><?php echo $f['ext']; ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($folders) && empty($files)): ?>
            <div class="empty-state">
                <svg class="empty-icon"
                    xmlns="https://png.pngtree.com/png-clipart/20190520/original/pngtree-database-glyph-black-icon-png-image_3754811.jpg"
                    width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="13 2 13 9 20 9"></polyline>
                </svg>
                <h3>Absolutely Empty</h3>
                <p>No files or folders found in this directory.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>Press <span class="kbd">/</span> to search &nbsp;•&nbsp; <span class="kbd">D</span> to change theme</p>
        <p style="margin-top: 8px; font-size: 0.8rem; opacity: 0.6;">Running on PHP <?php echo PHP_VERSION; ?></p>
    </footer>

    <!-- THEME PICKER POPUP -->
    <div class="theme-picker-overlay" id="themeOverlay"></div>
    <div class="theme-picker" id="themePicker">
        <div class="theme-picker-title">Choose Theme</div>
        <div class="theme-picker-subtitle">Pick the vibe that suits you • Press <span class="kbd">D</span> to toggle
        </div>
        <div class="theme-grid" id="themeGrid"></div>
    </div>

    <script>
        // =============================================
        //  THEME SYSTEM
        // =============================================
        const THEMES = [
            { id: 'midnight', name: 'Midnight', desc: 'Default dark', gradient: 'linear-gradient(135deg, #4f46e5, #db2777)' },
            { id: 'daylight', name: 'Daylight', desc: 'Clean & bright', gradient: 'linear-gradient(135deg, #c7d2fe, #fbcfe8)' },
            { id: 'forest', name: 'Forest', desc: 'Deep nature green', gradient: 'linear-gradient(135deg, #166534, #065f46)' },
            { id: 'sunset', name: 'Sunset', desc: 'Warm amber & coral', gradient: 'linear-gradient(135deg, #ea580c, #dc2626)' },
            { id: 'terminal', name: 'Terminal', desc: 'Hacker mode', gradient: 'linear-gradient(135deg, #00ff41, #003b00)' },
            { id: 'ocean', name: 'Ocean', desc: 'Deep sea blue', gradient: 'linear-gradient(135deg, #0369a1, #1d4ed8)' },
            { id: 'aurora', name: 'Aurora', desc: 'Northern lights', gradient: 'linear-gradient(135deg, #7c3aed, #06b6d4)' },
            { id: 'rose', name: 'Rose', desc: 'Elegant rose gold', gradient: 'linear-gradient(135deg, #be185d, #9f1239)' },
            { id: 'cyberpunk', name: 'Cyberpunk', desc: 'Neon purple & cyan', gradient: 'linear-gradient(135deg, #d946ef, #06b6d4)' }
        ];

        const body = document.body;
        const themeBtn = document.getElementById('themeBtn');
        const themeLabel = document.getElementById('themeLabel');
        const themePicker = document.getElementById('themePicker');
        const themeOverlay = document.getElementById('themeOverlay');
        const themeGrid = document.getElementById('themeGrid');
        const searchInput = document.getElementById('search');

        // Build picker grid
        const checkSvg = '<svg class="check-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

        THEMES.forEach(t => {
            const btn = document.createElement('button');
            btn.className = 'theme-option';
            btn.dataset.theme = t.id;
            btn.innerHTML = `
                <div class="theme-swatch" style="background: ${t.gradient}"></div>
                <div class="theme-option-text">
                    <span class="theme-option-name">${t.name}</span>
                    <span class="theme-option-desc">${t.desc}</span>
                </div>
                ${checkSvg}
            `;
            btn.addEventListener('click', () => setTheme(t.id));
            themeGrid.appendChild(btn);
        });

        // Theme logic
        function setTheme(theme) {
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);

            const t = THEMES.find(x => x.id === theme);
            if (themeLabel) themeLabel.textContent = t ? t.name : theme;

            // Update active state in picker
            document.querySelectorAll('.theme-option').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.theme === theme);
            });
        }

        function togglePicker() {
            const isOpen = themePicker.classList.contains('active');
            themePicker.classList.toggle('active');
            themeOverlay.classList.toggle('active');
        }

        function closePicker() {
            themePicker.classList.remove('active');
            themeOverlay.classList.remove('active');
        }

        // Init theme
        const savedTheme = localStorage.getItem('theme') || 'midnight';
        const validIds = THEMES.map(t => t.id);
        setTheme(validIds.includes(savedTheme) ? savedTheme : 'midnight');

        // Events
        themeBtn.addEventListener('click', togglePicker);
        themeOverlay.addEventListener('click', closePicker);

        // LIVE SEARCH
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.search-item').forEach(item => {
                const name = item.querySelector('.card-name').innerText.toLowerCase();
                item.style.display = name.includes(term) ? 'flex' : 'none';
            });
            document.querySelectorAll('.section-container').forEach(section => {
                const visible = section.querySelectorAll('.search-item[style="display: flex;"]').length > 0;
                section.style.display = visible ? 'block' : 'none';
            });
        });

        // KEYBOARD SHORTCUTS
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
            if (e.key.toLowerCase() === 'd' && document.activeElement !== searchInput) {
                togglePicker();
            }
            if (e.key === 'Escape') {
                closePicker();
                searchInput.blur();
            }
        });
    </script>
</body>

</html>
