<?php

// Réglages réservés au serveur, jamais transmis au navigateur. Copier en src/server.php
// (ignoré par git) pour les activer ; sans ce fichier, tout est désactivé.
return [
    // true : api/feed.php ne sert que les flux présents dans src/config.php. À activer dès
    // que la page est hébergée. Contrepartie : un flux ajouté depuis le panneau ne se charge
    // qu'une fois la configuration enregistrée.
    'feed_whitelist' => false,
];
