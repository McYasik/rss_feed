<?php
// Même règle que feeds.php : configuration locale si présente, démo versionnée sinon.
$config_file = __DIR__ . '/../src/config.php';
require file_exists($config_file) ? $config_file : __DIR__ . '/../src/config.example.php';
require __DIR__ . '/../src/fetch.php';
$config = get_config();

// Réglages réservés au serveur : fichier à part, jamais transmis au front ni régénéré par
// l'éditeur (qui réécrit config.php en entier). Absent, tout est désactivé.
$server_file = __DIR__ . '/../src/server.php';
$server      = file_exists($server_file) ? require $server_file : [];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . $config->cache_ttl);

$url = $_GET['url'] ?? '';
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL invalide']);
    exit;
}

// Liste blanche : seuls les flux de la configuration enregistrée sont servis, désactivés
// compris (ils peuvent être réactivés depuis l'éditeur).
if (!empty($server['feed_whitelist']) && !in_array($url, array_column(feeds(), 'url'), true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Flux hors liste blanche (enregistrer la configuration)']);
    exit;
}

$result = safe_fetch($url, 10);
if ($result['error'] === 'refused') {
    http_response_code(400);
    echo json_encode(['error' => 'Adresse refusée (http/https public uniquement)']);
    exit;
}
$xml_str = $result['body'];
$status  = (string) $result['status'];

// Cloudflare bloque les clients non navigateurs (ex. KHInsider) : on repasse par r.jina.ai,
// dont le navigateur headless franchit le challenge. Son rendu texte préfixe le XML d'une
// phrase de la visionneuse et décode les entités, d'où la découpe et le ré-échappement des &.
if ($xml_str === false && $status === '403') {
    $text  = safe_fetch('https://r.jina.ai/' . $url, 30, ['X-Return-Format: text'])['body'];
    $start = $text === false ? false : strpos($text, '<rss');
    if ($start === false && $text !== false) {
        $start = strpos($text, '<rdf:RDF');
    }
    if ($start !== false) {
        $xml_str = preg_replace('/&(?!#?\w+;)/', '&amp;', substr($text, $start));
    }
}

if ($xml_str === false) {
    $msg = $status === '403' ?'Flux bloqué (Cloudflare/403)' : 'Flux inaccessible';
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
