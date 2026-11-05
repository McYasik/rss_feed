<?php

// Téléchargement sûr d'une URL fournie par le client (anti-SSRF).
// Invariant : on ne se connecte qu'à une IP publique vérifiée, et c'est cette IP-là, épinglée
// dans curl, qui sert à la connexion. Une seconde résolution DNS (rebinding) ne peut donc pas
// rediriger la requête vers le réseau interne entre la vérification et l'appel.

const FETCH_MAX_REDIRECTS = 5;
const FETCH_MAX_BYTES     = 5 * 1024 * 1024;

/**
 * Télécharge $url en suivant les redirections à la main, chacune revérifiée.
 *
 * @return array{body: string|false, status: int, error: ?string}
 *         error vaut 'refused' quand la cible est interdite, sinon null.
 */
function safe_fetch(string $url, int $timeout, array $headers = []): array {
    for ($hop = 0; $hop <= FETCH_MAX_REDIRECTS; $hop++) {
        $target = resolve_public_target($url);
        if ($target === null) {
            return ['body' => false, 'status' => 0, 'error' => 'refused'];
        }

        [$body, $status, $location] = curl_pinned($url, $target, $timeout, $headers);

        // Redirection : la cible suivante repasse par toutes les vérifications.
        if ($status >= 300 && $status < 400 && $location !== null) {
            $url = absolute_url($location, $url);
            continue;
        }
        // Même contrat que file_get_contents : une réponse d'erreur HTTP n'a pas de corps exploitable.
        return ['body' => $status >= 200 && $status < 300 ? $body : false, 'status' => $status, 'error' => null];
    }
    return ['body' => false, 'status' => 0, 'error' => null];
}

/**
 * Vérifie schéma et destination, et renvoie l'IP à épingler, ou null si la cible est interdite.
 *
 * @return array{host: string, port: int, ip: string}|null
 */
function resolve_public_target(string $url): ?array {
    $parts  = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    $host   = $parts['host'] ?? '';
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }
    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

    // IP littérale (IPv6 entre crochets dans l'URL) : pas de DNS, on la vérifie directement.
    $literal = trim($host, '[]');
    if (filter_var($literal, FILTER_VALIDATE_IP)) {
        return is_public_ip($literal) ? ['host' => $host, 'port' => $port, 'ip' => $literal] : null;
    }

    // Nom de domaine : toutes les adresses doivent être publiques, sinon un enregistrement
    // mixte (une publique, une interne) laisserait curl choisir l'interne.
    $ips = gethostbynamel($host);
    if ($ips === false) {
        return null;
    }
    foreach ($ips as $ip) {
        if (!is_public_ip($ip)) {
            return null;
        }
    }
    return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
}

/**
 * Rejette les plages privées, réservées, locales et partagées (127/8, 10/8, 172.16/12,
 * 192.168/16, 169.254/16 dont les métadonnées cloud, 100.64/10, ::1, fc00::/7…).
 */
function is_public_ip(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
}

/**
 * Une requête, sans suivre les redirections, connectée à l'IP vérifiée.
 *
 * @return array{0: string|false, 1: int, 2: ?string} corps, statut HTTP, en-tête Location.
 */
function curl_pinned(string $url, array $target, int $timeout, array $headers): array {
    $location = null;
    $body     = '';
    $ch       = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RESOLVE         => ["{$target['host']}:{$target['port']}:{$target['ip']}"],
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION  => false,
        CURLOPT_TIMEOUT         => $timeout,
        CURLOPT_CONNECTTIMEOUT  => min(5, $timeout),
        CURLOPT_USERAGENT       => 'RSS-Aggregator/1.0',
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_ENCODING        => '',
        // Un proxy d'environnement court-circuiterait l'épinglage : connexion directe imposée.
        CURLOPT_PROXY           => '',
        CURLOPT_HEADERFUNCTION  => function ($ch, string $line) use (&$location): int {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
            }
            return strlen($line);
        },
        // Plafond de taille : renvoyer une longueur différente fait avorter le transfert.
        CURLOPT_WRITEFUNCTION   => function ($ch, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > FETCH_MAX_BYTES) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);

    $ok     = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$ok === false ? false : $body, $status, $location];
}

/**
 * Rend absolue une cible de redirection (absolue, sans schéma ou relative à l'hôte / au dossier).
 */
function absolute_url(string $location, string $base): string {
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
        return $location;
    }
    $b      = parse_url($base);
    $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (str_starts_with($location, '//')) {
        return $b['scheme'] . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }
    $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
    return $origin . $dir . $location;
}
