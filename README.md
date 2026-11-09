# NSW AI Visibility

[![CI](https://github.com/khalil-laajine/nsw-ai-visibility/actions/workflows/ci.yml/badge.svg)](https://github.com/khalil-laajine/nsw-ai-visibility/actions/workflows/ci.yml)
![WordPress 6.2+](https://img.shields.io/badge/WordPress-6.2%2B-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![Licence GPL-2.0-or-later](https://img.shields.io/badge/licence-GPL--2.0--or--later-green)

Extension WordPress pour gérer la visibilité d'un site dans les réponses des IA (GEO) : choisir quels robots d'IA peuvent lire le site, publier un fichier `llms.txt` et voir quelles pages ces robots visitent vraiment.

*WordPress plugin to manage how AI assistants see your site: control AI crawlers in robots.txt, publish an llms.txt file and track which pages AI bots actually visit. English summary at the end.*

## Pourquoi

Les assistants comme ChatGPT, Claude ou Perplexity envoient leurs propres robots sur les sites. Certains servent à citer vos pages dans leurs réponses, d'autres à entraîner des modèles. La plupart des propriétaires de sites ne savent ni lesquels passent, ni quelles pages ils lisent, ni comment les laisser entrer ou les bloquer un par un.

Cette extension répond à ces trois questions depuis l'administration WordPress.

## Fonctionnalités

**Contrôle des robots d'IA dans robots.txt**
14 robots connus (OpenAI, Anthropic, Perplexity, Google, Apple, Meta, Mistral, ByteDance, Common Crawl), classés selon leur rôle : recherche (ils citent vos pages), agent (ils ouvrent une page pour un utilisateur) ou entraînement. Vous bloquez seulement ceux que vous voulez, par exemple les robots d'entraînement en gardant ceux de recherche.

**Fichier llms.txt généré automatiquement**
Servi à l'adresse `/llms.txt`, au format proposé sur [llmstxt.org](https://llmstxt.org) : nom et description du site, une introduction que vous rédigez, la liste des pages et des derniers articles avec un résumé. Le résumé reprend la méta-description de Rank Math ou de Yoast quand elle existe, et les pages en `noindex` sont exclues. Le fichier est mis en cache et reconstruit à chaque publication. Vous pouvez aussi l'écrire entièrement à la main.

**Suivi des visites des robots d'IA**
Nombre de visites par robot, nombre de pages lues, date du dernier passage et pages les plus visitées sur 30 jours. Aucune adresse IP ni donnée de visiteur n'est enregistrée : seuls les robots sont comptés, dans une table dédiée, avec une durée de conservation réglable.

**API REST et WP-CLI**
Les statistiques sont disponibles en JSON pour un tableau de bord (Looker Studio, rapport client mensuel) et en ligne de commande.

## Installation

1. Téléchargez le dépôt (bouton **Code**, puis **Download ZIP**) ou clonez-le dans `wp-content/plugins/`.
2. Activez **NSW AI Visibility** dans **Extensions**.
3. Réglez-la dans **Réglages > AI Visibility**.

Si un fichier `robots.txt` ou `llms.txt` physique existe déjà à la racine du site, il est servi à la place de celui de WordPress : l'écran de réglages vous prévient.

## API REST

```
GET /wp-json/nsw-aiv/v1/stats?days=30
```

Réservé aux administrateurs (capacité `manage_options`). Exemple de réponse :

```json
{
  "days": 30,
  "blocked": ["GPTBot", "CCBot"],
  "by_bot": [
    { "bot": "OAI-SearchBot", "hits": 412, "pages": 37, "last_seen": "2026-11-08", "vendor": "OpenAI", "purpose": "search" }
  ],
  "top_paths": [
    { "path": "/serrurier-paris-15/", "hits": 58, "bots": "Claude-SearchBot, OAI-SearchBot, PerplexityBot" }
  ]
}
```

## WP-CLI

```bash
wp ai-visibility stats --days=7              # visites par robot
wp ai-visibility pages --format=csv > ia.csv # pages les plus visitées
wp ai-visibility llms                        # affiche le llms.txt généré
wp ai-visibility block GPTBot CCBot          # bloque des robots
wp ai-visibility allow GPTBot                # les autorise à nouveau
```

## Pour les développeurs

Filtres disponibles :

| Filtre | Rôle |
| --- | --- |
| `nsw_aiv_bots` | Ajouter ou retirer des robots de la liste |
| `nsw_aiv_llms_lines` | Modifier les lignes du llms.txt avant leur assemblage |
| `nsw_aiv_llms_include_post` | Exclure un contenu précis du llms.txt |

Exemple, ajouter un robot :

```php
add_filter( 'nsw_aiv_bots', function ( $bots ) {
	$bots['YouBot'] = array( 'vendor' => 'You.com', 'purpose' => 'search' );
	return $bots;
} );
```

Qualité du code :

```bash
composer install
composer lint       # syntaxe PHP
composer phpcs      # normes de code WordPress (WPCS 3)
php tests/run.php   # tests de la logique (détection, robots.txt, llms.txt)
```

L'intégration continue GitHub Actions vérifie la syntaxe sur PHP 7.4 à 8.3 et lance les tests à chaque envoi.

## Structure

```
nsw-ai-visibility.php        Chargement, activation, désactivation
includes/class-bots.php      Liste des robots d'IA et détection
includes/class-robots.php    Règles ajoutées au robots.txt virtuel
includes/class-llms.php      Génération et service du llms.txt
includes/class-tracker.php   Comptage des visites, purge planifiée
includes/class-rest.php      Route REST des statistiques
includes/class-cli.php       Commandes WP-CLI
includes/class-admin.php     Écran Réglages > AI Visibility
uninstall.php                Suppression des options et de la table
```

## English summary

NSW AI Visibility lets you choose which of 14 known AI crawlers may read your site (blocking is added to the virtual robots.txt, bot by bot, with search, agent and training bots clearly separated), serves an auto-generated `/llms.txt` built from your pages, posts and SEO meta descriptions (Rank Math and Yoast aware, noindex pages excluded), and counts AI bot visits per bot and per page without storing any personal data. Stats are exposed through a REST route (`/wp-json/nsw-aiv/v1/stats`) and WP-CLI commands (`wp ai-visibility stats|pages|llms|block|allow`).

## Auteur

Développé par [Khalil Laajine](https://www.linkedin.com/in/khalillaajine/), consultant SEO et développeur WordPress à Paris ([novasiteweb.fr](https://novasiteweb.fr)).

Licence GPL-2.0-or-later.
