<?php
// Même règle que feeds.php : configuration locale si présente, démo versionnée sinon.
$config_file = __DIR__ . '/../src/config.php';
require file_exists($config_file) ? $config_file : __DIR__ . '/../src/config.example.php';
$config = get_config();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . $config->cache_ttl);

$url = $_GET['url'] ?? '';
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL invalide']);
    exit;
}

$ctx = stream_context_create(['http' => [
    'timeout'    => 10,
    'user_agent' => 'RSS-Aggregator/1.0',
]]);

$xml_str = @file_get_contents($url, false, $ctx);
$status  = isset($http_response_header[0]) ? $http_response_header[0] : '';

// Cloudflare bloque les clients non navigateurs (ex. KHInsider) : on repasse par r.jina.ai,
// dont le navigateur headless franchit le challenge. Son rendu texte préfixe le XML d'une
// phrase de la visionneuse et décode les entités, d'où la découpe et le ré-échappement des &.
if ($xml_str === false && str_contains($status, '403')) {
    $proxy_ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'header'  => "X-Return-Format: text\r\n",
    ]]);
    $text  = @file_get_contents('https://r.jina.ai/' . $url, false, $proxy_ctx);
    $start = $text === false ? false : strpos($text, '<rss');
    if ($start === false && $text !== false) {
        $start = strpos($text, '<rdf:RDF');
    }
    if ($start !== false) {
        $xml_str = preg_replace('/&(?!#?\w+;)/', '&amp;', substr($text, $start));
    }
}

if ($xml_str === false) {
    $msg = str_contains($status, '403') ? 'Flux bloqué (Cloudflare/403)' : 'Flux inaccessible';
    http_response_code(502);
    echo json_encode(['error' => $msg]);
    exit;
}

$rss = @simplexml_load_string($xml_str, 'SimpleXMLElement', LIBXML_NOCDATA);
if (!$rss) {
    http_response_code(502);
    echo json_encode(['error' => 'XML invalide']);
    exit;
}

// RSS 1.0/RDF: root is <rdf:RDF>, items are at root level
// RSS 2.0: root is <rss>, items are inside <channel>
$is_rdf = ($rss->getName() === 'RDF');

if (!$is_rdf && !isset($rss->channel)) {
    http_response_code(502);
    echo json_encode(['error' => 'XML invalide ou sans channel']);
    exit;
}

$title = trim((string) ($rss->channel->title ?? 'Inconnu'));
$link  = trim((string) ($rss->channel->link  ?? ''));
$items = [];

$items_iter = $is_rdf ? $rss->item : $rss->channel->item;

foreach ($items_iter as $item) {
    $item_link = trim((string) ($item->link ?? ''));
    if (!$item_link) {
        // Fallback to guid (e.g. KHInsider has empty <link> but valid <guid>)
        $item_link = trim((string) ($item->guid ?? ''));
    }

    $pub_date = trim((string) ($item->pubDate ?? ''));
    if (!$pub_date) {
        // RSS 1.0/RDF uses Dublin Core dc:date
        $dc       = $item->children('dc', true);
        $pub_date = trim((string) ($dc->date ?? ''));
    }

    $items[] = [
        'title'   => trim((string) ($item->title ?? '')),
        'link'    => $item_link,
        'pubDate' => $pub_date,
    ];
}

if (empty($items)) {
    echo json_encode(['error' => 'Flux vide (aucun item)']);
    exit;
}

echo json_encode(['title' => $title, 'link' => $link, 'items' => $items]);
