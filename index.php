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

    $svg_default = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>';

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
        return ['color' => '#d63384', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'];
    }

    if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return ['color' => '#db2777', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>'];
    }

    if (in_array($ext, ['php', 'html', 'css', 'js', 'py', 'json', 'sql'])) {
        $c = isset($colors[$ext]) ? $colors[$ext] : '#64748b';
        return ['color' => $c, 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'];
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
    if (in_array($item, $ignored)) continue;
    if ($request_dir == '' && $item == basename(__FILE__)) continue;

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
    <title>Workspace | Localhost</title>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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

        body[data-theme="light"] {
            --bg-body: #f3f4f6;
            --bg-gradient-1: #c7d2fe;
            --bg-gradient-2: #fbcfe8;
            --bg-gradient-3: #bfdbfe;
            
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.6);
            --glass-highlight: rgba(255, 255, 255, 0.4);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            
            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-accent: #4f46e5;
        }

        /* RESET & BASE */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* BACKGROUND MESH GRADIENT */
        .ambient-light {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            background: 
                radial-gradient(circle at 15% 50%, rgba(79, 70, 229, 0.15), transparent 40%),
                radial-gradient(circle at 85% 30%, rgba(236, 72, 153, 0.15), transparent 40%);
            filter: blur(60px);
            opacity: 0.8;
        }
        
        /* NOISE TEXTURE OVERLAY */
        .noise-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
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
        
        .crumb-sep { opacity: 0.3; }

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
        .card:nth-child(1) { animation-delay: 0.05s; }
        .card:nth-child(2) { animation-delay: 0.1s; }
        .card:nth-child(3) { animation-delay: 0.15s; }
        .card:nth-child(4) { animation-delay: 0.2s; }
        .card:nth-child(5) { animation-delay: 0.25s; }
        .card:nth-child(6) { animation-delay: 0.3s; }
        /* ... more can be added via JS if needed for huge lists ... */

        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0) 20%, rgba(255,255,255,0.05) 50%, rgba(255,255,255,0) 80%);
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
        
        .folder-icon { color: #fbbf24; }
        .file-icon { color: var(--text-accent); }

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
        
        .dot { width: 3px; height: 3px; background: currentColor; border-radius: 50%; opacity: 0.5; }

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
        }
        
        footer a { color: var(--text-main); text-decoration: none; font-weight: 500; }
        footer a:hover { text-decoration: underline; }

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
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 16px;
                padding: 16px;
                border-radius: var(--radius-lg);
            }
            .search-wrapper { margin: 0; max-width: 100%; }
            .nav-actions { width: 100%; justify-content: center; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-theme="dark">

    <div class="ambient-light"></div>
    <div class="noise-overlay"></div>

    <nav class="navbar">
        <a href="?dir=" class="brand">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
            <span>Localhost</span>
        </a>

        <div class="search-wrapper">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="search" class="search-input" placeholder="Search files & folders..." autocomplete="off">
        </div>

        <div class="nav-actions">
            <!-- PHPMYADMIN BTN -->
            <a href="http://localhost/phpmyadmin" target="_blank" class="icon-btn php-btn" title="Open PHPMyAdmin">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"></path><path d="M18 14h-8"></path><path d="M15 18h-5"></path><path d="M10 6h8v4h-8V6Z"></path></svg>
            </a>
            
            <!-- THEME TOGGLE -->
            <button id="themeBtn" class="icon-btn" title="Toggle Theme (D)">
                <svg class="moon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <svg class="sun" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            </button>
        </div>
    </nav>

    <main class="container">
        
        <div class="breadcrumbs">
            <a href="?dir=" class="crumb">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
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
                        <a href="?dir=<?php echo rtrim($path_accum, '/'); ?>" class="crumb"><?php echo htmlspecialchars($part); ?></a>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        Directories
                    </h2>
                    <span class="badge"><?php echo count($folders); ?></span>
                </header>
                <div class="grid">
                    <?php foreach ($folders as $f): ?>
                        <a href="<?php echo $f['path']; ?>" class="card search-item" target="_blank">
                            <div class="icon-box folder-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="fill: currentColor; fill-opacity: 0.2;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
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
                <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                <h3>Absolutely Empty</h3>
                <p>No files or folders found in this directory.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>Press <span class="kbd">/</span> to search &nbsp;•&nbsp; <span class="kbd">D</span> to toggle theme</p>
        <p style="margin-top: 8px; font-size: 0.8rem; opacity: 0.6;">Running on PHP <?php echo PHP_VERSION; ?></p>
    </footer>

    <script>
        // DOM ELEMENTS
        const themeBtn = document.getElementById('themeBtn');
        const body = document.body;
        const searchInput = document.getElementById('search');
        const moonIcon = document.querySelector('.moon');
        const sunIcon = document.querySelector('.sun');

        // THEME INIT
        const savedTheme = localStorage.getItem('theme') || 'dark';
        setTheme(savedTheme);

        function setTheme(theme) {
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            if (theme === 'dark') {
                moonIcon.style.display = 'none';
                sunIcon.style.display = 'block';
            } else {
                moonIcon.style.display = 'block';
                sunIcon.style.display = 'none';
            }
        }

        // EVENT LISTENERS
        themeBtn.addEventListener('click', () => {
            const current = body.getAttribute('data-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });

        // LIVE SEARCH
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.search-item').forEach(item => {
                const name = item.querySelector('.card-name').innerText.toLowerCase();
                if (name.includes(term)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
            // Update Headers visibility
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
                // e.preventDefault(); // Don't block d in normal typing? Ah, strict check helps.
                themeBtn.click();
            }
            if (e.key === 'Escape') searchInput.blur();
        });
    </script>
</body>
</html>
