<!-- filepath: c:\xampp\projet\index.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Project Links</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div>
        <h1>Project</h1>
    </div>
    <div class="projects">
        <?php
        // Scan the current directory for subfolders
        $baseDir = __DIR__;
        $folders = array_filter(glob($baseDir . '/*'), 'is_dir');

        foreach ($folders as $folder) {
            $folderName = basename($folder);
            $indexFile = $folder . '/index.php';

            // Check if the folder contains an index.php file
            if (file_exists($indexFile)) {
                echo "<div class='project'>";
                echo "<a href='$folderName/index.php'>";
                echo "<h2>$folderName</h2>";
                echo "</a>";
                echo "</div>";
            }
        }
        ?>
    </div>
</body>

</html>