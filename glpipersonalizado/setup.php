<?php

/**
 * Plugin glpipersonalizado para GLPI 11 e 12
 * Redireciona usuários selecionados para uma interface totalmente customizada
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_GLPIPERSONALIZADO_VERSION', '1.1.0');

/**
 * Inicialização do plugin
 */
function plugin_init_glpipersonalizado(): void {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    // CSRF: chave literal (a constante Hooks::CSRF_COMPLIANT existe no GLPI 11 e foi removida no 12)
    $PLUGIN_HOOKS['csrf_compliant']['glpipersonalizado'] = true;

    // Só continua se houver sessão válida E usuário realmente logado
    if (!isset($_SESSION['glpiID']) || empty($_SESSION['glpiID'])) {
        return;
    }

    // Verificar se glpiname existe (confirma login completo)
    if (!isset($_SESSION['glpiname']) || empty($_SESSION['glpiname'])) {
        return;
    }

    // Menu de configuração (apenas para admins)
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['glpipersonalizado'] = 'front/config.form.php';
    }

    // =========================================================================
    // REDIRECIONAMENTO PÓS-LOGIN
    // =========================================================================

    // Se já passou pela verificação nesta requisição, não verificar novamente
    if (isset($GLOBALS['GLPIPERSONALIZADO_CHECKED'])) {
        return;
    }
    $GLOBALS['GLPIPERSONALIZADO_CHECKED'] = true;

    if (!plugin_glpipersonalizado_pode_redirecionar()) {
        return;
    }

    if (!class_exists('PluginGlpipersonalizadoConfig')) {
        $config_file = Plugin::getPhpDir('glpipersonalizado') . '/inc/config.class.php';
        if (!file_exists($config_file)) {
            return;
        }
        include_once($config_file);
    }

    // Verificar se o usuário deve ser redirecionado
    if (PluginGlpipersonalizadoConfig::shouldRedirectUser((int) $_SESSION['glpiID'])) {
        $portal_url = $CFG_GLPI['root_doc'] . '/plugins/glpipersonalizado/front/portal.php';
        if (!headers_sent()) {
            header('Location: ' . $portal_url);
            exit;
        }
    }
}

/**
 * Só redireciona a navegação de páginas (GET de documento), nunca ajax, API, arquivos ou logout.
 * No GLPI 11/12 toda requisição passa pelo index.php, então a decisão usa o caminho da URL.
 */
function plugin_glpipersonalizado_pode_redirecionar(): bool {
    if (PHP_SAPI === 'cli') {
        return false;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return false;
    }
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        return false;
    }
    // Navegadores informam o destino: só documentos (página inteira) são redirecionados
    $destino = strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? 'document');
    if (!in_array($destino, ['document', ''], true)) {
        return false;
    }

    $uri      = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $caminho  = strtolower((string) parse_url($uri, PHP_URL_PATH));
    $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');

    // Páginas do próprio plugin
    if (str_contains($caminho, '/plugins/glpipersonalizado/')) {
        return false;
    }

    // noAUTO: processo de logout ou retorno
    if (isset($_GET['noAUTO']) || str_contains($consulta, 'noAUTO')) {
        return false;
    }
    if (isset($_GET['redirect']) && str_contains(urldecode((string) $_GET['redirect']), 'noAUTO')) {
        return false;
    }

    $excluidos = [
        'logout.php', 'login.php', '/ajax/', 'apirest.php', 'api.php', '/api/', 'caldav.php',
        'install.php', 'cron.php', 'document.send.php', 'css.php', 'locale.php', 'lostpassword.php',
        'updatepassword.php', 'status.php', '/front/helpdesk.faq.php', '/js/', '/lib/', '/pics/', '/build/',
        '/marketplace/'
    ];
    foreach ($excluidos as $trecho) {
        if (str_contains($caminho, $trecho)) {
            return false;
        }
    }

    // Arquivos estáticos
    if (preg_match('/\.(css|js|map|png|jpe?g|gif|svg|ico|woff2?|ttf|json)$/', $caminho)) {
        return false;
    }

    return true;
}

/**
 * Informações do plugin
 */
function plugin_version_glpipersonalizado(): array {
    return [
        'name'           => 'GLPI Personalizado',
        'version'        => PLUGIN_GLPIPERSONALIZADO_VERSION,
        'author'         => 'GLPI Salvador',
        'license'        => 'GPLv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0.0',
                'max' => '12.99.99'
            ],
            'php' => [
                'min' => '8.2'
            ]
        ]
    ];
}

/**
 * Verificar pré-requisitos
 */
function plugin_glpipersonalizado_check_prerequisites(): bool {
    return true;
}

/**
 * Verificar configuração
 */
function plugin_glpipersonalizado_check_config(): bool {
    return true;
}
