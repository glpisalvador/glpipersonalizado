<?php

/**
 * Ícones Tabler do próprio GLPI (11 ou 12) para o portal, sem o restante do framework Tabler.
 * Lê public/lib/tabler.min.css da versão instalada, separa a fonte e as classes .ti-* e guarda em cache.
 */

global $CFG_GLPI;

$origem = GLPI_ROOT . '/public/lib/tabler.min.css';
$cache  = GLPI_TMP_DIR . '/glpipersonalizado_icones_' . md5(GLPI_VERSION . '|' . (is_file($origem) ? filemtime($origem) : 0) . '|' . $CFG_GLPI['root_doc']) . '.css';

if (!is_file($cache) && is_file($origem)) {
    $css   = (string) file_get_contents($origem);
    $saida = [];

    // Fonte: caminhos relativos a public/lib viram absolutos do GLPI
    if (preg_match('/@font-face\{font-family:"?tabler-icons"?;[^}]*\}/', $css, $fonte)) {
        $saida[] = preg_replace('/url\((?!["\']?(?:https?:|\/))["\']?([^)"\']+)["\']?\)/', 'url(' . $CFG_GLPI['root_doc'] . '/lib/$1)', $fonte[0]);
    }
    if (preg_match('/\.ti\{[^}]*\}/', $css, $base)) {
        $saida[] = $base[0];
    }
    if (preg_match_all('/\.ti-[a-z0-9-]+:before\{content:"[^"]*"\}/', $css, $icones)) {
        $saida[] = implode('', $icones[0]);
    }

    if (count($saida) >= 2) {
        @file_put_contents($cache, implode("\n", $saida));
    }
}

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: private, max-age=86400');

if (is_file($cache)) {
    readfile($cache);
}
