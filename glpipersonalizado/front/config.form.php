<?php

/**
 * Página de configurações do plugin glpipersonalizado
 * Aba: Usuários e Acesso
 */

// Carregado pelo GLPI 11/12 (inc/includes.php e obsoleto)

Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
    PluginGlpipersonalizadoConfig::negarAcesso();
}

global $DB;

include_once(Plugin::getPhpDir('glpipersonalizado') . '/inc/config.class.php');

// ============================================================================
// PROCESSAR POST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_action'])) {

    $save_action = $_POST['save_action'];

    switch ($save_action) {
        case 'save_users':
            $users = $_POST['selected_users'] ?? [];
            PluginGlpipersonalizadoConfig::saveSelectedUsers($users);
            Session::addMessageAfterRedirect('Usuários salvos com sucesso.', true, INFO);
            break;

        case 'save_groups':
            $groups = $_POST['selected_groups'] ?? [];
            PluginGlpipersonalizadoConfig::saveSelectedGroups($groups);
            Session::addMessageAfterRedirect('Grupos salvos com sucesso.', true, INFO);
            break;

        case 'save_profiles':
            $profiles = $_POST['selected_profiles'] ?? [];
            PluginGlpipersonalizadoConfig::saveSelectedProfiles($profiles);
            Session::addMessageAfterRedirect('Perfis salvos com sucesso.', true, INFO);
            break;

        case 'save_portal_settings':
            $settings = [
                'primary_color' => trim($_POST['primary_color'] ?? '#3b82f6'),
                'logo_url' => trim($_POST['logo_url'] ?? ''),
                'portal_title' => trim($_POST['portal_title'] ?? 'Portal de Serviços'),
                'welcome_message' => trim($_POST['welcome_message'] ?? '')
            ];
            PluginGlpipersonalizadoConfig::saveAllPortalSettings($settings);
            Session::addMessageAfterRedirect('Configurações do portal salvas com sucesso.', true, INFO);
            break;
    }

    Html::back();
}

// ============================================================================
// CARREGAR DADOS
// ============================================================================

$selected_users    = PluginGlpipersonalizadoConfig::getSelectedUsers();
$selected_groups   = PluginGlpipersonalizadoConfig::getSelectedGroups();
$selected_profiles = PluginGlpipersonalizadoConfig::getSelectedProfiles();
$portal_settings   = PluginGlpipersonalizadoConfig::getAllPortalSettings();

// USUÁRIOS
$users_selected = [];
$users_unselected = [];

$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'realname', 'firstname'],
    'FROM'   => 'glpi_users',
    'WHERE'  => [
        'is_active'  => 1,
        'is_deleted' => 0
    ],
    'ORDER'  => 'realname ASC, firstname ASC, name ASC'
]);

foreach ($iterator as $row) {
    $display = trim($row['firstname'] . ' ' . $row['realname']);
    if (empty($display)) {
        $display = $row['name'];
    } else {
        $display .= ' (' . $row['name'] . ')';
    }

    if (in_array($row['id'], $selected_users)) {
        $users_selected[$row['id']] = $display;
    } else {
        $users_unselected[$row['id']] = $display;
    }
}

$all_users = $users_selected + $users_unselected;

// GRUPOS
$groups_selected = [];
$groups_unselected = [];

$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'completename'],
    'FROM'   => 'glpi_groups',
    'ORDER'  => 'completename ASC'
]);

foreach ($iterator as $row) {
    $display = $row['completename'] ?: $row['name'];

    if (in_array($row['id'], $selected_groups)) {
        $groups_selected[$row['id']] = $display;
    } else {
        $groups_unselected[$row['id']] = $display;
    }
}

$all_groups = $groups_selected + $groups_unselected;

// PERFIS
$profiles_selected = [];
$profiles_unselected = [];

$iterator = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM'   => 'glpi_profiles',
    'ORDER'  => 'name ASC'
]);

foreach ($iterator as $row) {
    if (in_array($row['id'], $selected_profiles)) {
        $profiles_selected[$row['id']] = $row['name'];
    } else {
        $profiles_unselected[$row['id']] = $row['name'];
    }
}

$all_profiles = $profiles_selected + $profiles_unselected;

// Paleta de cores predefinidas
$color_palette = [
    '#3b82f6' => 'Azul',
    '#6366f1' => 'Índigo',
    '#8b5cf6' => 'Violeta',
    '#a855f7' => 'Púrpura',
    '#d946ef' => 'Fúcsia',
    '#ec4899' => 'Rosa',
    '#f43f5e' => 'Vermelho Rosa',
    '#ef4444' => 'Vermelho',
    '#f97316' => 'Laranja',
    '#f59e0b' => 'Âmbar',
    '#eab308' => 'Amarelo',
    '#84cc16' => 'Lima',
    '#22c55e' => 'Verde',
    '#10b981' => 'Esmeralda',
    '#14b8a6' => 'Teal',
    '#06b6d4' => 'Ciano',
    '#0ea5e9' => 'Azul Claro',
    '#64748b' => 'Cinza Azulado',
    '#71717a' => 'Cinza',
    '#78716c' => 'Cinza Quente',
];

$csrf_token = PluginGlpipersonalizadoConfig::tokenCsrf();

// ============================================================================
// EXIBIR PÁGINA
// ============================================================================

Html::header('GLPI Personalizado - Configurações', $_SERVER['PHP_SELF'], 'config', 'PluginGlpipersonalizadoConfig');

?>

<style>
.gp-container {
    max-width: 1400px;
    margin: 15px auto;
    padding: 0 15px;
}

.gp-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e0e5eb;
    margin-bottom: 20px;
}

.gp-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    font-size: 14px;
    font-weight: 500;
    color: #5a6f8a;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.gp-tab:hover {
    color: #2c3e50;
    background: rgba(0,0,0,0.02);
}

.gp-tab.active {
    color: #2c3e50;
    border-bottom-color: #2c3e50;
}

.gp-tab i {
    font-size: 18px;
}

.gp-header {
    background: #f8f9fa;
    border: 1px solid #e0e5eb;
    padding: 18px 22px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.gp-header h2 {
    margin: 0 0 6px 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.gp-header p {
    margin: 0;
    font-size: 13px;
    color: #5a6f8a;
}

.gp-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.gp-col {
    flex: 1;
    min-width: 0;
}

.gp-box {
    background: #fff;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.gp-box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e5eb;
}

.gp-box-header h4 {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
}

.gp-box-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.gp-search {
    margin-bottom: 10px;
}

.gp-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #dce1e8;
    border-radius: 4px;
    font-size: 13px;
}

.gp-search input:focus {
    outline: none;
    border-color: #5a6f8a;
}

.gp-actions {
    display: flex;
    gap: 15px;
    font-size: 11px;
    margin-bottom: 8px;
}

.gp-actions a {
    color: #5a6f8a;
    cursor: pointer;
    text-decoration: none;
}

.gp-actions a:hover {
    text-decoration: underline;
    color: #2c3e50;
}

.gp-list {
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    max-height: 320px;
    overflow-y: auto;
    flex: 1;
    background: #fafbfc;
}

.gp-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
    font-size: 12px;
    background: #fff;
}

.gp-item:last-child {
    border-bottom: none;
}

.gp-item:hover {
    background: #f5f7fa;
}

.gp-item.selected {
    background: rgba(44, 62, 80, 0.06);
    border-left: 3px solid #2c3e50;
    padding-left: 9px;
}

.gp-item.hidden {
    display: none;
}

.gp-item input[type='checkbox'] {
    margin-right: 10px;
    cursor: pointer;
}

.gp-item label {
    flex: 1;
    margin: 0;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #333;
}

.gp-footer {
    padding: 10px 12px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    font-size: 11px;
    color: #5a6f8a;
}

.gp-footer b {
    color: #2c3e50;
}

.btn-save {
    padding: 6px 16px;
    background: #2c3e50;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
}

.btn-save:hover {
    background: #1a252f;
}

.info-box {
    background: #e8f4fd;
    border: 1px solid #b8daff;
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #004085;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.info-box i {
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.info-box p {
    margin: 0;
}

/* Estilos para a aba de personalização */
.portal-settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.settings-section {
    background: #fff;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
    padding: 20px;
}

.settings-section h4 {
    margin: 0 0 16px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px solid #eef1f5;
}

.settings-section h4 i {
    font-size: 18px;
    color: #5a6f8a;
}

.form-group {
    margin-bottom: 16px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 6px;
}

.form-group input[type='text'],
.form-group input[type='url'],
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dce1e8;
    border-radius: 4px;
    font-size: 13px;
    color: #333;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #5a6f8a;
}

.form-group .form-hint {
    font-size: 11px;
    color: #7a8599;
    margin-top: 4px;
}

.color-palette {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}

.color-option {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 8px;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.2s;
    position: relative;
}

.color-option:hover {
    transform: scale(1.1);
    z-index: 1;
}

.color-option.selected {
    border-color: #2c3e50;
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px #2c3e50;
}

.color-option.selected::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    font-size: 14px;
    font-weight: bold;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.custom-color-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.custom-color-row input[type='color'] {
    width: 50px;
    height: 36px;
    border: 1px solid #dce1e8;
    border-radius: 4px;
    cursor: pointer;
    padding: 2px;
}

.custom-color-row input[type='text'] {
    flex: 1;
}

.logo-preview-container {
    margin-top: 12px;
    padding: 16px;
    background: #f8f9fa;
    border: 1px dashed #dce1e8;
    border-radius: 6px;
    text-align: center;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-preview-container img {
    max-height: 60px;
    max-width: 100%;
    object-fit: contain;
}

.logo-preview-container .no-logo {
    color: #7a8599;
    font-size: 12px;
}

.portal-preview {
    background: #f8f9fa;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
    padding: 20px;
    margin-top: 20px;
}

.portal-preview h4 {
    margin: 0 0 16px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-box {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.preview-header {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid #eee;
}

.preview-header .preview-logo {
    height: 32px;
    max-width: 120px;
    object-fit: contain;
}

.preview-header .preview-greeting {
    flex: 1;
}

.preview-header .preview-greeting small {
    font-size: 10px;
    color: #64748b;
    display: block;
}

.preview-header .preview-greeting strong {
    font-size: 13px;
    color: #1e293b;
}

.preview-sidebar-item {
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    border-left: 3px solid transparent;
}

.preview-sidebar-item.active {
    border-left-color: var(--preview-color, #3b82f6);
    background: var(--preview-bg, rgba(59, 130, 246, 0.08));
}

.preview-sidebar-item i {
    font-size: 16px;
    color: var(--preview-color, #3b82f6);
}

.preview-card {
    margin: 12px;
    padding: 16px;
    border: 2px solid var(--preview-border, rgba(59, 130, 246, 0.25));
    background: var(--preview-bg, rgba(59, 130, 246, 0.08));
    border-radius: 10px;
}

.preview-card-icon {
    width: 40px;
    height: 40px;
    background: var(--preview-icon-bg, rgba(59, 130, 246, 0.15));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}

.preview-card-icon i {
    font-size: 20px;
    color: var(--preview-color, #3b82f6);
}

.preview-card h5 {
    margin: 0;
    font-size: 13px;
    color: #1e293b;
}

.preview-btn {
    display: inline-block;
    padding: 8px 16px;
    background: var(--preview-color, #3b82f6);
    color: #fff;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    margin: 12px;
}

@media (max-width: 900px) {
    .gp-row {
        flex-direction: column;
    }
    .portal-settings-grid {
        grid-template-columns: 1fr;
    }
    .color-palette {
        grid-template-columns: repeat(5, 1fr);
    }
}
</style>

<div class="gp-container">

    <!-- TABS -->
    <div class="gp-tabs">
        <a href="config.form.php" class="gp-tab active">
            <i class="ti ti-users"></i> Usuários e Acesso
        </a>
        <a href="catalog.form.php" class="gp-tab">
            <i class="ti ti-layout-grid"></i> Catálogo de Serviços
        </a>
        <a href="services.form.php" class="gp-tab">
            <i class="ti ti-link"></i> Vínculos de Serviços
        </a>
        <a href="portal.form.php" class="gp-tab">
            <i class="ti ti-palette"></i> Personalização
        </a>
    </div>

    <!-- HEADER -->

    <!-- INFO BOX -->
    <div class="info-box">
        <i class="ti ti-info-circle"></i>
        <p>
            <strong>Como funciona:</strong> Os usuários selecionados abaixo (diretamente, por grupo ou por perfil) 
            serão automaticamente redirecionados para o Portal de Serviços após fazer login no GLPI. 
            Depois de selecionar os usuários aqui, vá na aba <strong>"Vínculos de Serviços"</strong> para definir 
            quais serviços cada um poderá acessar.
        </p>
    </div>

    <!-- LINHA 1: Perfis e Grupos -->
    <div class="gp-row">

        <!-- PERFIS -->
        <div class="gp-col">
            <div class="gp-box">
                <form method="post">
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="save_action" value="save_profiles">

                    <div class="gp-box-header">
                        <h4><i class="ti ti-shield" style="margin-right:6px;"></i> Perfis</h4>
                        <button type="submit" class="btn-save">Salvar</button>
                    </div>

                    <div class="gp-box-body">
                        <div class="gp-search">
                            <input type="text" placeholder="Filtrar perfis..." onkeyup="gpFilter(this,'list-profiles')">
                        </div>

                        <div class="gp-actions">
                            <a onclick="gpAll('list-profiles')">Marcar todos</a>
                            <a onclick="gpNone('list-profiles')">Desmarcar todos</a>
                        </div>

                        <div class="gp-list" id="list-profiles">
                            <?php foreach ($all_profiles as $id => $name): ?>
                                <?php 
                                $chk = in_array($id, $selected_profiles) ? 'checked' : '';
                                $sel = $chk ? 'selected' : '';
                                $search = strtolower($name);
                                ?>
                                <div class="gp-item <?php echo $sel; ?>" data-search="<?php echo $search; ?>">
                                    <input type="checkbox" name="selected_profiles[]" value="<?php echo $id; ?>" id="prof_<?php echo $id; ?>" <?php echo $chk; ?> onchange="gpToggle(this)">
                                    <label for="prof_<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="gp-footer">
                        <b class="gp-count"><?php echo count($selected_profiles); ?></b> perfil(is) selecionado(s)
                    </div>
                </form>
            </div>
        </div>

        <!-- GRUPOS -->
        <div class="gp-col">
            <div class="gp-box">
                <form method="post">
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="save_action" value="save_groups">

                    <div class="gp-box-header">
                        <h4><i class="ti ti-users-group" style="margin-right:6px;"></i> Grupos</h4>
                        <button type="submit" class="btn-save">Salvar</button>
                    </div>

                    <div class="gp-box-body">
                        <div class="gp-search">
                            <input type="text" placeholder="Filtrar grupos..." onkeyup="gpFilter(this,'list-groups')">
                        </div>

                        <div class="gp-actions">
                            <a onclick="gpAll('list-groups')">Marcar todos</a>
                            <a onclick="gpNone('list-groups')">Desmarcar todos</a>
                        </div>

                        <div class="gp-list" id="list-groups">
                            <?php if (empty($all_groups)): ?>
                                <div class="gp-item" style="color:#888;font-style:italic;">Nenhum grupo encontrado</div>
                            <?php else: ?>
                                <?php foreach ($all_groups as $id => $name): ?>
                                    <?php 
                                    $chk = in_array($id, $selected_groups) ? 'checked' : '';
                                    $sel = $chk ? 'selected' : '';
                                    $search = strtolower($name);
                                    ?>
                                    <div class="gp-item <?php echo $sel; ?>" data-search="<?php echo $search; ?>">
                                        <input type="checkbox" name="selected_groups[]" value="<?php echo $id; ?>" id="grp_<?php echo $id; ?>" <?php echo $chk; ?> onchange="gpToggle(this)">
                                        <label for="grp_<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="gp-footer">
                        <b class="gp-count"><?php echo count($selected_groups); ?></b> grupo(s) selecionado(s)
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- LINHA 2: Usuários -->
    <div class="gp-row">
        <div class="gp-col">
            <div class="gp-box">
                <form method="post">
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="save_action" value="save_users">

                    <div class="gp-box-header">
                        <h4><i class="ti ti-user" style="margin-right:6px;"></i> Usuários Específicos</h4>
                        <button type="submit" class="btn-save">Salvar</button>
                    </div>

                    <div class="gp-box-body">
                        <div class="gp-search">
                            <input type="text" placeholder="Filtrar usuários..." onkeyup="gpFilter(this,'list-users')">
                        </div>

                        <div class="gp-actions">
                            <a onclick="gpAll('list-users')">Marcar todos</a>
                            <a onclick="gpNone('list-users')">Desmarcar todos</a>
                        </div>

                        <div class="gp-list" id="list-users" style="max-height: 400px;">
                            <?php foreach ($all_users as $id => $name): ?>
                                <?php 
                                $chk = in_array($id, $selected_users) ? 'checked' : '';
                                $sel = $chk ? 'selected' : '';
                                $search = strtolower($name);
                                ?>
                                <div class="gp-item <?php echo $sel; ?>" data-search="<?php echo $search; ?>">
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $id; ?>" id="usr_<?php echo $id; ?>" <?php echo $chk; ?> onchange="gpToggle(this)">
                                    <label for="usr_<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="gp-footer">
                        <b class="gp-count"><?php echo count($selected_users); ?></b> usuário(s) selecionado(s)
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function gpFilter(input, listId) {
    var filter = input.value.toLowerCase();
    document.querySelectorAll('#' + listId + ' .gp-item').forEach(function(el) {
        var text = el.getAttribute('data-search') || '';
        el.classList.toggle('hidden', text.indexOf(filter) === -1);
    });
}

function gpToggle(checkbox) {
    checkbox.closest('.gp-item').classList.toggle('selected', checkbox.checked);
    gpUpdateCount(checkbox.closest('.gp-box'));
}

function gpAll(listId) {
    document.querySelectorAll('#' + listId + ' .gp-item:not(.hidden) input[type=checkbox]').forEach(function(cb) {
        cb.checked = true;
        cb.closest('.gp-item').classList.add('selected');
    });
    var box = document.getElementById(listId).closest('.gp-box');
    gpUpdateCount(box);
}

function gpNone(listId) {
    document.querySelectorAll('#' + listId + ' input[type=checkbox]').forEach(function(cb) {
        cb.checked = false;
    });
    document.querySelectorAll('#' + listId + ' .gp-item').forEach(function(el) {
        el.classList.remove('selected');
    });
    var box = document.getElementById(listId).closest('.gp-box');
    gpUpdateCount(box);
}

function gpUpdateCount(box) {
    var count = box.querySelectorAll('input[type=checkbox]:checked').length;
    var counter = box.querySelector('.gp-count');
    if (counter) {
        counter.textContent = count;
    }
}
</script>

<?php
Html::footer();