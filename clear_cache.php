<?php

// Directory where cache files are stored
$cacheDirectory = __DIR__ . '/cache/';

// Check if the cache directory exists
if (is_dir($cacheDirectory)) {
    // Open the directory
    if ($dh = opendir($cacheDirectory)) {
        // Read each file in the directory
        while (($file = readdir($dh)) !== false) {
            // Exclude "." and ".." directories
            if ($file != '.' && $file != '..') {
                // Construct the full path to the file
                $filePath = $cacheDirectory . $file;
                // Delete the file
                unlink($filePath);
            }
        }
        // Close the directory handle
        closedir($dh);
    }
}

echo "Cache cleared successfully!";
