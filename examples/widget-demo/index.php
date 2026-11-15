<?php

/**
 * A fake customer website for testing the Knowbase widget from ANOTHER origin.
 *
 * Not part of the Laravel app: it runs on PHP's built-in server.
 *
 *   php -S localhost:8080 -t examples/widget-demo
 *
 * Then open http://localhost:8080/?key=kbw_... (your widget's public key).
 * Optional: &knowbase=http://knowbase.test (where Knowbase runs; that's the default).
 *
 * The page prints exactly the embed code a real website would paste.
 * Its CSS is deliberately aggressive: the widget must still look right (Shadow DOM + iframe).
 */
$key = preg_match('/^kbw_[A-Za-z0-9]{32}$/', $_GET['key'] ?? '') === 1 ? $_GET['key'] : null;

$knowbase = filter_var($_GET['knowbase'] ?? 'http://knowbase.test', FILTER_VALIDATE_URL) ?: 'http://knowbase.test';
$knowbase = rtrim($knowbase, '/');

$e = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acme Store · Widget demo</title>
    <style>
        /* Hostile styles a real website might have. None of them should reach the widget. */
        * { font-family: 'Comic Sans MS', cursive !important; box-sizing: content-box; }
        button { background: hotpink !important; border: 4px dashed lime !important; border-radius: 0 !important; }
        iframe { border: 8px solid red !important; width: 50px !important; }
        div { padding: 3px; }
        svg { display: none !important; }

        body { margin: 0; background: #fffbea; color: #333; }
        header { background: #f59e0b; padding: 1rem 2rem; }
        main { max-width: 40rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; }
        code { background: #eee; padding: 0.1rem 0.3rem; }
        .warning { background: #fee2e2; border: 1px solid #f87171; padding: 1rem; }
        a { color: #b45309; }
    </style>
</head>
<body>
    <header>
        <h1>Acme Store</h1>
    </header>

    <main>
        <?php if ($key === null): ?>
            <p class="warning">
                Add your widget key to the address:
                <code>http://localhost:8080/?key=kbw_...</code><br>
                Find it in Knowbase → Settings → Website widget.
            </p>
        <?php else: ?>
            <p>This page pretends to be a customer's website on <strong>another origin</strong>.
                The chat button should appear in the bottom-right corner.</p>

            <p>It also has hostile CSS (Comic Sans, pink buttons, red iframe borders, hidden SVGs).
                The widget should look normal anyway.</p>

            <p>Websites can open the chat from their own links:
                <a href="#" onclick="KnowbaseWidget.open(); return false;">Chat with us</a>.</p>

            <p>Embed code used on this page:</p>
            <pre><code><?= $e('<script src="'.$knowbase.'/widget.js" data-key="'.$key.'" async></script>') ?></code></pre>

            <?php for ($i = 1; $i <= 12; $i++): ?>
                <p>Filler paragraph <?= $i ?>, so the page scrolls and you can check the button stays put.</p>
            <?php endfor; ?>

            <script src="<?= $e($knowbase) ?>/widget.js" data-key="<?= $e($key) ?>" async></script>
        <?php endif; ?>
    </main>
</body>
</html>
