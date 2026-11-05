# RSS Feed

Agrégateur RSS perso en PHP, sans base de données ni dépendance : une grille de cartes, une par
flux, chargées en parallèle, avec un panneau de configuration (rouage) qui modifie la page en
direct et génère le fichier de configuration.

> **Attention : pensé pour un usage local.** `api/feed.php` télécharge l'URL qu'on lui passe en
> paramètre. Il refuse tout ce qui n'est pas http/https vers une IP publique (réseau interne,
> machine locale, métadonnées cloud), redirections comprises, mais reste par défaut un relais
> vers n'importe quel flux public. Hébergé, activer la liste blanche (voir plus bas). Et la
> configuration, flux compris, est alors visible de tout visiteur de la page.

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

## Réglages serveur

`src/server.php` (ignoré par git, modèle dans `src/server.example.php`) porte les réglages
jamais transmis au navigateur. `'feed_whitelist' => true` limite `api/feed.php` aux flux de
`src/config.php` : un flux ajouté depuis le panneau ne se charge alors qu'une fois la
configuration enregistrée.

```bash
cp src/server.example.php src/server.php
```

## Flux protégés par Cloudflare

Quand un flux répond 403 (challenge Cloudflare), `api/feed.php` repasse par `r.jina.ai`, un
service tiers qui récupère la page à sa place : l'URL du flux lui est donc transmise.
