<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$BASE = __DIR__;
// La configuration locale (ignorée par git) prime ; sinon on retombe sur la démo versionnée.
require file_exists($BASE . '/src/config.php') ? $BASE . '/src/config.php' : $BASE . '/src/config.example.php';
require $BASE . '/src/helpers.php';

$config       = get_config();
$feeds_config = feeds();
$colors       = category_colors();
$daily        = daily_links();

require $BASE . '/templates/header.php';

// Les flux désactivés sont rendus aussi, masqués et non chargés : le panneau de configuration
// doit pouvoir les voir pour les réactiver ou les exporter.
foreach ($feeds_config as $feed) {
    $off      = !empty($feed['off']);
    $color    = htmlspecialchars($colors[$feed['category']] ?? '#e67e22');
    $cat      = htmlspecialchars($feed['category']);
    $data_url = htmlspecialchars($feed['url']);
    $title    = $off ? htmlspecialchars(parse_url($feed['url'], PHP_URL_HOST) ?? $feed['url']) : '…';
    $class    = $off ? 'feed feed--off' : 'feed feed--loading';
    $content  = $off ? '<p class="feed-message">Flux désactivé</p>' : '<div class="spinner"></div>';
    echo <<<HTML
    <div class="{$class}" data-url="{$data_url}" data-category="{$cat}" style="--card-color:{$color}">
        <h5>
            <span class="feed-category">{$cat}</span>
            <span class="feed-title-row">
                <span class="feed-title">{$title}</span>
            </span>
        </h5>
        <div class="feed-content">{$content}</div>
    </div>
    HTML;
}

require $BASE . '/templates/footer.php';
