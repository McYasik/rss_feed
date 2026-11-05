<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($config->pagetitle) ?></title>
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
    <link rel="icon" href="./pics/creepycat.png">
    <link rel="stylesheet" href="./styles.css">
    <style id="rss-settings"><?= settings_css($config) ?></style>
</head>
<body>
<div class="page-title">
    <hr>
    <h1><?= htmlspecialchars($config->pagetitle) ?></h1>
    <span id="last-loaded">…</span>
    <hr>
    <button id="dark-toggle">Sombre</button>
    <button id="config-toggle" aria-expanded="false" aria-controls="config-panel" aria-label="Configuration" title="Configuration">&#9881;&#xFE0E;</button>
</div>
<?php // Toujours présent, même vide, pour que le panneau puisse y ajouter des liens en direct. ?>
<nav class="daily-links" aria-label="Liens du jour"<?= $daily ? '' : ' hidden' ?>>
    <ul>
        <?php foreach ($daily as $link): ?>
            <li>
                <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener">
                    <img src="<?= htmlspecialchars(favicon_url($link['url'])) ?>" alt="" width="16" height="16">
                    <?= htmlspecialchars($link['name']) ?>
                    <span class="visually-hidden">(nouvel onglet)</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
<div class="content">
