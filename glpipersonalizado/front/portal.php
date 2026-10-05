<?php
/**
 * Portal personalizado - Catálogo de Serviços
 * Com suporte completo a validações de tickets
 * CORRIGIDO: Compatibilidade com GLPI 11 (itemtype_target/items_id_target)
 */

$GLOBALS['GLPIPERSONALIZADO_CHECKED'] = true;

// Carregado pelo GLPI 11/12 (inc/includes.php e obsoleto)

Session::checkLoginUser();

include_once(Plugin::getPhpDir('glpipersonalizado') . '/inc/config.class.php');

global $DB, $CFG_GLPI;

$user_id = Session::getLoginUserID();
$user_info = PluginGlpipersonalizadoConfig::getUserFullInfo($user_id);

// Nome formatado
$user_display_name = trim($user_info['user']['firstname'] . ' ' . $user_info['user']['realname']);
if (empty($user_display_name)) {
    $user_display_name = $user_info['user']['name'];
}

// Primeiro nome para saudação
$first_name = $user_info['user']['firstname'];
if (empty($first_name)) {
    $parts = explode(' ', $user_display_name);
    $first_name = $parts[0];
}

// URL de logout
$logout_url = $CFG_GLPI['root_doc'] . '/front/logout.php?noAUTO=1';

// Carregar serviços disponíveis para o usuário
$catalog = PluginGlpipersonalizadoConfig::getAvailableServicesByCategoryForUser($user_id);

// Categoria ativa (primeira por padrão)
$active_category_id = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
if ($active_category_id === 0 && !empty($catalog)) {
    $first_cat = reset($catalog);
    $active_category_id = $first_cat['info']['id'];
}

// Token CSRF
$csrf_token = PluginGlpipersonalizadoConfig::tokenCsrf();

// ============================================================================
// CARREGAR CONFIGURAÇÕES DE PERSONALIZAÇÃO
// ============================================================================

$portal_settings = PluginGlpipersonalizadoConfig::getAllPortalSettings();
$primary_color = $portal_settings['primary_color'] ?? '#3b82f6';
$logo_url = $portal_settings['logo_url'] ?? '';
$portal_title = $portal_settings['portal_title'] ?? 'Portal de Serviços';
$welcome_message = $portal_settings['welcome_message'] ?? '';
$header_text_color = $portal_settings['header_text_color'] ?? '#ffffff';

// Gerar variações de cor
$color_variations = PluginGlpipersonalizadoConfig::generateColorVariations($primary_color);

// ============================================================================
// PROCESSAR CRIAÇÃO DE TICKET
// ============================================================================

$new_ticket_id = 0;
$ticket_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $ticket_title = trim($_POST['ticket_title'] ?? '');
    $ticket_content = trim($_POST['ticket_content'] ?? '');

    if ($service_id > 0 && $ticket_title !== '' && $ticket_content !== '') {
        // Ticket::add nativo: regras, SLA, notificações e histórico do GLPI
        $resultado = PluginGlpipersonalizadoConfig::criarChamado($service_id, (int)$user_id, $ticket_title, $ticket_content);
        if ($resultado['ok']) {
            Html::redirect('portal.php?new=' . $resultado['id'] . ($active_category_id > 0 ? '&cat=' . $active_category_id : ''));
        }
        $ticket_error = $resultado['erro'];
    } else {
        $ticket_error = 'Preencha todos os campos obrigatórios.';
    }
}

// ============================================================================
// PROCESSAR ADIÇÃO DE COMENTÁRIO (FOLLOWUP)
// ============================================================================

$followup_success = false;
$followup_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_followup'])) {
    $ticket_id = (int)($_POST['followup_ticket_id'] ?? 0);
    $resultado = PluginGlpipersonalizadoConfig::adicionarComentario($ticket_id, (int)$user_id, trim($_POST['followup_content'] ?? ''));
    if ($resultado['ok']) {
        Html::redirect('portal.php?followup_added=' . $ticket_id . ($active_category_id > 0 ? '&cat=' . $active_category_id : ''));
    }
    $followup_error = $resultado['erro'];
}

// ============================================================================
// PROCESSAR APROVAÇÃO/RECUSA DE VALIDAÇÃO
// ============================================================================

$validation_success = false;
$validation_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_validation'])) {
    $validation_action = (string)($_POST['validation_action'] ?? '');
    $resultado = PluginGlpipersonalizadoConfig::responderValidacao(
        (int)($_POST['validation_id'] ?? 0),
        (int)$user_id,
        $validation_action,
        trim($_POST['validation_comment'] ?? '')
    );
    if ($resultado['ok']) {
        Html::redirect('portal.php?validation_processed=' . $resultado['tickets_id'] . '&action=' . $validation_action
            . ($active_category_id > 0 ? '&cat=' . $active_category_id : ''));
    }
    $validation_error = $resultado['erro'];
}
// IDs para destacar/notificar
$highlight_ticket_id = isset($_GET['new']) ? (int)$_GET['new'] : 0;
$followup_added_ticket_id = isset($_GET['followup_added']) ? (int)$_GET['followup_added'] : 0;
$validation_processed_ticket_id = isset($_GET['validation_processed']) ? (int)$_GET['validation_processed'] : 0;
$validation_processed_action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================================================
// CARREGAR TICKETS DO USUÁRIO
// ============================================================================

$user_tickets = [];
$user_is_requester = [];
$pending_validations_by_ticket = [];

$user_id_int = (int)$user_id;

// ============================================================================
// BUSCAR VALIDAÇÕES DO USUÁRIO - COMPATÍVEL COM GLPI 11
// ============================================================================
// Busca TODAS as validações onde o usuário é aprovador (pendentes E respondidas)
// GLPI 11 usa itemtype_target='User' e items_id_target para o aprovador
// Mas também pode ter dados antigos em users_id_validate

$validation_tickets = [];
$all_validations_by_ticket = []; // Todas as validações (pendentes + respondidas)
$pending_validations_by_ticket = []; // Apenas pendentes (para ações)

// Buscar validações onde o usuário é aprovador (estrutura NOVA do GLPI 11)
// Status: 2 = Aguardando, 3 = Aprovado, 4 = Recusado
$iterator_new = $DB->request([
    'SELECT' => [
        'v.id AS validation_id',
        'v.tickets_id',
        'v.users_id',
        'v.users_id_validate',
        'v.itemtype_target',
        'v.items_id_target',
        'v.status AS validation_status',
        'v.submission_date',
        'v.comment_submission',
        'v.validation_date',
        'v.comment_validation',
        'u.name AS requester_login',
        'u.realname AS requester_realname',
        'u.firstname AS requester_firstname'
    ],
    'FROM' => 'glpi_ticketvalidations AS v',
    'LEFT JOIN' => [
        'glpi_users AS u' => [
            'ON' => [
                'u' => 'id',
                'v' => 'users_id'
            ]
        ]
    ],
    'WHERE' => [
        'v.status' => [2, 3, 4], // Pendente, Aprovado, Recusado
        'v.itemtype_target' => 'User',
        'v.items_id_target' => $user_id_int
    ]
]);

foreach ($iterator_new as $row) {
    $tid = $row['tickets_id'];
    $validation_tickets[] = $tid;
    
    $requester_name = trim($row['requester_firstname'] . ' ' . $row['requester_realname']);
    if (empty($requester_name)) {
        $requester_name = $row['requester_login'];
    }
    $row['requester_name'] = $requester_name;
    
    // Adicionar em todas as validações
    if (!isset($all_validations_by_ticket[$tid])) {
        $all_validations_by_ticket[$tid] = [];
    }
    $all_validations_by_ticket[$tid][] = $row;
    
    // Se pendente, adicionar também na lista de pendentes
    if ($row['validation_status'] == 2) {
        if (!isset($pending_validations_by_ticket[$tid])) {
            $pending_validations_by_ticket[$tid] = [];
        }
        $pending_validations_by_ticket[$tid][] = $row;
    }
}

// Buscar validações onde o usuário é aprovador (estrutura ANTIGA - users_id_validate)
$iterator_old = $DB->request([
    'SELECT' => [
        'v.id AS validation_id',
        'v.tickets_id',
        'v.users_id',
        'v.users_id_validate',
        'v.itemtype_target',
        'v.items_id_target',
        'v.status AS validation_status',
        'v.submission_date',
        'v.comment_submission',
        'v.validation_date',
        'v.comment_validation',
        'u.name AS requester_login',
        'u.realname AS requester_realname',
        'u.firstname AS requester_firstname'
    ],
    'FROM' => 'glpi_ticketvalidations AS v',
    'LEFT JOIN' => [
        'glpi_users AS u' => [
            'ON' => [
                'u' => 'id',
                'v' => 'users_id'
            ]
        ]
    ],
    'WHERE' => [
        'v.status' => [2, 3, 4], // Pendente, Aprovado, Recusado
        'v.users_id_validate' => $user_id_int,
        ['v.users_id_validate' => ['>', 0]]
    ]
]);

foreach ($iterator_old as $row) {
    $tid = $row['tickets_id'];
    
    // Evitar duplicatas (caso a mesma validação apareça nas duas queries)
    $already_exists = false;
    if (isset($all_validations_by_ticket[$tid])) {
        foreach ($all_validations_by_ticket[$tid] as $existing) {
            if ($existing['validation_id'] == $row['validation_id']) {
                $already_exists = true;
                break;
            }
        }
    }
    
    if (!$already_exists) {
        $validation_tickets[] = $tid;
        
        $requester_name = trim($row['requester_firstname'] . ' ' . $row['requester_realname']);
        if (empty($requester_name)) {
            $requester_name = $row['requester_login'];
        }
        $row['requester_name'] = $requester_name;
        
        // Adicionar em todas as validações
        if (!isset($all_validations_by_ticket[$tid])) {
            $all_validations_by_ticket[$tid] = [];
        }
        $all_validations_by_ticket[$tid][] = $row;
        
        // Se pendente, adicionar também na lista de pendentes
        if ($row['validation_status'] == 2) {
            if (!isset($pending_validations_by_ticket[$tid])) {
                $pending_validations_by_ticket[$tid] = [];
            }
            $pending_validations_by_ticket[$tid][] = $row;
        }
    }
}

$validation_tickets = array_unique($validation_tickets);

// SEGUNDO: Buscar todos os tickets onde o usuário é requerente
$requester_tickets = [];
$iterator = $DB->request([
    'SELECT' => ['tickets_id'],
    'FROM' => 'glpi_tickets_users',
    'WHERE' => [
        'users_id' => $user_id_int,
        'type' => 1
    ]
]);
foreach ($iterator as $row) {
    $requester_tickets[] = $row['tickets_id'];
    $user_is_requester[$row['tickets_id']] = true;
}

// Combinar os IDs únicos (validações + requerente)
$all_ticket_ids = array_unique(array_merge($requester_tickets, $validation_tickets));

// Contar validações pendentes (para exibir no badge)
$total_pending_validations = count($validation_tickets);

if (!empty($all_ticket_ids)) {
    // Buscar dados completos dos tickets
    $iterator = $DB->request([
        'SELECT' => [
            't.id',
            't.name',
            't.content',
            't.date',
            't.date_mod',
            't.solvedate',
            't.closedate',
            't.status',
            't.type',
            't.priority',
            't.urgency',
            't.impact',
            't.entities_id',
            't.itilcategories_id',
            't.requesttypes_id',
            't.global_validation',
            't.is_deleted',
            'e.name AS entity_name',
            'e.completename AS entity_completename',
            'c.name AS category_name',
            'c.completename AS category_completename',
            'rt.name AS requesttype_name'
        ],
        'FROM' => 'glpi_tickets AS t',
        'LEFT JOIN' => [
            'glpi_entities AS e' => [
                'ON' => [
                    'e' => 'id',
                    't' => 'entities_id'
                ]
            ],
            'glpi_itilcategories AS c' => [
                'ON' => [
                    'c' => 'id',
                    't' => 'itilcategories_id'
                ]
            ],
            'glpi_requesttypes AS rt' => [
                'ON' => [
                    'rt' => 'id',
                    't' => 'requesttypes_id'
                ]
            ]
        ],
        'WHERE' => [
            't.id' => $all_ticket_ids
        ],
        'ORDER' => 't.date DESC'
    ]);

    foreach ($iterator as $row) {
        $tid = $row['id'];
        $has_pending_validation = isset($pending_validations_by_ticket[$tid]);
        $is_requester = isset($user_is_requester[$tid]);
        
        $has_any_validation = isset($all_validations_by_ticket[$tid]);
        
        if ($has_pending_validation || $has_any_validation || ($is_requester && $row['is_deleted'] == 0)) {
            $user_tickets[$tid] = $row;
            $user_tickets[$tid]['requesters'] = [];
            $user_tickets[$tid]['assignees'] = [];
            $user_tickets[$tid]['assignee_groups'] = [];
            $user_tickets[$tid]['observers'] = [];
            $user_tickets[$tid]['observer_groups'] = [];
            $user_tickets[$tid]['followups'] = [];
            $user_tickets[$tid]['pending_validations'] = $pending_validations_by_ticket[$tid] ?? [];
            $user_tickets[$tid]['all_validations'] = $all_validations_by_ticket[$tid] ?? [];
            $user_tickets[$tid]['is_requester'] = $is_requester;
            $user_tickets[$tid]['has_pending_validation_for_me'] = $has_pending_validation;
            $user_tickets[$tid]['has_any_validation_for_me'] = $has_any_validation;
        }
    }

    // Buscar atores dos tickets
    if (!empty($user_tickets)) {
        $included_ticket_ids = array_keys($user_tickets);
        
        $actors_iterator = $DB->request([
            'SELECT' => [
                'tu.tickets_id',
                'tu.type',
                'tu.users_id',
                'u.name AS user_login',
                'u.realname',
                'u.firstname'
            ],
            'FROM' => 'glpi_tickets_users AS tu',
            'LEFT JOIN' => [
                'glpi_users AS u' => [
                    'ON' => [
                        'u' => 'id',
                        'tu' => 'users_id'
                    ]
                ]
            ],
            'WHERE' => [
                'tu.tickets_id' => $included_ticket_ids
            ]
        ]);

        foreach ($actors_iterator as $actor) {
            $tid = $actor['tickets_id'];
            if (!isset($user_tickets[$tid])) continue;
            
            $actor_name = trim($actor['firstname'] . ' ' . $actor['realname']);
            if (empty($actor_name)) {
                $actor_name = $actor['user_login'];
            }
            
            switch ($actor['type']) {
                case 1:
                    $user_tickets[$tid]['requesters'][] = $actor_name;
                    break;
                case 2:
                    $user_tickets[$tid]['assignees'][] = $actor_name;
                    break;
                case 3:
                    $user_tickets[$tid]['observers'][] = $actor_name;
                    break;
            }
        }

        // Buscar grupos dos tickets
        $groups_iterator = $DB->request([
            'SELECT' => [
                'gt.tickets_id',
                'gt.type',
                'gt.groups_id',
                'g.name AS group_name',
                'g.completename AS group_completename'
            ],
            'FROM' => 'glpi_groups_tickets AS gt',
            'LEFT JOIN' => [
                'glpi_groups AS g' => [
                    'ON' => [
                        'g' => 'id',
                        'gt' => 'groups_id'
                    ]
                ]
            ],
            'WHERE' => [
                'gt.tickets_id' => $included_ticket_ids
            ]
        ]);

        foreach ($groups_iterator as $group) {
            $tid = $group['tickets_id'];
            if (!isset($user_tickets[$tid])) continue;
            
            $group_name = $group['group_completename'] ?: $group['group_name'];
            
            switch ($group['type']) {
                case 2:
                    $user_tickets[$tid]['assignee_groups'][] = $group_name;
                    break;
                case 3:
                    $user_tickets[$tid]['observer_groups'][] = $group_name;
                    break;
            }
        }

        // Buscar followups públicos
        $followups_iterator = $DB->request([
            'SELECT' => [
                'f.id',
                'f.items_id AS tickets_id',
                'f.content',
                'f.date',
                'f.users_id',
                'f.is_private',
                'u.name AS user_login',
                'u.realname',
                'u.firstname'
            ],
            'FROM' => 'glpi_itilfollowups AS f',
            'LEFT JOIN' => [
                'glpi_users AS u' => [
                    'ON' => [
                        'u' => 'id',
                        'f' => 'users_id'
                    ]
                ]
            ],
            'WHERE' => [
                'f.items_id' => $included_ticket_ids,
                'f.itemtype' => 'Ticket',
                'f.is_private' => 0
            ],
            'ORDER' => 'f.date ASC'
        ]);

        foreach ($followups_iterator as $followup) {
            $tid = $followup['tickets_id'];
            if (!isset($user_tickets[$tid])) continue;
            
            $followup_user = trim($followup['firstname'] . ' ' . $followup['realname']);
            if (empty($followup_user)) {
                $followup_user = $followup['user_login'];
            }
            $followup['user_name'] = $followup_user;
            
            $user_tickets[$tid]['followups'][] = $followup;
        }
    }
}

// Ordenar tickets por data (mais recentes primeiro)
// Priorizar tickets com validação pendente
uasort($user_tickets, function($a, $b) {
    $a_pending = !empty($a['pending_validations']) ? 1 : 0;
    $b_pending = !empty($b['pending_validations']) ? 1 : 0;
    
    if ($a_pending != $b_pending) {
        return $b_pending - $a_pending;
    }
    
    return strtotime($b['date']) - strtotime($a['date']);
});


// Status dos tickets
$ticket_status = [
    1 => ['name' => 'Novo', 'color' => $primary_color, 'bg' => $color_variations['primary_light']],
    2 => ['name' => 'Em Atendimento', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
    3 => ['name' => 'Em Atendimento', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
    4 => ['name' => 'Pendente', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
    5 => ['name' => 'Solucionado', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
    6 => ['name' => 'Fechado', 'color' => '#6b7280', 'bg' => 'rgba(107, 114, 128, 0.1)'],
];

// Status de validação
$validation_status_labels = [
    1 => ['name' => '-', 'color' => '#94a3b8', 'bg' => '#f1f5f9'],
    2 => ['name' => 'Aguardando', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
    3 => ['name' => 'Aprovado', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
    4 => ['name' => 'Recusado', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
];

// Saudação
if (!empty($welcome_message)) {
    $greeting = $welcome_message;
} else {
    $hour = (int)date('H');
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'Bom dia';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'Boa tarde';
    } else {
        $greeting = 'Boa noite';
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['glpilanguage'] ?? 'pt_BR'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($portal_title); ?> - GLPI</title>
    
    <!-- Ícones Tabler da versão do GLPI instalada (sem depender de CDN externo) -->
    <link rel="stylesheet" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/glpipersonalizado/front/icones.php?v=<?php echo urlencode(GLPI_VERSION); ?>">
    
    <style>
    :root {
        --primary-color: <?php echo $primary_color; ?>;
        --primary-rgb: <?php echo $color_variations['primary_rgb']; ?>;
        --primary-light: <?php echo $color_variations['primary_light']; ?>;
        --primary-lighter: <?php echo $color_variations['primary_lighter']; ?>;
        --primary-border: <?php echo $color_variations['primary_border']; ?>;
        --primary-icon-bg: <?php echo $color_variations['primary_icon_bg']; ?>;
        --primary-hover: <?php echo $color_variations['primary_hover']; ?>;
        --primary-dark: <?php echo $color_variations['primary_dark']; ?>;
        --header-text-color: <?php echo $header_text_color; ?>;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        font-size: 14px;
        line-height: 1.5;
        color: #333;
        background: #f5f7fa;
        min-height: 100vh;
    }

    .ti {
        font-family: 'tabler-icons' !important;
        speak: never;
        font-style: normal;
        font-weight: normal;
        font-variant: normal;
        text-transform: none;
        line-height: 1;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* HEADER */
    .portal-header {
        background: var(--primary-color);
        border-bottom: none;
        padding: 0 30px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .portal-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .portal-header-left .header-icon { font-size: 26px; color: var(--header-text-color) !important; }
    .portal-header-left .header-logo { height: 40px; max-width: 160px; object-fit: contain; }
    .portal-header-greeting { display: flex; flex-direction: column; }
    .portal-header-greeting .greeting-text { font-size: 12px; color: var(--header-text-color) !important; opacity: 0.8; font-weight: 500; }
    .portal-header-greeting .greeting-name { font-size: 16px; font-weight: 700; color: var(--header-text-color) !important; }
    .portal-header-right { display: flex; align-items: center; gap: 20px; }
    .user-info { display: flex; align-items: center; gap: 10px; }
    .user-avatar { width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--header-text-color) !important; font-weight: 600; font-size: 14px; }
    .user-name { font-size: 13px; color: var(--header-text-color) !important; font-weight: 500; }

    .btn-logout {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        color: var(--header-text-color) !important;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-logout:hover { background: rgba(255,255,255,0.25); border-color: rgba(255,255,255,0.5); }

    /* LAYOUT */
    .portal-layout { display: flex; min-height: calc(100vh - 60px); }

    /* SIDEBAR */
    .portal-sidebar {
        width: 280px;
        background: #fff;
        border-right: 1px solid #e0e5eb;
        padding: 24px 0;
        flex-shrink: 0;
    }

    .sidebar-title { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; padding: 0 24px 16px; }
    .category-list { list-style: none; }

    .category-item {
        display: flex;
        align-items: center;
        padding: 14px 24px;
        color: #475569;
        text-decoration: none;
        transition: all 0.15s;
        border-left: 3px solid transparent;
        margin-bottom: 2px;
    }

    .category-item:hover { background: #f8fafc; color: #1e293b; }
    .category-item.active { background: var(--primary-lighter); border-left-color: var(--primary-color); color: #1e293b; }
    .category-item .cat-icon { font-size: 22px; color: #64748b; margin-right: 14px; width: 24px; text-align: center; }
    .category-item.active .cat-icon { color: var(--primary-color); }
    .category-item .cat-name { flex: 1; font-size: 14px; font-weight: 500; }
    .category-item .count { font-size: 11px; color: #64748b; background: #f1f5f9; padding: 3px 10px; border-radius: 12px; font-weight: 600; }
    .category-item.active .count { background: var(--primary-icon-bg); color: var(--primary-color); }

    /* CONTENT */
    .portal-content { flex: 1; padding: 30px; overflow-y: auto; }
    .content-header { margin-bottom: 28px; }
    .content-header h2 { font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
    .content-header p { font-size: 14px; color: #64748b; }

    /* SERVICES GRID */
    .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px; }

    .service-card {
        background: var(--primary-lighter);
        border: 2px solid var(--primary-border);
        border-radius: 16px;
        padding: 24px;
        cursor: pointer;
        transition: all 0.25s ease;
        text-decoration: none;
        color: inherit;
        display: block;
        position: relative;
        overflow: hidden;
    }

    .service-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--primary-color); opacity: 0; transition: opacity 0.25s; }
    .service-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(var(--primary-rgb), 0.25); }
    .service-card:hover::before { opacity: 1; }

    .service-card-icon { width: 64px; height: 64px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; background: var(--primary-icon-bg); }
    .service-card-icon .icon { font-size: 32px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; color: var(--primary-color); }
    .service-card h3 { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 8px; }
    .service-card p { font-size: 13px; color: #64748b; line-height: 1.6; margin: 0; }

    .service-card-arrow { position: absolute; bottom: 20px; right: 20px; width: 32px; height: 32px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0; transform: translateX(-10px); transition: all 0.25s; }
    .service-card-arrow .icon { font-size: 18px; color: #64748b; }
    .service-card:hover .service-card-arrow { opacity: 1; transform: translateX(0); }

    /* TICKETS SECTION */
    .tickets-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .tickets-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
    .tickets-header h3 { font-size: 15px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 10px; }
    .tickets-header h3 i { color: #64748b; }
    .tickets-count { font-size: 11px; color: #64748b; background: #e2e8f0; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    
    .tickets-header-badges { display: flex; gap: 10px; align-items: center; }
    .validation-badge { 
        display: flex; 
        align-items: center; 
        gap: 5px; 
        background: #fef3c7; 
        color: #92400e; 
        padding: 5px 10px; 
        border-radius: 8px; 
        font-size: 11px; 
        font-weight: 600;
        animation: pulseGlow 2s ease-in-out infinite;
    }
    .validation-badge i { font-size: 14px; }
    
    @keyframes pulseGlow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
        50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
    }

    /* TICKETS TABLE - COMPACTA */
    .tickets-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    
    .tickets-table th { 
        text-align: left; 
        padding: 8px 10px; 
        font-size: 9px; 
        font-weight: 700; 
        color: #64748b; 
        text-transform: uppercase; 
        letter-spacing: 0.3px; 
        background: #f8fafc; 
        border-bottom: 1px solid #e2e8f0; 
        white-space: nowrap;
    }
    
    .tickets-table td { 
        padding: 8px 10px; 
        font-size: 11px; 
        color: #334155; 
        border-bottom: 1px solid #f1f5f9; 
        vertical-align: middle; 
    }
    
    .tickets-table tr:last-child td { border-bottom: none; }
    .tickets-table tr:hover td { background: #f8fafc; }
    
    .tickets-table tr.has-validation td { background: #fffbeb; }
    .tickets-table tr.has-validation:hover td { background: #fef3c7; }

    /* Larguras das colunas */
    .tickets-table th:nth-child(1),
    .tickets-table td:nth-child(1) { width: 85px; }
    
    .tickets-table th:nth-child(2),
    .tickets-table td:nth-child(2) { width: 12%; }
    
    .tickets-table th:nth-child(3),
    .tickets-table td:nth-child(3) { width: 14%; }
    
    .tickets-table th:nth-child(4),
    .tickets-table td:nth-child(4) { width: 12%; }
    
    .tickets-table th:nth-child(5),
    .tickets-table td:nth-child(5) { width: 10%; }
    
    .tickets-table th:nth-child(6),
    .tickets-table td:nth-child(6) { width: 70px; }
    
    .tickets-table th:nth-child(7),
    .tickets-table td:nth-child(7) { width: 140px; }
    
    .tickets-table th:nth-child(8),
    .tickets-table td:nth-child(8) { width: 10%; }
    
    .tickets-table th:nth-child(9),
    .tickets-table td:nth-child(9) { width: 95px; }
    
    .tickets-table th:nth-child(10),
    .tickets-table td:nth-child(10) { width: 50px; }

    @keyframes highlightBlink { 0%, 100% { background-color: transparent; } 50% { background-color: var(--primary-light); } }
    .tickets-table tr.highlight td { animation: highlightBlink 0.6s ease-in-out 2; }

    .ticket-id { font-weight: 700; color: var(--primary-color); font-size: 10px; }
    
    .ticket-title { 
        font-weight: 500; 
        color: #1e293b; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        font-size: 11px;
        display: block;
    }
    
    .ticket-desc { 
        font-size: 10px; 
        color: #64748b; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
        display: block;
    }
    
    .ticket-status { 
        display: inline-block; 
        padding: 2px 6px; 
        border-radius: 4px; 
        font-size: 9px; 
        font-weight: 600; 
        white-space: nowrap;
    }
    
    .ticket-actors { 
        font-size: 10px; 
        color: #64748b; 
    }
    
    .ticket-actors span { 
        display: block; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
    }
    
    .ticket-date { 
        font-size: 10px; 
        color: #64748b; 
        white-space: nowrap; 
    }
    
    .tickets-table td:nth-child(4),
    .tickets-table td:nth-child(5) {
        font-size: 9px;
        white-space: normal;
        line-height: 1.3;
        word-break: break-word;
    }
    
    .tickets-empty { padding: 40px; text-align: center; color: #64748b; }
    .tickets-empty i { font-size: 48px; opacity: 0.3; display: block; margin-bottom: 16px; }

    /* VALIDAÇÃO NA LISTA */
    .validation-cell { 
        min-width: 130px; 
    }
    
    .validation-pending-badge { 
        display: inline-flex; 
        align-items: center; 
        gap: 3px; 
        background: #fef3c7; 
        color: #92400e; 
        padding: 3px 6px; 
        border-radius: 4px; 
        font-size: 9px; 
        font-weight: 600;
        cursor: pointer;
        border: 1px solid #fcd34d;
        transition: all 0.2s;
    }
    .validation-pending-badge:hover { background: #fde68a; border-color: #f59e0b; }
    .validation-pending-badge i { font-size: 11px; }

    .validation-approved-badge { 
        display: inline-flex; 
        align-items: center; 
        gap: 3px; 
        background: #d1fae5; 
        color: #065f46; 
        padding: 3px 6px; 
        border-radius: 4px; 
        font-size: 9px; 
        font-weight: 600;
        cursor: pointer;
        border: 1px solid #6ee7b7;
        transition: all 0.2s;
    }
    .validation-approved-badge:hover { background: #a7f3d0; border-color: #34d399; }
    .validation-approved-badge i { font-size: 11px; }
    
    .validation-refused-badge { 
        display: inline-flex; 
        align-items: center; 
        gap: 3px; 
        background: #fee2e2; 
        color: #991b1b; 
        padding: 3px 6px; 
        border-radius: 4px; 
        font-size: 9px; 
        font-weight: 600;
        cursor: pointer;
        border: 1px solid #fca5a5;
        transition: all 0.2s;
    }
    .validation-refused-badge:hover { background: #fecaca; border-color: #f87171; }
    .validation-refused-badge i { font-size: 11px; }
    
    .validation-actions-inline {
        display: flex;
        gap: 4px;
    }
    
    .btn-approve-small, .btn-refuse-small {
        width: 22px;
        height: 22px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .btn-approve-small {
        background: #d1fae5;
        color: #059669;
    }
    .btn-approve-small:hover {
        background: #10b981;
        color: #fff;
    }
    
    .btn-refuse-small {
        background: #fee2e2;
        color: #dc2626;
    }
    .btn-refuse-small:hover {
        background: #ef4444;
        color: #fff;
    }
    
    .btn-approve-small i, .btn-refuse-small i { font-size: 11px; }

    .btn-view-ticket { 
        width: 26px; 
        height: 26px; 
        border: none; 
        background: #f1f5f9; 
        color: #64748b; 
        border-radius: 6px; 
        cursor: pointer; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        transition: all 0.2s; 
    }
    .btn-view-ticket:hover { background: var(--primary-color); color: #fff; }
    .btn-view-ticket i { font-size: 14px; }

    /* EMPTY CATALOG */
    .empty-catalog { text-align: center; padding: 80px 20px; color: #64748b; }
    .empty-catalog .empty-icon { font-size: 72px; opacity: 0.2; display: block; margin-bottom: 24px; color: #94a3b8; }
    .empty-catalog h3 { font-size: 20px; color: #475569; margin-bottom: 10px; font-weight: 600; }

    /* MODAIS */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-overlay.active { display: flex; }
    .modal-content { background: #fff; border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalIn 0.3s ease; }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }

    .modal-header { padding: 24px 28px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h4 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }
    .modal-close { background: #f1f5f9; border: none; font-size: 20px; cursor: pointer; color: #64748b; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .modal-close:hover { background: #e2e8f0; color: #334155; }
    .modal-body { padding: 28px; }
    .modal-footer { padding: 20px 28px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-radius: 0 0 16px 16px; }

    .form-group { margin-bottom: 24px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 8px; }
    .form-group label .required { color: #ef4444; }
    .form-group input, .form-group textarea { width: 100%; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; color: #1e293b; transition: all 0.2s; background: #fff; font-family: inherit; }
    .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15); }
    .form-group input::placeholder, .form-group textarea::placeholder { color: #94a3b8; }
    .form-group textarea { min-height: 140px; resize: vertical; }
    .form-group .form-hint { font-size: 12px; color: #64748b; margin-top: 8px; }

    .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); text-decoration: none; }
    .btn-primary:hover { background: linear-gradient(135deg, var(--primary-hover), var(--primary-dark)); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(var(--primary-rgb), 0.4); }
    .btn-secondary { padding: 12px 24px; background: #fff; color: #475569; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
    .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; color: #334155; }

    .error-message { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 10px; margin-bottom: 24px; font-size: 13px; display: flex; align-items: center; gap: 10px; }

    /* TOASTS */
    .toast-success, .toast-followup, .toast-validation { position: fixed; top: 80px; right: 20px; padding: 16px 24px; border-radius: 10px; display: flex; align-items: center; gap: 12px; font-weight: 500; z-index: 1001; animation: toastIn 0.4s ease, toastOut 0.4s ease 3s forwards; }
    .toast-success, .toast-followup { background: #10b981; color: #fff; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); }
    .toast-validation.approved { background: #10b981; color: #fff; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); }
    .toast-validation.refused { background: #ef4444; color: #fff; box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3); }
    .toast-success i, .toast-followup i, .toast-validation i { font-size: 20px; }
    @keyframes toastIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes toastOut { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(100px); } }

    /* MODAL TICKET VIEW */
    .ticket-modal-content { max-width: 800px; }
    .ticket-modal-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 24px 28px 20px; border-bottom: 1px solid #e2e8f0; }
    .ticket-modal-header-info h4 { margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: #1e293b; }
    .ticket-modal-header-info .ticket-modal-id { font-size: 13px; color: #64748b; }
    .ticket-modal-body { padding: 0; max-height: 70vh; overflow-y: auto; }
    .ticket-detail-section { padding: 20px 28px; border-bottom: 1px solid #f1f5f9; }
    .ticket-detail-section:last-child { border-bottom: none; }
    .ticket-detail-section h5 { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px; }
    .ticket-detail-section h5 i { font-size: 16px; }
    .ticket-detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .ticket-detail-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .ticket-detail-item { display: flex; flex-direction: column; gap: 4px; }
    .ticket-detail-item label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; }
    .ticket-detail-item span { font-size: 14px; color: #1e293b; }
    .ticket-content-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 14px; color: #334155; line-height: 1.6; max-height: 200px; overflow-y: auto; }

    /* FOLLOWUPS */
    .ticket-followups-list { display: flex; flex-direction: column; gap: 16px; }
    .ticket-followup-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
    .ticket-followup-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .ticket-followup-user { font-size: 13px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
    .ticket-followup-user i { color: #64748b; }
    .ticket-followup-date { font-size: 12px; color: #64748b; }
    .ticket-followup-content { font-size: 14px; color: #334155; line-height: 1.6; }
    .ticket-followup-empty { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; }
    .ticket-followup-empty i { font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5; }
    .ticket-modal-footer { padding: 16px 28px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; background: #f8fafc; border-radius: 0 0 16px 16px; }

    .ticket-add-followup { margin-top: 20px; padding-top: 20px; border-top: 1px dashed #e2e8f0; }
    .ticket-add-followup h6 { font-size: 12px; font-weight: 600; color: #64748b; margin: 0 0 12px 0; display: flex; align-items: center; gap: 6px; }
    .ticket-add-followup h6 i { font-size: 14px; }
    .followup-form-group { margin-bottom: 12px; }
.followup-form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-family: inherit; resize: vertical; min-height: 80px; transition: all 0.2s; }
.followup-form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15); }
.followup-form-actions { display: flex; justify-content: flex-end; }
.btn-send-followup { padding: 10px 20px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
.btn-send-followup:hover { background: linear-gradient(135deg, var(--primary-hover), var(--primary-dark)); transform: translateY(-1px); }
.btn-send-followup:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
/* VALIDAÇÃO NO MODAL */
.validation-section { background: #fffbeb; border: 2px solid #fcd34d; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
.validation-section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: #92400e; }
.validation-section-header i { font-size: 22px; }
.validation-item { background: #fff; border: 1px solid #fde68a; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
.validation-item:last-child { margin-bottom: 0; }
.validation-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.validation-item-requester { font-size: 13px; color: #78716c; display: flex; align-items: center; gap: 8px; }
.validation-item-requester i { font-size: 16px; }
.validation-item-date { font-size: 12px; color: #a8a29e; }
.validation-item-comment { font-size: 13px; color: #44403c; background: #fef3c7; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; line-height: 1.5; }
.validation-form { border-top: 1px dashed #fde68a; padding-top: 16px; }
.validation-form-group { margin-bottom: 12px; }
.validation-form-group label { display: block; font-size: 12px; font-weight: 600; color: #78716c; margin-bottom: 6px; }
.validation-form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #e7e5e4; border-radius: 8px; font-size: 13px; font-family: inherit; resize: vertical; min-height: 70px; }
.validation-form-group textarea:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15); }
.validation-actions { display: flex; gap: 12px; justify-content: flex-end; }
.btn-approve { padding: 10px 20px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.btn-approve:hover { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.btn-refuse { padding: 10px 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.btn-refuse:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

/* MODAL VALIDAÇÃO RÁPIDA */
.quick-validation-modal { max-width: 480px; }
.quick-validation-info { background: #f8fafc; border-radius: 10px; padding: 16px; margin-bottom: 20px; }
.quick-validation-info p { margin: 0 0 8px 0; font-size: 13px; color: #64748b; }
.quick-validation-info p:last-child { margin-bottom: 0; }
.quick-validation-info strong { color: #1e293b; }

/* RESPONSIVO */
@media (max-width: 1400px) {
    .tickets-table th:nth-child(3), 
    .tickets-table td:nth-child(3) { display: none; }
}

@media (max-width: 1200px) {
    .tickets-table th:nth-child(4), 
    .tickets-table td:nth-child(4) { display: none; }
}

@media (max-width: 1024px) {
    .tickets-table th:nth-child(5), 
    .tickets-table td:nth-child(5),
    .tickets-table th:nth-child(8), 
    .tickets-table td:nth-child(8) { display: none; }
}

@media (max-width: 768px) {
    .portal-layout { flex-direction: column; }
    .portal-sidebar { width: 100%; border-right: none; border-bottom: 1px solid #e0e5eb; padding: 16px 0; }
    .sidebar-title { display: none; }
    .category-list { display: flex; overflow-x: auto; padding: 0 16px; gap: 8px; }
    .category-item { padding: 10px 18px; white-space: nowrap; border-left: none; border-radius: 24px; border: 1px solid #e2e8f0; background: #fff; }
    .category-item.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
    .category-item.active .cat-icon, .category-item.active .count { color: #fff; }
    .category-item.active .count { background: rgba(255,255,255,0.2); }
    .category-item .cat-icon { margin-right: 8px; }
    .portal-content { padding: 20px 16px; }
    .portal-header { padding: 0 16px; }
    .user-name { display: none; }
    .services-grid { grid-template-columns: 1fr; }
    .content-header h2 { font-size: 20px; }
    .ticket-detail-grid, .ticket-detail-grid-3 { grid-template-columns: 1fr; }
    .ticket-modal-content { margin: 10px; }
    
    .tickets-table th:nth-child(9), 
    .tickets-table td:nth-child(9) { display: none; }
    
    .validation-actions-inline { display: none; }
}
</style>
</head>
<body>
<?php if ($highlight_ticket_id > 0): ?>
<div class="toast-success"><i class="ti ti-check"></i> Chamado #<?php echo $highlight_ticket_id; ?> criado com sucesso!</div>
<?php endif; ?>
<?php if ($followup_added_ticket_id > 0): ?>
<div class="toast-followup"><i class="ti ti-check"></i> Comentário adicionado ao chamado #<?php echo $followup_added_ticket_id; ?>!</div>
<?php endif; ?>
<?php if ($validation_processed_ticket_id > 0): ?>
<div class="toast-validation <?php echo ($validation_processed_action === 'approve') ? 'approved' : 'refused'; ?>">
<i class="ti ti-<?php echo ($validation_processed_action === 'approve') ? 'check' : 'x'; ?>"></i>
Validação <?php echo ($validation_processed_action === 'approve') ? 'aprovada' : 'recusada'; ?> no chamado #<?php echo $validation_processed_ticket_id; ?>!
</div>
<?php endif; ?>
<?php foreach (array_filter([$ticket_error, $followup_error, $validation_error]) as $erro_portal): ?>
<div class="toast-validation refused" style="animation: toastIn 0.4s ease, toastOut 0.4s ease 8s forwards;"><i class="ti ti-alert-circle"></i> <?php echo htmlspecialchars($erro_portal); ?></div>
<?php endforeach; ?>
<header class="portal-header">
    <div class="portal-header-left">
        <?php if (!empty($logo_url)): ?>
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="header-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
            <i class="ti ti-apps header-icon" style="display: none;"></i>
        <?php else: ?>
            <i class="ti ti-apps header-icon"></i>
        <?php endif; ?>
        <div class="portal-header-greeting">
            <span class="greeting-text"><?php echo htmlspecialchars($greeting); ?>,</span>
            <span class="greeting-name"><?php echo htmlspecialchars($first_name); ?></span>
        </div>
    </div>
    <div class="portal-header-right">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(mb_substr($user_display_name, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($user_display_name); ?></span>
        </div>
        <a href="<?php echo $logout_url; ?>" class="btn-logout"><i class="ti ti-logout"></i> Sair</a>
    </div>
</header>
<?php if (empty($catalog)): ?>
<div class="portal-layout">
    <div class="portal-content" style="display: flex; align-items: center; justify-content: center;">
        <div class="empty-catalog">
            <i class="ti ti-folder-off empty-icon"></i>
            <h3>Nenhum serviço disponível</h3>
            <p>No momento não há serviços configurados para você.<br>Entre em contato com o administrador do sistema.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="portal-layout">
    <aside class="portal-sidebar">
        <div class="sidebar-title">Catálogo de Serviços</div>
        <ul class="category-list">
            <?php foreach ($catalog as $cat_id => $cat_data): ?>
                <?php 
                $is_active = ($cat_id == $active_category_id);
                $cat_icon = trim($cat_data['info']['icon'] ?? 'ti ti-folder');
                if (empty($cat_icon)) $cat_icon = 'ti ti-folder';
                ?>
                <li>
                    <a href="?cat=<?php echo $cat_id; ?>" class="category-item <?php echo $is_active ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($cat_icon); ?> cat-icon"></i>
                        <span class="cat-name"><?php echo htmlspecialchars($cat_data['info']['name']); ?></span>
                        <span class="count"><?php echo count($cat_data['services']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
<main class="portal-content">
    <?php $current_category = $catalog[$active_category_id] ?? null; if ($current_category): ?>
        <div class="content-header">
            <h2><?php echo htmlspecialchars($current_category['info']['name']); ?></h2>
            <p>Selecione um serviço para abrir um chamado</p>
        </div>
        <div class="services-grid">
            <?php foreach ($current_category['services'] as $service): 
                $icon_class = trim($service['icon'] ?? '');
                if (empty($icon_class)) $icon_class = 'ti ti-file-text';
                $svc_name_js = htmlspecialchars(addslashes($service['name']), ENT_QUOTES);
                $svc_title_js = htmlspecialchars(addslashes($service['ticket_title'] ?? ''), ENT_QUOTES);
                $svc_desc_js = htmlspecialchars(addslashes($service['ticket_description'] ?? ''), ENT_QUOTES);
            ?>
                <a href="#" class="service-card" onclick="openTicketModal(<?php echo $service['id']; ?>, '<?php echo $svc_name_js; ?>', '<?php echo $svc_title_js; ?>', '<?php echo $svc_desc_js; ?>'); return false;">
                    <div class="service-card-icon"><i class="<?php echo htmlspecialchars($icon_class); ?> icon"></i></div>
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description'] ?: 'Clique para abrir um chamado'); ?></p>
                    <div class="service-card-arrow"><i class="ti ti-arrow-right icon"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="tickets-section">
        <div class="tickets-header">
            <h3><i class="ti ti-list-check"></i> Meus Chamados</h3>
            <div class="tickets-header-badges">
                <?php if ($total_pending_validations > 0): ?>
                    <div class="validation-badge">
                        <i class="ti ti-alert-circle"></i>
                        <?php echo $total_pending_validations; ?> validação(ões) pendente(s)
                    </div>
                <?php endif; ?>
                <span class="tickets-count"><?php echo count($user_tickets); ?> chamado(s)</span>
            </div>
        </div>
        <?php if (empty($user_tickets)): ?>
            <div class="tickets-empty"><i class="ti ti-ticket-off"></i><p>Você ainda não abriu nenhum chamado.</p></div>
        <?php else: ?>
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Entidade</th>
                        <th>Status</th>
                        <th>Validação</th>
                        <th>Atribuído</th>
                        <th>Data</th>
                        <th style="width: 80px; text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_tickets as $ticket): 
                        $status = $ticket_status[$ticket['status']] ?? ['name' => 'Desconhecido', 'color' => '#6b7280', 'bg' => '#f3f4f6'];
                        $content_text = strip_tags($ticket['content']);
                        $content_text = html_entity_decode($content_text);
                        $content_text = trim(preg_replace('/\s+/', ' ', $content_text));
                        if (mb_strlen($content_text) > 50) $content_text = mb_substr($content_text, 0, 50) . '...';
                        $assignees = !empty($ticket['assignees']) ? implode(', ', $ticket['assignees']) : '';
                        $assignee_groups = !empty($ticket['assignee_groups']) ? implode(', ', $ticket['assignee_groups']) : '';
                        $all_assignees = array_filter([$assignees, $assignee_groups]);
                        $assignees_display = !empty($all_assignees) ? implode(', ', $all_assignees) : '-';
                        $category_name = $ticket['category_completename'] ?: $ticket['category_name'] ?: '-';
                        $category_name = html_entity_decode($category_name, ENT_QUOTES, 'UTF-8');
                        $entity_name = $ticket['entity_completename'] ?: $ticket['entity_name'] ?: '-';
                        $entity_name = html_entity_decode($entity_name, ENT_QUOTES, 'UTF-8');
                        $date_formatted = date('d/m/Y H:i', strtotime($ticket['date']));
                        $is_new = ($ticket['id'] == $highlight_ticket_id);
                        $has_validation = !empty($ticket['pending_validations']);
                        $ticket_json = htmlspecialchars(json_encode($ticket, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="<?php echo $is_new ? 'highlight' : ''; ?> <?php echo $has_validation ? 'has-validation' : ''; ?>" <?php echo $is_new ? 'id="new-ticket"' : ''; ?>>
                            <td><span class="ticket-id">#<?php echo $ticket['id']; ?></span></td>
                            <td><span class="ticket-title" title="<?php echo htmlspecialchars($ticket['name']); ?>"><?php echo htmlspecialchars($ticket['name']); ?></span></td>
                            <td><span class="ticket-desc" title="<?php echo htmlspecialchars($content_text); ?>"><?php echo htmlspecialchars($content_text); ?></span></td>
                            <td><?php echo htmlspecialchars($category_name); ?></td>
                            <td><?php echo htmlspecialchars($entity_name); ?></td>
                            <td><span class="ticket-status" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['color']; ?>;"><?php echo $status['name']; ?></span></td>
                            <td class="validation-cell">
                                <?php if ($has_validation): ?>
                                    <?php $first_validation = $ticket['pending_validations'][0]; ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="validation-pending-badge" onclick='viewTicketModal(<?php echo $ticket_json; ?>)' title="Clique para ver detalhes">
                                            <i class="ti ti-alert-circle"></i> Pendente
                                        </span>
                                        <div class="validation-actions-inline">
                                            <button type="button" class="btn-approve-small" title="Aprovar" onclick="openQuickValidation(<?php echo $first_validation['validation_id']; ?>, <?php echo $ticket['id']; ?>, 'approve', '<?php echo htmlspecialchars(addslashes($ticket['name']), ENT_QUOTES); ?>')">
                                                <i class="ti ti-check"></i>
                                            </button>
                                            <button type="button" class="btn-refuse-small" title="Recusar" onclick="openQuickValidation(<?php echo $first_validation['validation_id']; ?>, <?php echo $ticket['id']; ?>, 'refuse', '<?php echo htmlspecialchars(addslashes($ticket['name']), ENT_QUOTES); ?>')">
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php elseif (!empty($ticket['all_validations'])): ?>
                                    <?php 
                                    // Mostrar status da validação já respondida
                                    $my_validation = $ticket['all_validations'][0];
                                    $val_status = (int)$my_validation['validation_status'];
                                    ?>
                                    <?php if ($val_status == 3): ?>
                                        <span class="validation-approved-badge" onclick='viewTicketModal(<?php echo $ticket_json; ?>)' title="Você aprovou esta validação">
                                            <i class="ti ti-check"></i> Aprovado
                                        </span>
                                    <?php elseif ($val_status == 4): ?>
                                        <span class="validation-refused-badge" onclick='viewTicketModal(<?php echo $ticket_json; ?>)' title="Você recusou esta validação">
                                            <i class="ti ti-x"></i> Recusado
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="ticket-actors"><span title="<?php echo htmlspecialchars($assignees_display); ?>"><?php echo htmlspecialchars($assignees_display); ?></span></td>
                            <td class="ticket-date"><?php echo $date_formatted; ?></td>
                            <td style="text-align: center;"><button type="button" class="btn-view-ticket" onclick='viewTicketModal(<?php echo $ticket_json; ?>)' title="Visualizar"><i class="ti ti-eye"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>
</div>
<!-- MODAL CRIAR TICKET -->
<div class="modal-overlay" id="ticketModal">
    <div class="modal-content">
        <form method="post" action="portal.php<?php echo $active_category_id > 0 ? '?cat=' . $active_category_id : ''; ?>">
            <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="create_ticket" value="1">
            <input type="hidden" name="service_id" id="modal_service_id" value="">
            <div class="modal-header">
                <h4 id="modal_title">Abrir Chamado</h4>
                <button type="button" class="modal-close" onclick="closeModal()"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($ticket_error)): ?>
                    <div class="error-message"><i class="ti ti-alert-circle"></i> <?php echo htmlspecialchars($ticket_error); ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Título do Chamado <span class="required">*</span></label>
                    <input type="text" name="ticket_title" id="modal_ticket_title" required placeholder="Descreva brevemente o problema ou solicitação">
                </div>
                <div class="form-group">
                    <label>Descrição <span class="required">*</span></label>
                    <textarea name="ticket_content" id="modal_ticket_content" required placeholder="Descreva em detalhes sua solicitação..."></textarea>
                    <div class="form-hint">Quanto mais detalhes você fornecer, mais rápido poderemos atendê-lo.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="ti ti-send"></i> Enviar Chamado</button>
            </div>
        </form>
    </div>
</div>
<!-- MODAL VISUALIZAR TICKET -->
<div class="modal-overlay" id="ticketViewModal">
    <div class="modal-content ticket-modal-content">
        <div class="ticket-modal-header">
            <div class="ticket-modal-header-info">
                <h4 id="ticketViewTitle">Título do Ticket</h4>
                <span class="ticket-modal-id" id="ticketViewId">#0000000</span>
            </div>
            <button type="button" class="modal-close" onclick="closeTicketViewModal()"><i class="ti ti-x"></i></button>
        </div>
        <div class="ticket-modal-body">
            <!-- Seção de Validação (aparece apenas se houver validações pendentes) -->
            <div class="ticket-detail-section" id="ticketValidationSection" style="display: none;">
                <div class="validation-section">
                    <div class="validation-section-header">
                        <i class="ti ti-alert-circle"></i> 
                        Validação Pendente - Sua Aprovação é Necessária
                    </div>
                    <div id="ticketValidationItems"></div>
                </div>
            </div>
        <div class="ticket-detail-section">
            <h5><i class="ti ti-info-circle"></i> Informações Gerais</h5>
            <div class="ticket-detail-grid">
                <div class="ticket-detail-item"><label>Status</label><span id="ticketViewStatus">-</span></div>
                <div class="ticket-detail-item"><label>Tipo</label><span id="ticketViewType">-</span></div>
                <div class="ticket-detail-item"><label>Entidade</label><span id="ticketViewEntity">-</span></div>
                <div class="ticket-detail-item"><label>Categoria</label><span id="ticketViewCategory">-</span></div>
                <div class="ticket-detail-item"><label>Origem da Requisição</label><span id="ticketViewRequestType">-</span></div>
                <div class="ticket-detail-item"><label>Data de Abertura</label><span id="ticketViewDate">-</span></div>
            </div>
        </div>
        <div class="ticket-detail-section">
            <h5><i class="ti ti-flag"></i> Prioridade</h5>
            <div class="ticket-detail-grid-3">
                <div class="ticket-detail-item"><label>Prioridade</label><span id="ticketViewPriority">-</span></div>
                <div class="ticket-detail-item"><label>Urgência</label><span id="ticketViewUrgency">-</span></div>
                <div class="ticket-detail-item"><label>Impacto</label><span id="ticketViewImpact">-</span></div>
            </div>
        </div>
        <div class="ticket-detail-section">
            <h5><i class="ti ti-users"></i> Pessoas Envolvidas</h5>
            <div class="ticket-detail-grid">
                <div class="ticket-detail-item"><label>Requerente(s)</label><span id="ticketViewRequesters">-</span></div>
                <div class="ticket-detail-item"><label>Atribuído a</label><span id="ticketViewAssignees">-</span></div>
                <div class="ticket-detail-item"><label>Observador(es)</label><span id="ticketViewObservers">-</span></div>
                <div class="ticket-detail-item"><label>Grupo(s) Observador</label><span id="ticketViewObserverGroups">-</span></div>
            </div>
        </div>
        <div class="ticket-detail-section">
            <h5><i class="ti ti-file-text"></i> Descrição</h5>
            <div class="ticket-content-box" id="ticketViewContent">-</div>
        </div>
        <div class="ticket-detail-section">
            <h5><i class="ti ti-messages"></i> Acompanhamentos</h5>
            <div class="ticket-followups-list" id="ticketViewFollowups">
                <div class="ticket-followup-empty"><i class="ti ti-message-off"></i> Nenhum acompanhamento público.</div>
            </div>
            <div class="ticket-add-followup" id="ticketAddFollowupSection">
                <h6><i class="ti ti-message-plus"></i> Adicionar Comentário</h6>
                <form method="post" action="portal.php<?php echo $active_category_id > 0 ? '?cat=' . $active_category_id : ''; ?>" id="formAddFollowup">
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="add_followup" value="1">
                    <input type="hidden" name="followup_ticket_id" id="followupTicketId" value="">
                    <div class="followup-form-group">
                        <textarea name="followup_content" id="followupContent" placeholder="Digite seu comentário..." required></textarea>
                    </div>
                    <div class="followup-form-actions">
                        <button type="submit" class="btn-send-followup" id="btnSendFollowup"><i class="ti ti-send"></i> Enviar Comentário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="ticket-modal-footer">
        <button type="button" class="btn-secondary" onclick="closeTicketViewModal()">Fechar</button>
    </div>
</div>
</div>
<!-- MODAL VALIDAÇÃO RÁPIDA -->
<div class="modal-overlay" id="quickValidationModal">
    <div class="modal-content quick-validation-modal">
        <form method="post" action="portal.php<?php echo $active_category_id > 0 ? '?cat=' . $active_category_id : ''; ?>" id="formQuickValidation">
            <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="process_validation" value="1">
            <input type="hidden" name="validation_id" id="quickValidationId" value="">
            <input type="hidden" name="validation_action" id="quickValidationAction" value="">
        <div class="modal-header">
            <h4 id="quickValidationTitle">Validar Chamado</h4>
            <button type="button" class="modal-close" onclick="closeQuickValidationModal()"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="quick-validation-info">
                <p><strong>Chamado:</strong> <span id="quickValidationTicketName"></span></p>
                <p><strong>ID:</strong> #<span id="quickValidationTicketId"></span></p>
            </div>
            
            <div class="form-group">
                <label id="quickValidationLabel">Comentário (opcional)</label>
                <textarea name="validation_comment" id="quickValidationComment" placeholder="Adicione um comentário sobre sua decisão..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeQuickValidationModal()">Cancelar</button>
            <button type="submit" class="btn-refuse" id="btnQuickRefuse" style="display: none;"><i class="ti ti-x"></i> Recusar</button>
            <button type="submit" class="btn-approve" id="btnQuickApprove" style="display: none;"><i class="ti ti-check"></i> Aprovar</button>
        </div>
    </form>
</div>
</div>
<?php endif; ?>
<script>
function openTicketModal(serviceId, serviceName, defaultTitle, defaultDescription) {
    document.getElementById('modal_service_id').value = serviceId;
    document.getElementById('modal_title').textContent = serviceName;
    document.getElementById('modal_ticket_title').value = defaultTitle || '';
    document.getElementById('modal_ticket_content').value = defaultDescription || '';
    document.getElementById('ticketModal').classList.add('active');
    setTimeout(function() { document.getElementById('modal_ticket_title').focus(); }, 100);
}
function closeModal() { document.getElementById('ticketModal').classList.remove('active'); }
document.getElementById('ticketModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
var priorityLabels = { 1: 'Muito baixa', 2: 'Baixa', 3: 'Média', 4: 'Alta', 5: 'Muito alta' };
var typeLabels = { 1: 'Incidente', 2: 'Requisição' };
var statusLabels = { 1: 'Novo', 2: 'Em Atendimento', 3: 'Pendente', 4: 'Solucionado', 5: 'Fechado', 6: 'Fechado' };
function viewTicketModal(ticket) {
document.getElementById('followupTicketId').value = ticket.id;
document.getElementById('followupContent').value = '';
document.getElementById('ticketViewTitle').textContent = ticket.name || '-';
document.getElementById('ticketViewId').textContent = '#' + ticket.id;
document.getElementById('ticketViewStatus').textContent = statusLabels[ticket.status] || '-';
document.getElementById('ticketViewType').textContent = typeLabels[ticket.type] || '-';
var entityName = ticket.entity_completename || ticket.entity_name || '-';
document.getElementById('ticketViewEntity').textContent = decodeHtmlEntities(entityName);

var categoryName = ticket.category_completename || ticket.category_name || '-';
document.getElementById('ticketViewCategory').textContent = decodeHtmlEntities(categoryName);

document.getElementById('ticketViewRequestType').textContent = ticket.requesttype_name || '-';
document.getElementById('ticketViewDate').textContent = formatDate(ticket.date);
document.getElementById('ticketViewPriority').textContent = priorityLabels[ticket.priority] || '-';
document.getElementById('ticketViewUrgency').textContent = priorityLabels[ticket.urgency] || '-';
document.getElementById('ticketViewImpact').textContent = priorityLabels[ticket.impact] || '-';
document.getElementById('ticketViewRequesters').textContent = (ticket.requesters && ticket.requesters.length > 0) ? ticket.requesters.join(', ') : '-';

var assignees = [];
if (ticket.assignees && ticket.assignees.length > 0) assignees = assignees.concat(ticket.assignees);
if (ticket.assignee_groups && ticket.assignee_groups.length > 0) assignees = assignees.concat(ticket.assignee_groups);
document.getElementById('ticketViewAssignees').textContent = assignees.length > 0 ? assignees.join(', ') : '-';

document.getElementById('ticketViewObservers').textContent = (ticket.observers && ticket.observers.length > 0) ? ticket.observers.join(', ') : '-';
document.getElementById('ticketViewObserverGroups').textContent = (ticket.observer_groups && ticket.observer_groups.length > 0) ? ticket.observer_groups.join(', ') : '-';

var content = ticket.content || '-';
document.getElementById('ticketViewContent').innerHTML = decodeHtmlEntities(content);

// Followups
var followupsContainer = document.getElementById('ticketViewFollowups');
if (ticket.followups && ticket.followups.length > 0) {
    var followupsHtml = '';
    ticket.followups.forEach(function(followup) {
        var followupContent = decodeHtmlEntities(followup.content || '');
        followupsHtml += '<div class="ticket-followup-item">';
        followupsHtml += '<div class="ticket-followup-header">';
        followupsHtml += '<span class="ticket-followup-user"><i class="ti ti-user"></i> ' + escapeHtml(followup.user_name || 'Sistema') + '</span>';
        followupsHtml += '<span class="ticket-followup-date">' + formatDate(followup.date) + '</span>';
        followupsHtml += '</div>';
        followupsHtml += '<div class="ticket-followup-content">' + followupContent + '</div>';
        followupsHtml += '</div>';
    });
    followupsContainer.innerHTML = followupsHtml;
} else {
    followupsContainer.innerHTML = '<div class="ticket-followup-empty"><i class="ti ti-message-off"></i> Nenhum acompanhamento público.</div>';
}

// Validações pendentes
var validationSection = document.getElementById('ticketValidationSection');
var validationItems = document.getElementById('ticketValidationItems');

if (ticket.pending_validations && ticket.pending_validations.length > 0) {
    validationSection.style.display = 'block';
    var validationsHtml = '';
    
    ticket.pending_validations.forEach(function(validation) {
        validationsHtml += '<div class="validation-item">';
        validationsHtml += '<div class="validation-item-header">';
        validationsHtml += '<span class="validation-item-requester"><i class="ti ti-user"></i> Solicitado por: ' + escapeHtml(validation.requester_name || 'Sistema') + '</span>';
        validationsHtml += '<span class="validation-item-date">' + formatDate(validation.submission_date) + '</span>';
        validationsHtml += '</div>';
        
        if (validation.comment_submission) {
            validationsHtml += '<div class="validation-item-comment">' + decodeHtmlEntities(validation.comment_submission) + '</div>';
        }
        
        validationsHtml += '<form method="post" action="portal.php<?php echo $active_category_id > 0 ? '?cat=' . $active_category_id : ''; ?>" class="validation-form">';
        validationsHtml += '<input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">';
        validationsHtml += '<input type="hidden" name="process_validation" value="1">';
        validationsHtml += '<input type="hidden" name="validation_id" value="' + validation.validation_id + '">';
        validationsHtml += '<div class="validation-form-group">';
        validationsHtml += '<label>Comentário (obrigatório para recusar)</label>';
        validationsHtml += '<textarea name="validation_comment" placeholder="Adicione um comentário sobre sua decisão..."></textarea>';
        validationsHtml += '</div>';
        validationsHtml += '<div class="validation-actions">';
        validationsHtml += '<button type="submit" name="validation_action" value="refuse" class="btn-refuse" onclick="this.form.validation_comment.required = true;"><i class="ti ti-x"></i> Recusar</button>';
        validationsHtml += '<button type="submit" name="validation_action" value="approve" class="btn-approve" onclick="this.form.validation_comment.required = false;"><i class="ti ti-check"></i> Aprovar</button>';
        validationsHtml += '</div>';
        validationsHtml += '</form>';
        validationsHtml += '</div>';
    });
    
    validationItems.innerHTML = validationsHtml;
} else {
    validationSection.style.display = 'none';
    validationItems.innerHTML = '';
}

// Verificar se pode adicionar followup
checkTicketStatus(ticket.status);

document.getElementById('ticketViewModal').classList.add('active');
}
function closeTicketViewModal() { document.getElementById('ticketViewModal').classList.remove('active'); }
function checkTicketStatus(status) {
var closedStatuses = [5, 6]; // Solucionado e Fechado (Pendente = 4 aceita comentário)
var addFollowupSection = document.getElementById('ticketAddFollowupSection');
if (closedStatuses.includes(parseInt(status))) {
addFollowupSection.style.display = 'none';
} else {
addFollowupSection.style.display = 'block';
document.getElementById('followupContent').disabled = false;
document.getElementById('btnSendFollowup').disabled = false;
}
}
// Modal de validação rápida
function openQuickValidation(validationId, ticketId, action, ticketName) {
document.getElementById('quickValidationId').value = validationId;
document.getElementById('quickValidationAction').value = action;
document.getElementById('quickValidationTicketId').textContent = ticketId;
document.getElementById('quickValidationTicketName').textContent = ticketName;
document.getElementById('quickValidationComment').value = '';
// Recusa exige motivo (regra nativa do GLPI)
document.getElementById('quickValidationComment').required = (action === 'refuse');
document.getElementById('quickValidationLabel').textContent = action === 'refuse' ? 'Motivo da recusa (obrigatório)' : 'Comentário (opcional)';
var btnApprove = document.getElementById('btnQuickApprove');
var btnRefuse = document.getElementById('btnQuickRefuse');
if (action === 'approve') {
    document.getElementById('quickValidationTitle').textContent = 'Aprovar Validação';
    btnApprove.style.display = 'inline-flex';
    btnRefuse.style.display = 'none';
} else {
    document.getElementById('quickValidationTitle').textContent = 'Recusar Validação';
    btnApprove.style.display = 'none';
    btnRefuse.style.display = 'inline-flex';
}

document.getElementById('quickValidationModal').classList.add('active');
}
function closeQuickValidationModal() {
document.getElementById('quickValidationModal').classList.remove('active');
}
document.getElementById('quickValidationModal').addEventListener('click', function(e) {
if (e.target === this) closeQuickValidationModal();
});
function formatDate(dateStr) {
if (!dateStr) return '-';
var date = new Date(dateStr);
if (isNaN(date.getTime())) return dateStr;
var day = String(date.getDate()).padStart(2, '0');
var month = String(date.getMonth() + 1).padStart(2, '0');
var year = date.getFullYear();
var hours = String(date.getHours()).padStart(2, '0');
var minutes = String(date.getMinutes()).padStart(2, '0');
return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
}
function decodeHtmlEntities(str) {
if (!str) return '';
var textarea = document.createElement('textarea');
textarea.innerHTML = str;
return textarea.value;
}
function escapeHtml(str) {
if (!str) return '';
var div = document.createElement('div');
div.textContent = str;
return div.innerHTML;
}
document.getElementById('ticketViewModal').addEventListener('click', function(e) { if (e.target === this) closeTicketViewModal(); });
document.addEventListener('keydown', function(e) {
if (e.key === 'Escape') { closeModal(); closeTicketViewModal(); closeQuickValidationModal(); }
});
document.getElementById('formAddFollowup').addEventListener('submit', function(e) {
var content = document.getElementById('followupContent').value.trim();
if (!content) { e.preventDefault(); alert('Por favor, digite um comentário.'); return false; }
document.getElementById('btnSendFollowup').disabled = true;
document.getElementById('btnSendFollowup').innerHTML = '<i class="ti ti-loader"></i> Enviando...';
});
<?php if ($highlight_ticket_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
var newTicket = document.getElementById('new-ticket');
if (newTicket) { setTimeout(function() { newTicket.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 500); }
});
<?php endif; ?>
</script>
</body>
</html>