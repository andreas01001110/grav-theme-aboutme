<?php
namespace Grav\Theme\Aboutme;

use Symfony\Component\Yaml\Yaml;

/**
 * Abgleich der Listen einer Seite zwischen den Sprachen.
 *
 * Die Hauptsprache bestimmt, welche Einträge es gibt und in welcher Reihenfolge. Jeder Eintrag
 * trägt eine unsichtbare Kennung (uid). Beim Abgleich bekommen die anderen Sprachen:
 * - neue Einträge mit dem Text der Hauptsprache (zum Übersetzen),
 * - die Reihenfolge der Hauptsprache,
 * - gelöschte Einträge entfernt,
 * - gemeinsame Felder (Bild, Adresse, Kategorie …) aus der Hauptsprache.
 * Übersetzte Texte bleiben erhalten. Einträge, die nur in einer anderen Sprache angelegt wurden
 * (Kennung beginnt mit dem Sprachkürzel), bleiben dort am Ende stehen.
 */
class LangSync
{
    /** Vorlage => [Liste => gemeinsame Felder] */
    public const LISTS = [
        'timeline'  => ['entries' => ['category', 'current']],
        'page'      => ['gallery' => ['image', 'format', 'credit'], 'videos' => ['year', 'url', 'poster']],
        // frühere deutsche Namen der Seitentypen (werden automatisch umbenannt, siehe aboutme.php)
        'stationen' => ['entries' => ['category', 'current']],
        'about'     => ['rubrics' => ['current', 'link_url']],
        'hero'      => ['buttons' => ['url', 'style'], 'stats' => []],
        'skills'    => ['groups' => ['icon', 'highlight']],
        'projects'  => ['projects' => ['ai', 'image', 'preview', 'link1_url', 'link2_url']],
        'seite'     => ['gallery' => ['image', 'format', 'credit'], 'videos' => ['year', 'url', 'poster']],
        'slider'    => ['slides' => ['image', 'shade', 'button_url']],
    ];

    /** Vorlage => [Gruppe => gemeinsame Felder] (feste Felder statt Listen) */
    public const OBJECTS = [
        'hero' => ['badge_top' => ['side'], 'badge_bottom' => ['side']],
    ];

    public static function newUid(string $prefix = ''): string
    {
        return ($prefix !== '' ? $prefix . '-' : '') . bin2hex(random_bytes(4));
    }

    /** Vorlagenname aus dem Dateinamen, z. B. hero.de.md → hero */
    public static function templateOf(string $file): string
    {
        return explode('.', basename($file))[0];
    }

    /**
     * Kennungen in einem Kopf vergeben (fehlende und doppelte). Gibt true zurück, wenn sich etwas
     * geändert hat. $prefix: leer für die Hauptsprache, sonst das Sprachkürzel.
     */
    public static function ensureUids(array &$header, string $template, string $prefix = ''): bool
    {
        $changed = false;
        foreach (array_keys(self::LISTS[$template] ?? []) as $list) {
            if (empty($header[$list]) || !is_array($header[$list])) {
                continue;
            }
            $seen = [];
            foreach ($header[$list] as $i => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $uid = (string) ($item['uid'] ?? '');
                if ($uid === '' || isset($seen[$uid])) {
                    $uid = self::newUid($prefix);
                    $header[$list][$i]['uid'] = $uid;
                    $changed = true;
                }
                $seen[$uid] = true;
            }
        }
        return $changed;
    }

    /**
     * Eine andere Sprache an die Hauptsprache angleichen.
     * $mode 'missing': Einträge ohne Kennung nach Bild bzw. Position zuordnen (normales Speichern).
     * $mode 'all': zusätzlich fremde Kennungen neu zuordnen. Nur für die einmalige Vorbereitung, wenn
     *              die Hauptsprache vorher noch gar keine Kennungen hatte. Beim normalen Speichern
     *              bedeutet eine unbekannte Kennung dagegen: In der Hauptsprache gelöscht.
     */
    public static function syncHeader(array $main, array $other, string $template, string $lang, string $mode = 'missing'): array
    {
        foreach (self::LISTS[$template] ?? [] as $list => $shared) {
            $mainItems = array_values(array_filter((array) ($main[$list] ?? []), 'is_array'));
            $otherItems = array_values(array_filter((array) ($other[$list] ?? []), 'is_array'));
            if (!$mainItems && !$otherItems) {
                continue;
            }

            if ($mode === 'missing' || $mode === 'all') {
                // Einträge mit Bild über das Bild zuordnen, alle anderen über die Position.
                $uidByImage = [];
                $mainUids = [];
                foreach ($mainItems as $mainItem) {
                    if (!empty($mainItem['uid'])) {
                        $mainUids[(string) $mainItem['uid']] = true;
                        if (!empty($mainItem['image'])) {
                            $uidByImage[(string) $mainItem['image']] = $mainItem['uid'];
                        }
                    }
                }
                foreach ($otherItems as $i => $item) {
                    $uid = (string) ($item['uid'] ?? '');
                    if ($uid !== '' && ($mode !== 'all' || isset($mainUids[$uid]) || str_starts_with($uid, $lang . '-'))) {
                        continue;
                    }
                    if (!empty($item['image']) && isset($uidByImage[(string) $item['image']])) {
                        $otherItems[$i]['uid'] = $uidByImage[(string) $item['image']];
                    } elseif (empty($item['image']) && isset($mainItems[$i]['uid'])) {
                        $otherItems[$i]['uid'] = $mainItems[$i]['uid'];
                    }
                }
            }

            $byUid = [];
            foreach ($otherItems as $item) {
                if (!empty($item['uid'])) {
                    $byUid[(string) $item['uid']] = $item;
                }
            }

            $result = [];
            foreach ($mainItems as $mainItem) {
                $uid = (string) ($mainItem['uid'] ?? '');
                $item = ($uid !== '' && isset($byUid[$uid])) ? $byUid[$uid] : $mainItem;
                unset($byUid[$uid]);
                // Fehlende oder leere Felder aus der Hauptsprache, gemeinsame Felder immer
                foreach ($mainItem as $key => $value) {
                    if (in_array($key, $shared, true) || !array_key_exists($key, $item) || $item[$key] === '' || $item[$key] === null || $item[$key] === []) {
                        $item[$key] = $value;
                    }
                }
                foreach ($shared as $key) {
                    if (!array_key_exists($key, $mainItem)) {
                        unset($item[$key]);
                    }
                }
                $result[] = $item;
            }
            // Nur in dieser Sprache angelegte Einträge behalten (Kennung mit Sprachkürzel)
            foreach ($byUid as $uid => $item) {
                if (str_starts_with($uid, $lang . '-')) {
                    $result[] = $item;
                }
            }
            if ($result) {
                $other[$list] = $result;
            } else {
                unset($other[$list]);
            }
        }

        foreach (self::OBJECTS[$template] ?? [] as $group => $shared) {
            $mainGroup = $main[$group] ?? null;
            if (!is_array($mainGroup)) {
                continue;
            }
            $otherGroup = is_array($other[$group] ?? null) ? $other[$group] : [];
            foreach ($mainGroup as $key => $value) {
                if (in_array($key, $shared, true) || !array_key_exists($key, $otherGroup) || $otherGroup[$key] === '' || $otherGroup[$key] === null) {
                    $otherGroup[$key] = $value;
                }
            }
            $other[$group] = $otherGroup;
        }

        return $other;
    }

    /** Datei in Kopf (Array) und Text zerlegen */
    public static function read(string $file): ?array
    {
        $text = (string) file_get_contents($file);
        if (!preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $text, $m)) {
            return null;
        }
        $header = Yaml::parse($m[1]);
        return [is_array($header) ? $header : [], $m[2]];
    }

    public static function write(string $file, array $header, string $body): void
    {
        $yaml = Yaml::dump($header, 10, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($file, "---\n" . rtrim($yaml, "\n") . "\n---\n" . $body);
    }

    /**
     * Alle Sprachfassungen eines Seitenordners angleichen. Gibt die Namen der geänderten Dateien zurück.
     * $mode: siehe syncHeader().
     */
    public static function syncFolder(string $dir, string $mainLang, array $langs, string $mode = 'missing'): array
    {
        $changed = [];
        foreach (glob($dir . '/*.' . $mainLang . '.md') ?: [] as $mainFile) {
            $template = self::templateOf($mainFile);
            if (!isset(self::LISTS[$template]) && !isset(self::OBJECTS[$template])) {
                continue;
            }
            $main = self::read($mainFile);
            if ($main === null) {
                continue;
            }
            [$mainHeader, $mainBody] = $main;
            if (self::ensureUids($mainHeader, $template)) {
                self::write($mainFile, $mainHeader, $mainBody);
                $changed[] = basename($mainFile);
            }
            foreach ($langs as $lang) {
                if ($lang === $mainLang) {
                    continue;
                }
                $otherFile = $dir . '/' . $template . '.' . $lang . '.md';
                if (!is_file($otherFile)) {
                    continue;
                }
                $other = self::read($otherFile);
                if ($other === null) {
                    continue;
                }
                [$otherHeader, $otherBody] = $other;
                $synced = self::syncHeader($mainHeader, $otherHeader, $template, $lang, $mode);
                if ($synced != $otherHeader) {
                    self::write($otherFile, $synced, $otherBody);
                    $changed[] = basename($otherFile);
                }
            }
        }
        return $changed;
    }
}
