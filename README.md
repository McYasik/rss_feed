# RSS Feed

Agrégateur RSS perso en PHP, sans base de données ni dépendance : une grille de cartes, une par
flux, chargées en parallèle, avec un panneau de configuration (rouage) qui modifie la page en
direct et génère le fichier de configuration.

> **Attention : usage local uniquement.** `api/feed.php` télécharge n'importe quelle URL qu'on
> lui passe en paramètre. Exposé sur un serveur public, il devient un relais ouvert (SSRF) :
> n'importe qui peut lui faire interroger le réseau interne, la machine elle-même ou des
> services tiers. Ne pas l'héberger sans restreindre au préalable les URL acceptées (http/https
> uniquement, et seulement les flux de la configuration).

## Lancer

PHP 8 ou plus, avec l'accès réseau sortant (`allow_url_fopen`).

```bash
php -S localhost:8000
```

Puis ouvrir http://localhost:8000/feeds.php.

## Configurer

- `src/config.example.php` : configuration de démonstration, versionnée.
- `src/config.php` : ta vraie configuration, ignorée par git. Elle prend le pas sur l'exemple
  dès qu'elle existe.

Pour démarrer, copier l'exemple puis l'ajuster, à la main ou depuis le panneau de configuration
(bouton rouage), dont l'export se colle tel quel dans `src/config.php`.

```bash
cp src/config.example.php src/config.php
```

Chaque flux porte une URL, une catégorie et, en option, `'off' => true` pour le garder en
réserve sans le charger.

## Flux protégés par Cloudflare

Quand un flux répond 403 (challenge Cloudflare), `api/feed.php` repasse par `r.jina.ai`, un
service tiers qui récupère la page à sa place : l'URL du flux lui est donc transmise.
