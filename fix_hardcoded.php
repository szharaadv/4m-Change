<?php
/**
 * Fix semua hardcoded /4m-change/ paths
 */

$projectRoot = __DIR__;
$stats = 0;

echo "<h2>🔧 Fixing ALL hardcoded /4m-change/ paths</h2>";
echo "<pre style='background:#f5f5f5;padding:15px;border-radius:5px;font-size:12px'>";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    if (basename($file) === 'fix_hardcoded.php') continue;
    
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $original = $content;
    
    // FIX 1: header('Location: /4m-change/modules/...) → header('Location: ' . navLink('modules/...'))
    $content = preg_replace_callback(
        '/header\([\'"]Location:\s*\/4m-change\/(modules\/[^\'"]+)/',
        function($m) { return "header('Location: ' . navLink('" . $m[1] . "'"; },
        $content
    );
    
    // FIX 2: href="/4m-change/modules/... → href="<?= navLink('modules/...
    $content = preg_replace_callback(
        '/href=["\']\/4m-change\/(modules\/[^"\']+)["\']/',
        function($m) { return 'href="<?= navLink(\'' . $m[1] . '\') ?>'; },
        $content
    );
    
    // FIX 3: onclick="location.href='/4m-change/modules/... → onclick="location.href='<?= navLink('modules/...
    $content = preg_replace_callback(
        '/onclick=["\']location\.href=[\'"\/4m-change\/(modules\/[^"\']+)["\']/',
        function($m) { return 'onclick="location.href=\'<?= navLink(\'' . $m[1] . '\') ?>'; },
        $content
    );
    
    // FIX 4: For APP_URL based URLs (email), they're OK - skip those
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        $rel = str_replace($projectRoot . '/', '', $path);
        echo "✅ $rel\n";
        $stats++;
    }
}

echo "</pre>";
echo "<p style='color:green;font-weight:bold'>✅ Fixed $stats files!</p>";
echo "<p>Delete fix_hardcoded.php after done.</p>";
?>