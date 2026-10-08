<?php
namespace Grav\Theme;

use Grav\Common\Theme;
use Grav\Theme\Aboutme\LangSync;

require_once __DIR__ . '/classes/LangSync.php';

class Aboutme extends Theme
{
    /** Netzwerke, die das Theme kennt (Schlüssel = Symbolname in macros/icons.html.twig). */
    private const NETWORKS = [
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'linkedin'  => 'LinkedIn',
        'xing'      => 'XING',
        'x'         => 'X',
        'threads'   => 'Threads',
        'bluesky'   => 'Bluesky',
        'mastodon'  => 'Mastodon',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'twitch'    => 'Twitch',
        'pinterest' => 'Pinterest',
        'github'    => 'GitHub',
        'gitlab'    => 'GitLab',
        'discord'   => 'Discord',
        'spotify'   => 'Spotify',
        'soundcloud' => 'SoundCloud',
        'bandcamp'  => 'Bandcamp',
        'etsy'      => 'Etsy',
    ];

    public static function getSubscribedEvents()
    {
        return [
            'onThemeInitialized'   => ['onThemeInitialized', 0],
            'onPageInitialized'    => ['onPageInitialized', 0],
            'onTwigInitialized'    => ['onTwigInitialized', 0],
            'onTwigSiteVariables'  => ['onTwigSiteVariables', 0],
            'onOutputGenerated'    => ['onOutputGenerated', 0],
            'onAdminSave'          => ['onAdminSave', 0],
            'onAdminAfterSave'     => ['onAdminAfterSave', 0],
        ];
    }

    /**
     * Mehrsprachige Listen (Zeitstrahl, Rubriken, Kompetenzen, Projekte, Buttons, Kennzahlen,
     * Galerie, Videos, Slider): Beim Speichern bekommt jeder Eintrag eine unsichtbare Kennung.
     * Wird die Hauptsprache gespeichert, gleicht LangSync die anderen Sprachen an. Siehe classes/LangSync.php.
     */
    public function onAdminSave($event): void
    {
        $page = $event['page'] ?? $event['object'] ?? null;
        if (!$page instanceof \Grav\Common\Page\Interfaces\PageInterface || !$this->grav['language']->enabled()) {
            return;
        }
        $template = LangSync::templateOf((string) $page->name());
        if (!isset(LangSync::LISTS[$template])) {
            return;
        }
        $lang = (string) ($page->language() ?: $this->grav['language']->getDefault());
        $main = (string) $this->grav['language']->getDefault();
        $header = json_decode((string) json_encode($page->header()), true) ?: [];
        if (LangSync::ensureUids($header, $template, $lang === $main ? '' : $lang)) {
            $page->header((object) $header);
        }
    }

    public function onAdminAfterSave($event): void
    {
        $page = $event['page'] ?? $event['object'] ?? null;
        $language = $this->grav['language'];
        if (!$page instanceof \Grav\Common\Page\Interfaces\PageInterface || !$language->enabled()) {
            return;
        }
        $main = (string) $language->getDefault();
        $lang = (string) ($page->language() ?: $main);
        if ($lang !== $main || !$page->path()) {
            return;
        }
        try {
            // Einträge ohne Kennung in anderen Sprachen werden nach Bild bzw. Position zugeordnet;
            // eine unbekannte Kennung heißt: in der Hauptsprache gelöscht
            $changed = LangSync::syncFolder((string) $page->path(), $main, (array) $language->getLanguages(), 'missing');
            if ($changed) {
                $this->grav['log']->info('aboutme: Sprachfassungen angeglichen: ' . implode(', ', $changed));
            }
        } catch (\Throwable $e) {
            $this->grav['log']->error('aboutme: Sprachabgleich fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /** Muster für E-Mail-Adressen */
    private const EMAIL_PATTERN = '[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}';

    /**
     * Schutz vor Adress-Sammlern: Jede E-Mail-Adresse in der fertigen Seite wird
     * verschleiert. Im HTML steht sie nur noch kodiert (data-m, rückwärts und Base64);
     * js/site.js setzt daraus für Besucher wieder einen normalen mailto-Link zusammen.
     * Ohne JavaScript und in der Markdown-Ausgabe (.md) erscheint „name [at] domain [dot] de“.
     * Inhalte und Admin bleiben unverändert, es betrifft nur die Ausgabe an Besucher.
     */
    public function onOutputGenerated($event): void
    {
        if (str_contains((string) $this->grav['uri']->path(), '/api/')) {
            return;
        }
        $output = $this->grav->output;
        if (!is_string($output) || !str_contains($output, '@')) {
            return;
        }
        $page = $event['page'] ?? null;
        $format = $page ? (string) $page->templateFormat() : 'html';

        if ($format === 'md') {
            $output = self::protectEmailsInText($output);
        } elseif ($format === 'html') {
            $output = self::protectEmailsInHtml($output);
        } else {
            return;
        }
        $this->grav->output = $output;
    }

    /** „name [at] domain [dot] de“ */
    private static function emailAsText(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return $local . ' [at] ' . str_replace('.', ' [dot] ', $domain);
    }

    private static function emailLink(string $email, string $label, string $query = ''): string
    {
        $isAddress = strcasecmp(trim(strip_tags($label)), $email) === 0;
        $text = $isAddress ? self::emailAsText($email) : $label;
        return '<a class="nh-mail" href="#" rel="nofollow" data-m="' . base64_encode(strrev($email)) . '"'
            . ($query !== '' ? ' data-q="' . htmlspecialchars(base64_encode($query), ENT_QUOTES) . '"' : '')
            . ($isAddress ? ' data-t="1"' : '') . '>' . $text . '</a>';
    }

    private static function protectEmailsInHtml(string $html): string
    {
        $email = self::EMAIL_PATTERN;

        // 1. mailto-Links ersetzen
        $html = (string) preg_replace_callback(
            '~<a\b[^>]*?href=(["\'])mailto:(' . $email . ')(\?[^"\']*)?\1[^>]*>(.*?)</a>~is',
            static fn ($m) => self::emailLink($m[2], $m[4], $m[3] ?? ''),
            $html
        );

        // 2. Adressen im sichtbaren Text (nicht in Tags, Skripten, Styles, Formularfeldern)
        $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>|<[^>]+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<' || !str_contains($part, '@')) {
                continue;
            }
            $parts[$i] = (string) preg_replace_callback('~' . $email . '~', static fn ($m) => self::emailLink($m[0], $m[0]), $part);
        }
        return implode('', $parts);
    }

    private static function protectEmailsInText(string $text): string
    {
        $email = self::EMAIL_PATTERN;
        // [Text](mailto:adresse) und <adresse> zuerst, damit keine kaputte Link-Syntax bleibt
        $text = (string) preg_replace_callback('~\[([^\]]*)\]\(mailto:(' . $email . ')[^)]*\)~i',
            static fn ($m) => strcasecmp(trim($m[1]), $m[2]) === 0 ? self::emailAsText($m[2]) : $m[1] . ' (' . self::emailAsText($m[2]) . ')', $text);
        $text = (string) preg_replace_callback('~<(?:mailto:)?(' . $email . ')>~i', static fn ($m) => self::emailAsText($m[1]), $text);
        return (string) preg_replace_callback('~(?:mailto:)?(' . $email . ')~i', static fn ($m) => self::emailAsText($m[1]), $text);
    }

    /** Frühere deutsche Namen der Seitentypen => heutige Namen */
    public const LEGACY_TEMPLATES = ['seite' => 'page', 'stationen' => 'timeline', 'beitrag' => 'post', 'uebersicht' => 'overview'];

    public function onThemeInitialized()
    {
        $this->migrateTemplateNames();
        $this->ensureHomePage();
        $this->seedMenuList();
        $this->migrateLangSync();
    }

    /**
     * Seiten ohne Kennungen in den Listen (ältere Inhalte, per FTP hochgeladene Dateien) einmalig
     * vorbereiten: Kennungen vergeben und die anderen Sprachen nach Bild bzw. Position zuordnen.
     * Läuft nur bei Admin-/API-Anfragen, also bevor im Admin etwas gespeichert wird.
     */
    private function migrateLangSync(): void
    {
        if (!str_contains((string) $this->grav['uri']->path(), '/api/')) {
            return;
        }
        $language = $this->grav['language'];
        if (!$language->enabled()) {
            return;
        }
        $root = $this->grav['locator']->findResource('page://', true);
        if (!$root || !is_dir($root)) {
            return;
        }
        $main = (string) $language->getDefault();
        $langs = (array) $language->getLanguages();
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $dir) {
                if (!$dir->isDir()) {
                    continue;
                }
                foreach (glob($dir->getPathname() . '/*.' . $main . '.md') ?: [] as $file) {
                    $lists = LangSync::LISTS[LangSync::templateOf($file)] ?? null;
                    if (!$lists) {
                        continue;
                    }
                    // Schnelltest ohne YAML: hat die Datei Listen, aber noch keine Kennungen?
                    $text = (string) file_get_contents($file);
                    $hasList = false;
                    foreach (array_keys($lists) as $list) {
                        if (preg_match('/^' . preg_quote($list, '/') . ':\s*$/m', $text)) {
                            $hasList = true;
                            break;
                        }
                    }
                    if ($hasList && !preg_match('/^\s+uid:/m', $text)) {
                        // Hauptsprache noch ohne Kennungen: alle Einträge der anderen Sprachen neu zuordnen
                        $changed = LangSync::syncFolder($dir->getPathname(), $main, $langs, 'all');
                        if ($changed) {
                            $this->grav['log']->info('aboutme: Kennungen für Sprachabgleich vergeben: ' . $dir->getFilename());
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->grav['log']->error('aboutme: Vorbereitung Sprachabgleich fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Seitendateien mit den früheren deutschen Namen der Seitentypen (seite.de.md, stationen.en.md …)
     * auf die heutigen Namen umbenennen (page.de.md, timeline.en.md …). Läuft vor dem Einlesen der
     * Seiten. Ein Merker im Cache-Ordner sorgt dafür, dass nur nach dem Leeren des Caches erneut gesucht wird.
     */
    private function migrateTemplateNames(): void
    {
        $locator = $this->grav['locator'];
        $root = $locator->findResource('page://', true);
        $cacheDir = $locator->findResource('cache://', true, true);
        if (!$root || !is_dir($root)) {
            return;
        }
        $marker = $cacheDir ? $cacheDir . '/aboutme-templates-v1' : null;
        if ($marker && is_file($marker)) {
            return;
        }
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            $dirs = [$root];
            foreach ($it as $item) {
                if ($item->isDir()) {
                    $dirs[] = $item->getPathname();
                }
            }
            foreach ($dirs as $dir) {
                foreach (self::LEGACY_TEMPLATES as $old => $new) {
                    foreach (glob($dir . '/' . $old . '.*md') ?: [] as $file) {
                        $target = $dir . '/' . $new . substr(basename($file), strlen($old));
                        if (is_file($target) || !@rename($file, $target)) {
                            continue;
                        }
                        if ($new === 'overview') {
                            $text = (string) file_get_contents($target);
                            $fixed = preg_replace('/^(\s*child_type:\s*)beitrag\s*$/m', '$1post', $text);
                            if ($fixed !== null && $fixed !== $text) {
                                file_put_contents($target, $fixed);
                            }
                        }
                        $this->grav['log']->info('aboutme: Seitentyp umbenannt: ' . basename($file) . ' → ' . basename($target));
                    }
                }
            }
            if ($marker) {
                @file_put_contents($marker, date('c'));
            }
        } catch (\Throwable $e) {
            $this->grav['log']->error('aboutme: Umbenennen der Seitentypen fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Sicherheitsnetz für die Startseite: Zeigt der eingestellte Alias (system.home.alias,
     * z. B. noch '/home') auf einen Seitenordner, den es nicht gibt, wird die Profilseite
     * '/ueber-mich' verwendet. Läuft vor dem Einlesen der Seiten, daher genügt ein Blick
     * auf die Ordner unter user/pages.
     */
    private function ensureHomePage(): void
    {
        $config = $this->grav['config'];
        $alias = trim((string) $config->get('system.home.alias', '/home'), '/');
        $dir = $this->grav['locator']->findResource('page://', true);
        if (!$dir || !is_dir($dir)) {
            return;
        }

        $exists = static function (string $slug) use ($dir): bool {
            if ($slug === '') {
                return false;
            }
            // Nur die erste Ebene prüfen; Ordner mit oder ohne Nummer (01.ueber-mich / ueber-mich)
            $first = explode('/', $slug)[0];
            foreach ((array) scandir($dir) as $name) {
                if ($name === $first || preg_replace('/^\d+\./', '', (string) $name) === $first) {
                    return is_dir($dir . '/' . $name);
                }
            }
            return false;
        };

        if ($exists($alias)) {
            return;
        }
        foreach (['about', 'ueber-mich'] as $candidate) {
            if ($exists($candidate)) {
                $config->set('system.home.alias', '/' . $candidate);
                $this->grav['log']->warning('aboutme: Startseite "/' . $alias . '" nicht gefunden, verwende "/' . $candidate . '". Bitte system.home.alias anpassen.');
                return;
            }
        }
    }

    /** Name des Cookies, in dem die Sprachwahl des Besuchers gespeichert wird (gesetzt von js/site.js). */
    public const LANG_COOKIE = 'aboutme_lang';

    /**
     * Sprache automatisch wählen (Themes → AboutME → Sprache): Ruft jemand eine Seite ohne
     * Sprachkürzel auf (z. B. "/" oder "/stationen"), hat noch keine Sprache gewählt und sein
     * Browser bevorzugt eine andere unterstützte Sprache als die Standardsprache, wird einmalig
     * auf die passende Adresse weitergeleitet ("/en", "/en/stationen"). Nur bei normalen
     * Seitenaufrufen (GET); Formulare, Admin und Schnittstelle bleiben unberührt.
     */
    private function autoLanguage(): void
    {
        $config = $this->grav['config'];
        $language = $this->grav['language'];
        if (!$config->get('theme.auto_language') || !$language->enabled() || $language->isLanguageInUrl()) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || isset($_GET['lang']) || isset($_COOKIE[self::LANG_COOKIE])) {
            return;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($accept === '') {
            return;   // z. B. Suchmaschinen: keine Weiterleitung
        }

        $supported = array_map('strtolower', (array) $language->getLanguages());
        $default = strtolower((string) ($language->getDefault() ?: ($supported[0] ?? '')));
        $best = $this->preferredLanguage($accept, $supported);
        if ($best === null || $best === $default) {
            return;
        }

        $uri = $this->grav['uri'];
        $path = '/' . ltrim((string) $uri->path(), '/');
        foreach (['plugins.admin2.route', 'plugins.admin.route', 'plugins.api.route'] as $key) {
            $route = '/' . trim((string) $config->get($key, ''), '/');
            if ($route !== '/' && ($path === $route || str_starts_with($path, $route . '/'))) {
                return;
            }
        }

        $target = rtrim((string) $this->grav['base_url'], '/') . '/' . $best . ($path === '/' ? '' : $path);
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $this->grav->redirect($target . ($query !== '' ? '?' . $query : ''), 302);
    }

    /** Wertet den Accept-Language-Header aus und gibt die beste unterstützte Sprache zurück. */
    private function preferredLanguage(string $header, array $supported): ?string
    {
        $ranked = [];
        foreach (explode(',', $header) as $i => $part) {
            $bits = array_map('trim', explode(';', $part));
            $code = strtolower(substr((string) $bits[0], 0, 2));
            $q = 1.0;
            foreach (array_slice($bits, 1) as $b) {
                if (str_starts_with($b, 'q=')) {
                    $q = (float) substr($b, 2);
                }
            }
            if ($code !== '' && $q > 0 && !isset($ranked[$code])) {
                $ranked[$code] = $q - $i / 1000;   // gleiche Gewichtung: Reihenfolge entscheidet
            }
        }
        arsort($ranked);
        foreach (array_keys($ranked) as $code) {
            if (in_array($code, $supported, true)) {
                return $code;
            }
        }
        // Keine bevorzugte Sprache wird angeboten: Englisch, falls vorhanden, sonst nichts tun
        return in_array('en', $supported, true) ? 'en' : null;
    }

    /**
     * Vorgelagerte Startseite: Ist sie unter Themes → AboutME eingeschaltet, zeigt die
     * Adresse "/" (bzw. "/en") die Seite /start. Die bisherige Startseite bleibt unter
     * ihrer eigenen Adresse (z. B. /ueber-mich) erreichbar.
     */
    public function onPageInitialized(): void
    {
        // Seite mit früherem deutschen Typnamen (falls das Umbenennen nicht möglich war): heutige Vorlage verwenden
        $current = $this->grav['page'] ?? null;
        if ($current && isset(self::LEGACY_TEMPLATES[(string) $current->template()])) {
            $current->template(self::LEGACY_TEMPLATES[(string) $current->template()]);
        }

        $this->autoLanguage();

        $config = $this->grav['config'];
        if (!$config->get('theme.front.enabled')) {
            return;
        }

        $page = $this->grav['page'] ?? null;
        if (!$page || !$page->home()) {
            return;
        }

        // Nur bei der reinen Startadresse tauschen, nicht bei /ueber-mich
        $path = trim((string) $this->grav['uri']->path(), '/');
        if ($path !== '') {
            return;
        }

        $route = '/' . trim((string) $config->get('theme.front.route', '/start'), '/');
        $front = $this->grav['pages']->find($route);
        if (!$front || !$front->published()) {
            return;
        }

        unset($this->grav['page']);
        $this->grav['page'] = $front;
    }

    /**
     * Menüliste im Admin vorbelegen: Ist die Liste leer, werden die Einträge des
     * automatischen Menüs eingetragen (Seiten und Reihenfolge wie gerade auf der
     * Website). Die Website sieht danach gleich aus, denn die Liste enthält genau
     * das bisherige Menü; ab dann gilt, was im Admin in der Liste steht.
     * Läuft nur bei Admin-/API-Anfragen, nie bei Besuchern.
     */
    private function seedMenuList(): void
    {
        $path = (string) $this->grav['uri']->path();
        if (!str_contains($path, '/api/')) {
            return;
        }
        $config = $this->grav['config'];
        if ($config->get('theme.nav_links')) {
            return;
        }
        $dir = $this->grav['locator']->findResource('config://themes', true, true);
        if (!$dir || !is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $file = \Grav\Common\File\CompiledYamlFile::instance($dir . '/aboutme.yaml');
        $data = $file->exists() ? (array) $file->content() : [];
        if (!empty($data['nav_links'])) {
            return;
        }
        $links = self::menuDefaults();
        if (!$links) {
            return;
        }
        // nav_mode stammt aus einer Zwischenversion und wird nicht mehr verwendet
        unset($data['nav_mode']);
        $data['nav_links'] = $links;
        $file->save($data);
        $file->free();

        $config->set('theme.nav_links', $links);
        $this->grav['log']->info('aboutme: Menüliste im Admin mit ' . count($links) . ' Einträgen des automatischen Menüs vorbelegt.');
    }

    /**
     * Einträge, die das automatische Menü gerade zeigt, in derselben
     * Reihenfolge: Bausteine der Profilseite mit Feld „Menü“, danach die sichtbaren
     * Hauptseiten. Liest die Ordner direkt, damit es auch in Admin-Anfragen ohne
     * geladene Seiten funktioniert. Texte in der Standardsprache, Übersetzungen je Sprache.
     */
    public static function menuDefaults(): array
    {
        try {
            $grav = \Grav\Common\Grav::instance();
            $config = $grav['config'];
            $dir = $grav['locator']->findResource('page://', true);
            if (!$dir || !is_dir($dir)) {
                return [];
            }

            $langs = array_values((array) $config->get('system.languages.supported', []));
            $default = (string) ($config->get('system.languages.default_lang') ?: ($langs[0] ?? ''));
            $others = array_values(array_diff($langs, [$default]));

            $homeSlug = explode('/', trim((string) $config->get('system.home.alias', '/home'), '/'))[0];
            $entries = [];
            $add = static function (array $labels, string $url) use (&$entries, $default, $others): void {
                $text = trim((string) ($labels[$default] ?? ''));
                if ($text === '') {
                    return;
                }
                $texts = [];
                foreach ($others as $lang) {
                    $t = trim((string) ($labels[$lang] ?? ''));
                    if ($t !== '' && $t !== $text) {
                        $texts[$lang] = $t;
                    }
                }
                $entry = ['text' => $text, 'url' => $url, 'new_tab' => false];
                if ($texts) {
                    $entry['texts'] = $texts;
                }
                $entries[] = $entry;
            };

            $folders = self::sortedFolders($dir);

            // 1. Bausteine der Profilseite (Sprungmarken)
            foreach ($folders as $name) {
                if (self::slugOf($name) !== $homeSlug) {
                    continue;
                }
                $homePath = $dir . '/' . $name;
                $homeHeader = self::pageHeaders($homePath, $default, [])[$default] ?? [];
                $modules = array_values(array_filter(self::sortedFolders($homePath), static fn ($n) => $n[0] === '_'));
                // Reihenfolge wie auf der Seite: content.order.custom, danach die übrigen
                $custom = (array) ($homeHeader['content']['order']['custom'] ?? []);
                if ($custom) {
                    $ordered = array_values(array_intersect($custom, $modules));
                    $modules = array_merge($ordered, array_values(array_diff($modules, $ordered)));
                }
                foreach ($modules as $module) {
                    $headers = self::pageHeaders($homePath . '/' . $module, $default, $others);
                    $main = $headers[$default] ?? [];
                    if (empty($main['menu']) || (isset($main['published']) && !$main['published'])) {
                        continue;
                    }
                    $anchor = (string) ($main['anchor'] ?? '') ?: \Grav\Common\Inflector::hyphenize((string) $main['menu']);
                    $labels = [];
                    foreach ($headers as $lang => $h) {
                        $labels[$lang] = (string) ($h['menu'] ?? '');
                    }
                    $add($labels, '#' . $anchor);
                }
            }

            // 2. Sichtbare Hauptseiten (wie pages.children.visible: Ordner mit Nummer oder visible: true)
            foreach ($folders as $name) {
                if ($name[0] === '_' || self::slugOf($name) === $homeSlug) {
                    continue;
                }
                $headers = self::pageHeaders($dir . '/' . $name, $default, $others);
                $main = $headers[$default] ?? null;
                if ($main === null || (isset($main['published']) && !$main['published'])) {
                    continue;
                }
                $visible = isset($main['visible']) ? (bool) $main['visible'] : (bool) preg_match('/^\d+\./', $name);
                if (!$visible) {
                    continue;
                }
                $labels = [];
                foreach ($headers as $lang => $h) {
                    $labels[$lang] = (string) (($h['menu'] ?? '') ?: ($h['title'] ?? ''));
                }
                $add($labels, '/' . self::slugOf($name));
            }

            return $entries;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Unterordner in Grav-Standardreihenfolge (nach Ordnername), ohne versteckte. */
    private static function sortedFolders(string $dir): array
    {
        $names = [];
        foreach ((array) scandir($dir) as $name) {
            $name = (string) $name;
            if ($name === '' || $name[0] === '.' || !is_dir($dir . '/' . $name)) {
                continue;
            }
            $names[] = $name;
        }
        natsort($names);
        return array_values($names);
    }

    private static function slugOf(string $folder): string
    {
        return (string) preg_replace('/^\d+\./', '', $folder);
    }

    /**
     * Kopfdaten einer Seite je Sprache aus den Dateien name.<lang>.md
     * (ohne Sprachen bzw. als Rückfall: name.md).
     */
    private static function pageHeaders(string $path, string $default, array $others): array
    {
        $files = glob($path . '/*.md') ?: [];
        $result = [];
        foreach (array_merge([$default], $others) as $lang) {
            $match = null;
            foreach ($files as $file) {
                $base = basename($file, '.md');
                if ($lang !== '' && str_ends_with($base, '.' . $lang)) {
                    $match = $file;
                    break;
                }
            }
            if ($match === null && $lang === $default) {
                foreach ($files as $file) {
                    if (substr_count(basename($file), '.') === 1) {
                        $match = $file;
                        break;
                    }
                }
            }
            if ($match !== null) {
                $header = \Grav\Common\File\CompiledMarkdownFile::instance($match)->header();
                $result[$lang] = is_array($header) ? $header : [];
            }
        }
        return $result;
    }

    /**
     * Stellt `nh_socials` für alle Templates bereit: die Profile in der im
     * Admin festgelegten Reihenfolge, nur Einträge mit Adresse.
     */
    public function onTwigSiteVariables()
    {
        $this->grav['twig']->twig_vars['nh_socials'] = $this->socialLinks();
    }

    private function socialLinks(): array
    {
        $config = $this->grav['config'];
        $links = [];

        foreach ((array) $config->get('theme.social_links', []) as $item) {
            $key = (string) ($item['network'] ?? '');
            $url = trim((string) ($item['url'] ?? ''));
            if ($url !== '' && isset(self::NETWORKS[$key])) {
                // "menu": oben in der Menüleiste zeigen (Schalter je Profil, ohne Angabe = ja)
                $menu = !isset($item['in_menu']) || filter_var($item['in_menu'], FILTER_VALIDATE_BOOLEAN);
                $links[] = ['key' => $key, 'label' => self::NETWORKS[$key], 'url' => $url, 'menu' => $menu];
            }
        }

        return $links;
    }

    public function onTwigInitialized()
    {
        $twig = $this->grav['twig'];

        $twig->twig->addFilter(new \Twig\TwigFilter('nh_highlight', [$this, 'highlight'], ['is_safe' => ['html']]));
        $twig->twig->addFilter(new \Twig\TwigFilter('nh_list', [$this, 'toList']));
        $twig->twig->addFilter(new \Twig\TwigFilter('nh_initials', [$this, 'initials']));
        $twig->twig->addFunction(new \Twig\TwigFunction('nh_gallery', [$this, 'galleryFor']));
    }

    /** Anfangsbuchstaben für Monogramm und Favicon: erstes und letztes Wort, z. B. „Jon Doe“ → JD */
    public function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$words) {
            return '';
        }
        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '';
        return mb_strtoupper($first . $last);
    }

    /**
     * Galerie einer Seite für die aktive Sprache. Bilder werden in der Standardsprache gepflegt
     * und erscheinen automatisch auch in den anderen Sprachen, in derselben Reihenfolge.
     * Die Galerie-Liste einer anderen Sprache dient als Übersetzung: Ein Eintrag mit demselben
     * Bild ersetzt dort Bildbeschreibung, Fotograf und Format. Bilder, die nur dort stehen,
     * kommen am Ende dazu.
     */
    public function galleryFor($page): array
    {
        $header = (array) $page->header();
        $own = array_values(array_filter((array) ($header['gallery'] ?? []), 'is_array'));
        $result = ['items' => $own, 'format' => (string) ($header['gallery_format'] ?? '')];

        $language = $this->grav['language'];
        $active = (string) $language->getActive();
        $default = (string) $language->getDefault();
        if (!$language->enabled() || $active === '' || $default === '' || $active === $default) {
            return $result;
        }

        $baseHeader = self::pageHeaders((string) $page->path(), $default, [])[$default] ?? [];
        $base = array_values(array_filter((array) ($baseHeader['gallery'] ?? []), 'is_array'));
        if (!$base) {
            return $result;
        }

        $byImage = [];
        foreach ($own as $item) {
            if (!empty($item['image'])) {
                $byImage[$item['image']] = $item;
            }
        }
        $merged = [];
        foreach ($base as $item) {
            $image = (string) ($item['image'] ?? '');
            if ($image !== '' && isset($byImage[$image])) {
                $override = array_filter($byImage[$image], static fn ($v) => $v !== '' && $v !== null);
                $merged[] = array_merge($item, $override);
                unset($byImage[$image]);
            } else {
                $merged[] = $item;
            }
        }
        foreach ($own as $item) {
            if (!empty($item['image']) && isset($byImage[$item['image']])) {
                $merged[] = $item;
            }
        }

        $result['items'] = $merged;
        if ($result['format'] === '' && !empty($baseHeader['gallery_format'])) {
            $result['format'] = (string) $baseHeader['gallery_format'];
        }
        return $result;
    }

    /**
     * Escape a headline and turn ==marked== parts into a highlighted span,
     * so the editor can mark a phrase in the admin without writing HTML.
     */
    public function highlight(?string $text): string
    {
        $escaped = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

        return preg_replace('/==(.+?)==/u', '<span class="hl">$1</span>', $escaped) ?? $escaped;
    }

    /**
     * Accept either a YAML list or a comma separated string (as typed in a
     * plain admin text field) and return a clean array of entries.
     */
    public function toList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string) $value);
        }

        return array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $items), static fn ($item) => $item !== ''));
    }
}
