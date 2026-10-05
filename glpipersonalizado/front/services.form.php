<?php

/**
 * Página de configuração de vínculos de serviços
 * Define quais serviços estão disponíveis para cada usuário/grupo/perfil
 */

// Carregado pelo GLPI 11/12 (inc/includes.php e obsoleto)

Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
    PluginGlpipersonalizadoConfig::negarAcesso();
}

global $DB, $CFG_GLPI;

// ============================================================================
// PROCESSAR POST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_services') {
        $target_type = $_POST['target_type'] ?? '';
        $target_id = (int)($_POST['target_id'] ?? 0);
        $service_ids = $_POST['services'] ?? [];
        
        if (!empty($target_type) && $target_id > 0) {
            PluginGlpipersonalizadoConfig::saveServicesForTarget($target_type, $target_id, $service_ids);
            Session::addMessageAfterRedirect('Serviços atualizados com sucesso.', true, INFO);
        }
    }
    
    Html::back();
}

// ============================================================================
// CARREGAR DADOS
// ============================================================================

// Usuários selecionados para redirecionamento
$selected_users = PluginGlpipersonalizadoConfig::getSelectedUsers();
$selected_groups = PluginGlpipersonalizadoConfig::getSelectedGroups();
$selected_profiles = PluginGlpipersonalizadoConfig::getSelectedProfiles();

// Carregar nomes dos usuários
$users_data = [];
if (!empty($selected_users)) {
    $iterator = $DB->request([
        'SELECT' => ['id', 'name', 'realname', 'firstname'],
        'FROM' => 'glpi_users',
        'WHERE' => ['id' => $selected_users]
    ]);
    foreach ($iterator as $row) {
        $display = trim($row['firstname'] . ' ' . $row['realname']);
        if (empty($display)) $display = $row['name'];
        $users_data[$row['id']] = $display;
    }
}

// Carregar nomes dos grupos
$groups_data = [];
if (!empty($selected_groups)) {
    $iterator = $DB->request([
        'SELECT' => ['id', 'name', 'completename'],
        'FROM' => 'glpi_groups',
        'WHERE' => ['id' => $selected_groups]
    ]);
    foreach ($iterator as $row) {
        $groups_data[$row['id']] = $row['completename'] ?: $row['name'];
    }
}

// Carregar nomes dos perfis
$profiles_data = [];
if (!empty($selected_profiles)) {
    $iterator = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_profiles',
        'WHERE' => ['id' => $selected_profiles]
    ]);
    foreach ($iterator as $row) {
        $profiles_data[$row['id']] = $row['name'];
    }
}

// Carregar todos os serviços ativos agrupados por categoria
$categories = [];
$iterator = $DB->request([
    'FROM' => 'glpi_plugin_glpipersonalizado_categories',
    'WHERE' => ['is_active' => 1],
    'ORDER' => 'position ASC'
]);
foreach ($iterator as $row) {
    $categories[$row['id']] = $row;
}

$services = [];
$iterator = $DB->request([
    'FROM' => 'glpi_plugin_glpipersonalizado_services',
    'WHERE' => ['is_active' => 1],
    'ORDER' => 'category_id ASC, position ASC'
]);
foreach ($iterator as $row) {
    if (!isset($services[$row['category_id']])) {
        $services[$row['category_id']] = [];
    }
    $services[$row['category_id']][] = $row;
}

// Alvo selecionado para edição
$edit_type = $_GET['type'] ?? '';
$edit_id = (int)($_GET['id'] ?? 0);
$edit_services = [];
if (!empty($edit_type) && $edit_id > 0) {
    $edit_services = PluginGlpipersonalizadoConfig::getServicesForTarget($edit_type, $edit_id);
}

$csrf_token = PluginGlpipersonalizadoConfig::tokenCsrf();

Html::header('GLPI Personalizado - Vínculos de Serviços', $_SERVER['PHP_SELF'], 'config', 'PluginGlpipersonalizadoConfig');

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

.targets-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
}

.targets-sidebar {
    background: #fff;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
}

.sidebar-section {
    border-bottom: 1px solid #e0e5eb;
}

.sidebar-section:last-child {
    border-bottom: none;
}

.sidebar-section-header {
    padding: 12px 16px;
    background: #f8f9fa;
    font-size: 12px;
    font-weight: 600;
    color: #5a6f8a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-section-header i {
    font-size: 16px;
}

.target-list {
    padding: 8px;
}

.target-item {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-radius: 4px;
    margin-bottom: 4px;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    color: #2c3e50;
    border: 1px solid transparent;
}

.target-item:hover {
    background: #f5f7fa;
    color: #2c3e50;
}

.target-item.active {
    background: rgba(44, 62, 80, 0.08);
    border-color: rgba(44, 62, 80, 0.15);
}

.target-item .target-name {
    flex: 1;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.target-item .target-count {
    font-size: 11px;
    color: #7a8599;
    background: #eef1f5;
    padding: 2px 8px;
    border-radius: 10px;
}

.empty-targets {
    padding: 20px;
    text-align: center;
    color: #7a8599;
    font-size: 12px;
}

.services-panel {
    background: #fff;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
}

.services-panel-header {
    padding: 14px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e5eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.services-panel-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

.services-panel-body {
    padding: 16px;
}

.no-target-selected {
    padding: 60px 20px;
    text-align: center;
    color: #7a8599;
}

.no-target-selected i {
    font-size: 48px;
    opacity: 0.3;
    display: block;
    margin-bottom: 15px;
}

.service-category {
    margin-bottom: 20px;
}

.service-category-header {
    font-size: 12px;
    font-weight: 600;
    color: #5a6f8a;
    padding: 8px 0;
    border-bottom: 1px solid #eef1f5;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.service-checkbox {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    background: #f8f9fb;
    border-radius: 4px;
    margin-bottom: 6px;
    cursor: pointer;
    transition: all 0.15s;
}

.service-checkbox:hover {
    background: #eef1f5;
}

.service-checkbox.selected {
    background: rgba(44, 62, 80, 0.08);
    border-left: 3px solid #2c3e50;
    padding-left: 9px;
}

.service-checkbox input {
    margin-right: 12px;
}

.service-checkbox .svc-info {
    flex: 1;
}

.service-checkbox .svc-name {
    font-size: 13px;
    font-weight: 500;
    color: #2c3e50;
}

.service-checkbox .svc-desc {
    font-size: 11px;
    color: #7a8599;
    margin-top: 2px;
}

.btn-primary {
    padding: 8px 18px;
    background: #2c3e50;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.btn-primary:hover {
    background: #1a252f;
}

.actions-bar {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eef1f5;
}

.actions-bar a {
    font-size: 12px;
    color: #5a6f8a;
    text-decoration: none;
}

.actions-bar a:hover {
    color: #2c3e50;
    text-decoration: underline;
}

@media (max-width: 900px) {
    .targets-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="gp-container">

    <!-- TABS -->
<div class="gp-tabs">
    <a href="config.form.php" class="gp-tab">
        <i class="ti ti-users"></i> Usuários e Acesso
    </a>
    <a href="catalog.form.php" class="gp-tab">
        <i class="ti ti-layout-grid"></i> Catálogo de Serviços
    </a>
    <a href="services.form.php" class="gp-tab active">
        <i class="ti ti-link"></i> Vínculos de Serviços
    </a>
    <a href="portal.form.php" class="gp-tab">
        <i class="ti ti-palette"></i> Personalização
    </a>
</div>

    <!-- HEADER -->
    <div class="gp-header">
        <h2>Vínculos de Serviços</h2>
        <p>Defina quais serviços do catálogo estarão disponíveis para cada usuário, grupo ou perfil selecionado.</p>
    </div>

    <!-- GRID -->
    <div class="targets-grid">

        <!-- SIDEBAR - ALVOS -->
        <div class="targets-sidebar">
            
            <!-- USUÁRIOS -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <i class="ti ti-user"></i> Usuários
                </div>
                <div class="target-list">
                    <?php if (empty($users_data)): ?>
                        <div class="empty-targets">Nenhum usuário selecionado</div>
                    <?php else: ?>
                        <?php foreach ($users_data as $id => $name): ?>
                            <?php 
                            $is_active = ($edit_type === 'user' && $edit_id === $id);
                            $count = count(PluginGlpipersonalizadoConfig::getServicesForTarget('user', $id));
                            ?>
                            <a href="?type=user&id=<?php echo $id; ?>" class="target-item <?php echo $is_active ? 'active' : ''; ?>">
                                <span class="target-name"><?php echo htmlspecialchars($name); ?></span>
                                <span class="target-count"><?php echo $count; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- GRUPOS -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <i class="ti ti-users-group"></i> Grupos
                </div>
                <div class="target-list">
                    <?php if (empty($groups_data)): ?>
                        <div class="empty-targets">Nenhum grupo selecionado</div>
                    <?php else: ?>
                        <?php foreach ($groups_data as $id => $name): ?>
                            <?php 
                            $is_active = ($edit_type === 'group' && $edit_id === $id);
                            $count = count(PluginGlpipersonalizadoConfig::getServicesForTarget('group', $id));
                            ?>
                            <a href="?type=group&id=<?php echo $id; ?>" class="target-item <?php echo $is_active ? 'active' : ''; ?>">
                                <span class="target-name"><?php echo htmlspecialchars($name); ?></span>
                                <span class="target-count"><?php echo $count; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PERFIS -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <i class="ti ti-shield"></i> Perfis
                </div>
                <div class="target-list">
                    <?php if (empty($profiles_data)): ?>
                        <div class="empty-targets">Nenhum perfil selecionado</div>
                    <?php else: ?>
                        <?php foreach ($profiles_data as $id => $name): ?>
                            <?php 
                            $is_active = ($edit_type === 'profile' && $edit_id === $id);
                            $count = count(PluginGlpipersonalizadoConfig::getServicesForTarget('profile', $id));
                            ?>
                            <a href="?type=profile&id=<?php echo $id; ?>" class="target-item <?php echo $is_active ? 'active' : ''; ?>">
                                <span class="target-name"><?php echo htmlspecialchars($name); ?></span>
                                <span class="target-count"><?php echo $count; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- PAINEL DE SERVIÇOS -->
        <div class="services-panel">
            <?php if (empty($edit_type) || $edit_id <= 0): ?>
                <div class="services-panel-body">
                    <div class="no-target-selected">
                        <i class="ti ti-hand-click"></i>
                        <p>Selecione um usuário, grupo ou perfil<br>na lista à esquerda para configurar os serviços.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php
                // Nome do alvo selecionado
                $target_name = '';
                if ($edit_type === 'user') $target_name = $users_data[$edit_id] ?? 'Usuário';
                if ($edit_type === 'group') $target_name = $groups_data[$edit_id] ?? 'Grupo';
                if ($edit_type === 'profile') $target_name = $profiles_data[$edit_id] ?? 'Perfil';
                ?>
                <form method="post">
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="save_services">
                    <input type="hidden" name="target_type" value="<?php echo htmlspecialchars($edit_type); ?>">
                    <input type="hidden" name="target_id" value="<?php echo $edit_id; ?>">

                    <div class="services-panel-header">
                        <h3>Serviços para: <?php echo htmlspecialchars($target_name); ?></h3>
                        <button type="submit" class="btn-primary">Salvar</button>
                    </div>

                    <div class="services-panel-body">
                        
                        <div class="actions-bar">
                            <a href="#" onclick="selectAll(); return false;">Marcar todos</a>
                            <a href="#" onclick="selectNone(); return false;">Desmarcar todos</a>
                        </div>

                        <?php if (empty($categories) || empty($services)): ?>
                            <div class="no-target-selected">
                                <i class="ti ti-file-off"></i>
                                <p>Nenhum serviço cadastrado.<br>Crie serviços na aba "Catálogo de Serviços".</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($categories as $cat_id => $cat): ?>
                                <?php if (isset($services[$cat_id]) && !empty($services[$cat_id])): ?>
                                    <div class="service-category">
                                        <div class="service-category-header">
                                            <i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </div>
                                        
                                        <?php foreach ($services[$cat_id] as $svc): ?>
                                            <?php 
                                            $is_checked = in_array($svc['id'], $edit_services);
                                            $selected_class = $is_checked ? 'selected' : '';
                                            ?>
                                            <label class="service-checkbox <?php echo $selected_class; ?>">
                                                <input type="checkbox" name="services[]" value="<?php echo $svc['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?> onchange="toggleSelected(this)">
                                                <div class="svc-info">
                                                    <div class="svc-name"><?php echo htmlspecialchars($svc['name']); ?></div>
                                                    <?php if (!empty($svc['description'])): ?>
                                                        <div class="svc-desc"><?php echo htmlspecialchars($svc['description']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
function toggleSelected(checkbox) {
    var label = checkbox.closest('.service-checkbox');
    if (checkbox.checked) {
        label.classList.add('selected');
    } else {
        label.classList.remove('selected');
    }
}

function selectAll() {
    document.querySelectorAll('.service-checkbox input[type="checkbox"]').forEach(function(cb) {
        cb.checked = true;
        cb.closest('.service-checkbox').classList.add('selected');
    });
}

function selectNone() {
    document.querySelectorAll('.service-checkbox input[type="checkbox"]').forEach(function(cb) {
        cb.checked = false;
        cb.closest('.service-checkbox').classList.remove('selected');
    });
}
</script>

<?php
Html::footer();