<?php

// Configuration de démonstration, versionnée. La vraie configuration va dans src/config.php
// (ignoré par git), qui prend le pas sur ce fichier dès qu'il existe.
// Ce fichier peut être régénéré en entier depuis le panneau de configuration (rouage).

function get_config(): object {
    return (object) [
        'pagetitle'      => 'RSS Feed',
        'header_color'   => null, // null = couleur du thème
        'feeds_per_row'  => 4,
        'items_per_feed' => null, // null = illimité
        'font_size'      => 0.875, // rem
        'title_size'     => 0.9, // rem
        'cache_ttl'      => 600, // secondes
    ];
}

// Sites à visiter chaque jour, affichés sous le titre.
function daily_links(): array {
    return [
        ['name' => 'Wikipédia, article du jour', 'url' => 'https://fr.wikipedia.org/'],
    ];
}

// Liste plate : l'ordre d'affichage des cartes est celui de la liste, indépendant des catégories.
// 'off' => true garde un flux en réserve sans le charger.
function feeds(): array {
    return [
        ['url' => 'https://xkcd.com/rss.xml', 'category' => 'BD'],
        ['url' => 'https://www.peppercarrot.com/en/rss/peppercarrot.xml', 'category' => 'BD'],
        ['url' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss', 'category' => 'Sciences'],
        ['url' => 'https://www.nature.com/nature.rss', 'category' => 'Sciences'],
        ['url' => 'https://www.nngroup.com/feed/rss/', 'category' => 'UX / UI', 'off' => true],
    ];
}

// L'ordre de ce tableau est celui des catégories dans le panneau de configuration.
function category_colors(): array {
    return [
        'BD'       => '#8e44ad',
        'Sciences' => '#2c3e50',
        'UX / UI'  => '#16a085',
    ];
}
