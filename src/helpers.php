<?php

function get_feeds_data(array $feeds_config): array {
    $feeds_by_category = [];

    foreach ($feeds_config as $feeds_category => $feeds_url) {

        $feeds_by_category[$feeds_category] = [];

        foreach ($feeds_url as $f) {

            $rss_feed = @simplexml_load_file($f);

            if (!$rss_feed || !isset($rss_feed->channel)) {
                continue; // flux illisible ou sans channel
            }

            $title = isset($rss_feed->channel->title) ? trim((string) $rss_feed->channel->title) : 'unknown title';
            $link = isset($rss_feed->channel->link) ? trim((string) $rss_feed->channel->link) : '';
            $desc = isset($rss_feed->channel->description) ? trim((string) $rss_feed->channel->description) : '';

            $items = [];
            foreach ($rss_feed->channel->item as $item) {
                $items[] = [
                    'title' => (string) ($item->title ?? ''),
                    'link' => (string) ($item->link ?? ''),
                    'pubDate' => (string) ($item->pubDate ?? ''),
                    'description' => (string) ($item->description ?? ''),
                ];
            }

            $feeds_by_category[$feeds_category][] = [
                'title' => $title,
                'link' => $link,
                'description' => $desc,
                'items' => $items,
            ];
        }
    }

    return $feeds_by_category;
}

// Favicon via le service de Google : évite de parser chaque site pour trouver son icône.
function favicon_url(string $url): string {
    return 'https://www.google.com/s2/favicons?sz=32&domain=' . urlencode(parse_url($url, PHP_URL_HOST) ?? '');
}

// Noir ou blanc selon la luminance du fond, pour garder le titre lisible sur une couleur de bandeau libre.
function contrast_text(string $hex): string {
    [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%2x%2x%2x');
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#000000' : '#ffffff';
}

// Réglages d'affichage traduits en variables CSS. editor.js reconstruit le même bloc en direct.
// Les valeurs sont typées ici : le contenu d'une balise <style> n'est pas échappable en HTML.
function settings_css(object $config): string {
    $per_row = max(1, (int) $config->feeds_per_row);
    $item    = (float) $config->font_size;
    $title   = (float) $config->title_size;
    $css     = ":root { --per-row: {$per_row}; --item-size: {$item}rem; --title-size: {$title}rem; }\n";
    // Bandeau : les variables de texte sont redéfinies dans son seul périmètre.
    $color = (string) $config->header_color;
    if (preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        $fg   = contrast_text($color);
        $css .= ".page-title { --header-bg: {$color}; --text: {$fg}; --text-muted: {$fg}; }\n";
    }
    // Limite par CSS : tous les items restent chargés, la valeur peut donc changer en direct.
    if ($config->items_per_feed) {
        $from = (int) $config->items_per_feed + 1;
        $css .= ".rss-item:nth-child(n + {$from}) { display: none; }\n";
    }
    return $css;
}
