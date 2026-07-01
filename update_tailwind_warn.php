<?php
$files = [
    'd:/New folder/htdocs/restaurant_medusa/contact.html',
    'd:/New folder/htdocs/restaurant_medusa/menutest.html',
    'd:/New folder/htdocs/restaurant_medusa/my-orders.php',
    'd:/New folder/htdocs/restaurant_medusa/register.php'
];

$replacement = <<<HTML
<script>
    const originalWarn = console.warn;
    console.warn = function(...args) {
        if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com should not be used in production')) return;
        originalWarn.apply(console, args);
    };
</script>
<script src="https://cdn.tailwindcss.com"></script>
HTML;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // We might have multiple spaces, so use preg_replace
        $content = preg_replace('/<script\s+src="https:\/\/cdn\.tailwindcss\.com"><\/script>/i', $replacement, $content);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
?>
