<?php

/**
 * Página de configurações do plugin glpipersonalizado
 * Aba: Catálogo de Serviços (Categorias e Serviços)
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

    // Criar Categoria
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['category_icon'] ?? 'ti ti-folder');
        
        if (!empty($name)) {
            // Pegar próxima posição
            $iterator = $DB->request([
                'SELECT' => ['MAX' => 'position AS max_pos'],
                'FROM' => 'glpi_plugin_glpipersonalizado_categories'
            ]);
            $row = $iterator->current();
            $next_pos = ($row['max_pos'] ?? 0) + 1;
            
            $DB->insert('glpi_plugin_glpipersonalizado_categories', [
                'name' => $name,
                'icon' => $icon,
                'position' => $next_pos,
                'is_active' => 1
            ]);
            Session::addMessageAfterRedirect('Categoria criada com sucesso.', true, INFO);
        }
        Html::back();
    }

    // Editar Categoria
    if (isset($_POST['edit_category'])) {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['category_icon'] ?? 'ti ti-folder');
        $is_active = isset($_POST['category_active']) ? 1 : 0;
        
        if ($id > 0 && !empty($name)) {
            $DB->update('glpi_plugin_glpipersonalizado_categories', [
                'name' => $name,
                'icon' => $icon,
                'is_active' => $is_active
            ], ['id' => $id]);
            Session::addMessageAfterRedirect('Categoria atualizada com sucesso.', true, INFO);
        }
        Html::back();
    }

    // Excluir Categoria
    if (isset($_POST['delete_category'])) {
        $id = (int)($_POST['category_id'] ?? 0);
        if ($id > 0) {
            // Excluir serviços da categoria primeiro
            $DB->delete('glpi_plugin_glpipersonalizado_services', ['category_id' => $id]);
            // Excluir categoria
            $DB->delete('glpi_plugin_glpipersonalizado_categories', ['id' => $id]);
            Session::addMessageAfterRedirect('Categoria excluída com sucesso.', true, INFO);
        }
        Html::back();
    }

    // Criar Serviço
    if (isset($_POST['add_service'])) {
        $category_id = (int)($_POST['service_category_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['service_description'] ?? '');
        $icon = trim($_POST['service_icon'] ?? 'ti ti-file-text');
        
        if ($category_id > 0 && !empty($name)) {
            // Pegar próxima posição na categoria
            $iterator = $DB->request([
                'SELECT' => ['MAX' => 'position AS max_pos'],
                'FROM' => 'glpi_plugin_glpipersonalizado_services',
                'WHERE' => ['category_id' => $category_id]
            ]);
            $row = $iterator->current();
            $next_pos = ($row['max_pos'] ?? 0) + 1;
            
            $DB->insert('glpi_plugin_glpipersonalizado_services', [
                'category_id' => $category_id,
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'position' => $next_pos,
                'is_active' => 1,
                'ticket_title' => trim($_POST['ticket_title'] ?? ''),
                'ticket_description' => trim($_POST['ticket_description'] ?? ''),
                'ticket_category_id' => (int)($_POST['ticket_category_id'] ?? 0) ?: null,
                'ticket_type' => (int)($_POST['ticket_type'] ?? 1),
                'ticket_priority' => (int)($_POST['ticket_priority'] ?? 3),
                'ticket_urgency' => (int)($_POST['ticket_urgency'] ?? 3),
                'ticket_impact' => (int)($_POST['ticket_impact'] ?? 3),
                'ticket_entity_id' => (int)($_POST['ticket_entity_id'] ?? 0) ?: null,
                'ticket_group_observer' => (int)($_POST['ticket_group_observer'] ?? 0) ?: null,
                'ticket_requesttype_id' => (int)($_POST['ticket_requesttype_id'] ?? 0) ?: null
            ]);
            Session::addMessageAfterRedirect('Serviço criado com sucesso.', true, INFO);
        }
        Html::back();
    }

    // Editar Serviço
    if (isset($_POST['edit_service'])) {
        $id = (int)($_POST['service_id'] ?? 0);
        $category_id = (int)($_POST['service_category_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['service_description'] ?? '');
        $icon = trim($_POST['service_icon'] ?? 'ti ti-file-text');
        $is_active = isset($_POST['service_active']) ? 1 : 0;
        
        if ($id > 0 && $category_id > 0 && !empty($name)) {
            $DB->update('glpi_plugin_glpipersonalizado_services', [
                'category_id' => $category_id,
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'is_active' => $is_active,
                'ticket_title' => trim($_POST['ticket_title'] ?? ''),
                'ticket_description' => trim($_POST['ticket_description'] ?? ''),
                'ticket_category_id' => (int)($_POST['ticket_category_id'] ?? 0) ?: null,
                'ticket_type' => (int)($_POST['ticket_type'] ?? 1),
                'ticket_priority' => (int)($_POST['ticket_priority'] ?? 3),
                'ticket_urgency' => (int)($_POST['ticket_urgency'] ?? 3),
                'ticket_impact' => (int)($_POST['ticket_impact'] ?? 3),
                'ticket_entity_id' => (int)($_POST['ticket_entity_id'] ?? 0) ?: null,
                'ticket_group_observer' => (int)($_POST['ticket_group_observer'] ?? 0) ?: null,
                'ticket_requesttype_id' => (int)($_POST['ticket_requesttype_id'] ?? 0) ?: null
            ], ['id' => $id]);
            Session::addMessageAfterRedirect('Serviço atualizado com sucesso.', true, INFO);
        }
        Html::back();
    }

    // Excluir Serviço
    if (isset($_POST['delete_service'])) {
        $id = (int)($_POST['service_id'] ?? 0);
        if ($id > 0) {
            // Excluir vínculos primeiro
            $DB->delete('glpi_plugin_glpipersonalizado_services_targets', ['service_id' => $id]);
            // Excluir serviço
            $DB->delete('glpi_plugin_glpipersonalizado_services', ['id' => $id]);
            Session::addMessageAfterRedirect('Serviço excluído com sucesso.', true, INFO);
        }
        Html::back();
    }
}

// ============================================================================
// CARREGAR DADOS
// ============================================================================

// Categorias
$categories = [];
$iterator = $DB->request([
    'FROM' => 'glpi_plugin_glpipersonalizado_categories',
    'ORDER' => 'position ASC'
]);
foreach ($iterator as $row) {
    $categories[$row['id']] = $row;
}

// Serviços
$services = [];
$iterator = $DB->request([
    'FROM' => 'glpi_plugin_glpipersonalizado_services',
    'ORDER' => 'category_id ASC, position ASC'
]);
foreach ($iterator as $row) {
    $services[$row['id']] = $row;
}

// Categorias GLPI (para tickets)
$glpi_categories = [];
$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'completename'],
    'FROM' => 'glpi_itilcategories',
    'ORDER' => 'completename ASC'
]);
foreach ($iterator as $row) {
    $glpi_categories[$row['id']] = $row['completename'] ?: $row['name'];
}

// Entidades GLPI
$glpi_entities = [];
$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'completename'],
    'FROM' => 'glpi_entities',
    'ORDER' => 'completename ASC'
]);
foreach ($iterator as $row) {
    $glpi_entities[$row['id']] = $row['completename'] ?: $row['name'];
}

// Grupos GLPI
$glpi_groups = [];
$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'completename'],
    'FROM' => 'glpi_groups',
    'ORDER' => 'completename ASC'
]);
foreach ($iterator as $row) {
    $glpi_groups[$row['id']] = $row['completename'] ?: $row['name'];
}

// Origens de Requisição GLPI
$glpi_requesttypes = [];
$iterator = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM' => 'glpi_requesttypes',
    'ORDER' => 'name ASC'
]);
foreach ($iterator as $row) {
    $glpi_requesttypes[$row['id']] = $row['name'];
}

$csrf_token = PluginGlpipersonalizadoConfig::tokenCsrf();

// ============================================================================
// EXIBIR PÁGINA
// ============================================================================

Html::header('GLPI Personalizado - Catálogo de Serviços', $_SERVER['PHP_SELF'], 'config', 'PluginGlpipersonalizadoConfig');

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

.catalog-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
}

.catalog-box {
    background: #fff;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
}

.catalog-box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e5eb;
    border-radius: 6px 6px 0 0;
}

.catalog-box-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.catalog-box-header h4 i {
    color: #5a6f8a;
}

.catalog-box-body {
    padding: 0;
}

.btn-add {
    padding: 6px 14px;
    background: #2c3e50;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-add:hover {
    background: #1a252f;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.btn-edit {
    background: #e8f4fd;
    color: #004085;
}

.btn-edit:hover {
    background: #cce5ff;
}

.btn-delete {
    background: #f8d7da;
    color: #721c24;
}

.btn-delete:hover {
    background: #f5c6cb;
}

/* Lista de categorias */
.category-list {
    max-height: 600px;
    overflow-y: auto;
}

.category-item {
    display: flex;
    align-items: center;
    padding: 12px 18px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background 0.15s;
}

.category-item:hover {
    background: #f8f9fa;
}

.category-item.active {
    background: #e8f4fd;
    border-left: 3px solid #2c3e50;
    padding-left: 15px;
}

.category-item.inactive {
    opacity: 0.5;
}

.category-item .cat-icon {
    font-size: 22px;
    color: #5a6f8a;
    margin-right: 12px;
    width: 28px;
    text-align: center;
}

.category-item.active .cat-icon {
    color: #2c3e50;
}

.category-item .cat-info {
    flex: 1;
}

.category-item .cat-name {
    font-size: 13px;
    font-weight: 500;
    color: #2c3e50;
}

.category-item .cat-count {
    font-size: 11px;
    color: #5a6f8a;
}

.category-item .cat-actions {
    display: flex;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.15s;
}

.category-item:hover .cat-actions {
    opacity: 1;
}

.empty-list {
    padding: 30px;
    text-align: center;
    color: #5a6f8a;
    font-size: 13px;
}

.empty-list i {
    font-size: 36px;
    display: block;
    margin-bottom: 10px;
    opacity: 0.3;
}

/* Lista de serviços */
.services-header {
    padding: 14px 18px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e5eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.services-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

.services-list {
    max-height: 550px;
    overflow-y: auto;
}

.service-item {
    display: flex;
    align-items: flex-start;
    padding: 14px 18px;
    border-bottom: 1px solid #eee;
    transition: background 0.15s;
}

.service-item:hover {
    background: #f8f9fa;
}

.service-item.inactive {
    opacity: 0.5;
}

.service-item .svc-icon {
    font-size: 24px;
    color: #5a6f8a;
    margin-right: 14px;
    width: 32px;
    text-align: center;
    padding-top: 2px;
}

.service-item .svc-info {
    flex: 1;
}

.service-item .svc-name {
    font-size: 13px;
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 2px;
}

.service-item .svc-desc {
    font-size: 12px;
    color: #5a6f8a;
    margin-bottom: 6px;
}

.service-item .svc-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.service-item .svc-meta span {
    font-size: 10px;
    color: #fff;
    background: #5a6f8a;
    padding: 2px 8px;
    border-radius: 10px;
}

.service-item .svc-meta span.type-incident {
    background: #dc3545;
}

.service-item .svc-meta span.type-request {
    background: #28a745;
}

.service-item .svc-actions {
    display: flex;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.15s;
}

.service-item:hover .svc-actions {
    opacity: 1;
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid #e0e5eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #5a6f8a;
    line-height: 1;
}

.modal-close:hover {
    color: #2c3e50;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e0e5eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f8f9fa;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 6px;
}

.form-group label .required {
    color: #dc3545;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dce1e8;
    border-radius: 4px;
    font-size: 13px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #2c3e50;
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.form-group .form-hint {
    font-size: 11px;
    color: #5a6f8a;
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.form-section {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.form-section h5 {
    margin: 0 0 16px 0;
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section h5 i {
    color: #5a6f8a;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-check input[type="checkbox"] {
    width: auto;
}

.form-check label {
    margin: 0;
    font-weight: normal;
}

.btn-primary {
    padding: 10px 20px;
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

.btn-secondary {
    padding: 10px 20px;
    background: #fff;
    color: #5a6f8a;
    border: 1px solid #dce1e8;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.btn-secondary:hover {
    background: #f8f9fa;
}

.btn-danger {
    padding: 10px 20px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.btn-danger:hover {
    background: #c82333;
}

/* Icon selector */
.icon-selector {
    display: flex;
    align-items: center;
    gap: 12px;
}

.icon-preview {
    width: 48px;
    height: 48px;
    background: #f8f9fa;
    border: 1px solid #dce1e8;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #5a6f8a;
}

.icon-selector input {
    flex: 1;
}

@media (max-width: 900px) {
    .catalog-layout {
        grid-template-columns: 1fr;
    }
    
    .form-row,
    .form-row-3 {
        grid-template-columns: 1fr;
    }
}

/* Icon Selector */
.icon-selector-container {
    margin-top: 10px;
}

.icon-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 6px;
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    background: #f8f9fa;
    border: 1px solid #e0e5eb;
    border-radius: 6px;
    margin-top: 10px;
}

.icon-grid-item {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 2px solid #e0e5eb;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 18px;
    color: #5a6f8a;
}

.icon-grid-item:hover {
    border-color: #5a6f8a;
    background: #f0f4f8;
    color: #2c3e50;
}

.icon-grid-item.selected {
    border-color: #2c3e50;
    background: #e8f4fd;
    color: #2c3e50;
}

.icon-search {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #dce1e8;
    border-radius: 4px;
    font-size: 13px;
    margin-top: 10px;
}

.icon-search:focus {
    outline: none;
    border-color: #2c3e50;
}

</style>

<div class="gp-container">

    <!-- TABS -->
    <div class="gp-tabs">
        <a href="config.form.php" class="gp-tab">
            <i class="ti ti-users"></i> Usuários e Acesso
        </a>
        <a href="catalog.form.php" class="gp-tab active">
            <i class="ti ti-layout-grid"></i> Catálogo de Serviços
        </a>
        <a href="services.form.php" class="gp-tab">
            <i class="ti ti-link"></i> Vínculos de Serviços
        </a>
        <a href="portal.form.php" class="gp-tab">
            <i class="ti ti-palette"></i> Personalização
        </a>
    </div>

    <!-- INFO BOX -->
    <div class="info-box">
        <i class="ti ti-info-circle"></i>
        <p>
            <strong>Catálogo de Serviços:</strong> Crie categorias para organizar seus serviços e depois adicione os serviços em cada categoria. 
            Cada serviço pode ter configurações específicas para preenchimento automático dos tickets (categoria, tipo, prioridade, etc.).
        </p>
    </div>

    <!-- LAYOUT -->
    <div class="catalog-layout">
        
        <!-- CATEGORIAS -->
        <div class="catalog-box">
            <div class="catalog-box-header">
                <h4><i class="ti ti-folder"></i> Catalogos de Serviço</h4>
                <button type="button" class="btn-add" onclick="openCategoryModal()">
                    <i class="ti ti-plus"></i> Nova
                </button>
            </div>
            <div class="catalog-box-body">
                <div class="category-list">
                    <?php if (empty($categories)): ?>
                        <div class="empty-list">
                            <i class="ti ti-folder-off"></i>
                            Nenhuma categoria cadastrada.<br>
                            Clique em "Nova" para criar.
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $cat_services = array_filter($services, function($s) use ($cat) {
                                return $s['category_id'] == $cat['id'];
                            });
                            $service_count = count($cat_services);
                            ?>
                            <div class="category-item <?php echo $cat['is_active'] ? '' : 'inactive'; ?>" 
                                 data-id="<?php echo $cat['id']; ?>"
                                 onclick="selectCategory(<?php echo $cat['id']; ?>)">
                                <i class="<?php echo htmlspecialchars($cat['icon'] ?: 'ti ti-folder'); ?> cat-icon"></i>
                                <div class="cat-info">
                                    <div class="cat-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                                    <div class="cat-count"><?php echo $service_count; ?> serviço(s)</div>
                                </div>
                                <div class="cat-actions">
                                    <button type="button" class="btn-sm btn-edit" onclick="event.stopPropagation(); editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                    <button type="button" class="btn-sm btn-delete" onclick="event.stopPropagation(); deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>')">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- SERVIÇOS -->
        <div class="catalog-box">
            <div class="services-header">
                <h4 id="services-title">Selecione uma categoria</h4>
                <button type="button" class="btn-add" id="btn-add-service" style="display: none;" onclick="openServiceModal()">
                    <i class="ti ti-plus"></i> Novo Serviço
                </button>
            </div>
            <div class="catalog-box-body">
                <div class="services-list" id="services-list">
                    <div class="empty-list">
                        <i class="ti ti-click"></i>
                        Clique em uma categoria à esquerda<br>
                        para ver seus serviços.
                    </div>
                </div>
            </div>
        </div>
        
    </div>

</div>

<!-- MODAL CATEGORIA -->
<div class="modal-overlay" id="modal-category">
    <div class="modal-content" style="max-width: 500px;">
        <form method="post" id="form-category">
            <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="category_id" id="cat-id" value="">
            
            <div class="modal-header">
                <h4 id="modal-category-title">Nova Categoria</h4>
                <button type="button" class="modal-close" onclick="closeModal('modal-category')">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome da Categoria <span class="required">*</span></label>
                    <input type="text" name="category_name" id="cat-name" required placeholder="Ex: Suporte Técnico">
                </div>
                
                <div class="form-group">
    <label>Ícone</label>
    <div class="icon-selector">
        <div class="icon-preview" id="cat-icon-preview">
            <i class="ti ti-folder"></i>
        </div>
        <input type="text" name="category_icon" id="cat-icon" value="ti ti-folder" placeholder="ti ti-folder" onchange="updateIconPreview('cat')">
    </div>
    <div class="icon-selector-container">
        <input type="text" class="icon-search" id="cat-icon-search" placeholder="Pesquisar ícone..." oninput="filterIcons('cat')">
        <div class="icon-grid" id="cat-icon-grid">
            <!-- Ícones serão inseridos via JavaScript -->
        </div>
    </div>
    <div class="form-hint">
        Clique em um ícone acima ou digite manualmente. 
        <a href="https://tabler.io/icons" target="_blank">Ver todos os ícones</a>
    </div>
</div>
                
                <div class="form-group form-check" id="cat-active-group" style="display: none;">
                    <input type="checkbox" name="category_active" id="cat-active" checked>
                    <label for="cat-active">Categoria ativa</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-category')">Cancelar</button>
                <button type="submit" name="add_category" id="btn-save-category" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SERVIÇO -->
<div class="modal-overlay" id="modal-service">
    <div class="modal-content">
        <form method="post" id="form-service">
            <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="service_id" id="svc-id" value="">
            <input type="hidden" name="service_category_id" id="svc-category-id" value="">
            
            <div class="modal-header">
                <h4 id="modal-service-title">Novo Serviço</h4>
                <button type="button" class="modal-close" onclick="closeModal('modal-service')">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Vincular ao Catálogo <span class="required">*</span></label>
                        <select name="service_category_id_select" id="svc-category-select" required onchange="document.getElementById('svc-category-id').value = this.value;">
                            <option value="">Selecione...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
    <label>Ícone</label>
    <div class="icon-selector">
        <div class="icon-preview" id="svc-icon-preview">
            <i class="ti ti-file-text"></i>
        </div>
        <input type="text" name="service_icon" id="svc-icon" value="ti ti-file-text" placeholder="ti ti-file-text" onchange="updateIconPreview('svc')">
    </div>
    <div class="icon-selector-container">
        <input type="text" class="icon-search" id="svc-icon-search" placeholder="Pesquisar ícone..." oninput="filterIcons('svc')">
        <div class="icon-grid" id="svc-icon-grid">
            <!-- Ícones serão inseridos via JavaScript -->
        </div>
    </div>
</div>
                
                <div class="form-group">
                    <label>Nome do Serviço <span class="required">*</span></label>
                    <input type="text" name="service_name" id="svc-name" required placeholder="Ex: Solicitação de Acesso">
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="service_description" id="svc-desc" placeholder="Descrição do serviço que aparecerá no portal..."></textarea>
                </div>
                
                <!-- PREENCHIMENTO AUTOMÁTICO DO TICKET -->
                <div class="form-section">
                    <h5><i class="ti ti-settings"></i> Preenchimento Automático do Ticket</h5>
                    <p class="form-hint" style="margin-bottom: 16px;">Estes campos serão preenchidos automaticamente no ticket quando este serviço for utilizado.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Entidade</label>
                            <select name="ticket_entity_id" id="svc-entity">
                                <option value="">Usar entidade do usuário</option>
                                <?php foreach ($glpi_entities as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="ticket_type" id="svc-type">
                                <option value="1">Incidente</option>
                                <option value="2">Requisição</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Categoria GLPI</label>
                            <select name="ticket_category_id" id="svc-glpi-cat">
                                <option value="">Nenhuma</option>
                                <?php foreach ($glpi_categories as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Origem da Requisição</label>
                            <select name="ticket_requesttype_id" id="svc-requesttype">
                                <option value="">Padrão</option>
                                <?php foreach ($glpi_requesttypes as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="ticket_priority" id="svc-priority">
                                <option value="1">Muito baixa</option>
                                <option value="2">Baixa</option>
                                <option value="3" selected>Média</option>
                                <option value="4">Alta</option>
                                <option value="5">Muito alta</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Urgência</label>
                            <select name="ticket_urgency" id="svc-urgency">
                                <option value="1">Muito baixa</option>
                                <option value="2">Baixa</option>
                                <option value="3" selected>Média</option>
                                <option value="4">Alta</option>
                                <option value="5">Muito alta</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Impacto</label>
                            <select name="ticket_impact" id="svc-impact">
                                <option value="1">Muito baixo</option>
                                <option value="2">Baixo</option>
                                <option value="3" selected>Médio</option>
                                <option value="4">Alto</option>
                                <option value="5">Muito alto</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Grupo Observador</label>
                        <select name="ticket_group_observer" id="svc-group-observer">
                            <option value="">Nenhum</option>
                            <?php foreach ($glpi_groups as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Grupo que será adicionado como observador no ticket.</div>
                    </div>
                </div>
                
                <!-- TEMPLATE DE PREENCHIMENTO -->
                <div class="form-section">
                    <h5><i class="ti ti-template"></i> Template de Preenchimento</h5>
                    <p class="form-hint" style="margin-bottom: 16px;">Preencha aqui um modelo para título e descrição que será sugerido ao usuário ao abrir o chamado.</p>
                    
                    <div class="form-group">
                        <label>Título do Ticket</label>
                        <input type="text" name="ticket_title" id="svc-ticket-title" placeholder="Ex: [Acesso] Solicitação de acesso - ">
                    </div>
                    
                    <div class="form-group">
                        <label>Descrição do Ticket</label>
                        <textarea name="ticket_description" id="svc-ticket-desc" placeholder="Ex: Descreva o sistema e perfil de acesso necessário..."></textarea>
                    </div>
                </div>
                
                <div class="form-group form-check" id="svc-active-group" style="display: none;">
                    <input type="checkbox" name="service_active" id="svc-active" checked>
                    <label for="svc-active">Serviço ativo</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-service')">Cancelar</button>
                <button type="submit" name="add_service" id="btn-save-service" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CONFIRMAR EXCLUSÃO -->
<div class="modal-overlay" id="modal-delete">
    <div class="modal-content" style="max-width: 400px;">
        <form method="post" id="form-delete">
            <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="category_id" id="delete-cat-id" value="">
            <input type="hidden" name="service_id" id="delete-svc-id" value="">
            
            <div class="modal-header">
                <h4>Confirmar Exclusão</h4>
                <button type="button" class="modal-close" onclick="closeModal('modal-delete')">&times;</button>
            </div>
            
            <div class="modal-body">
                <p id="delete-message">Tem certeza que deseja excluir?</p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-delete')">Cancelar</button>
                <button type="submit" name="delete_category" id="btn-delete-confirm" class="btn-danger">Excluir</button>
            </div>
        </form>
    </div>
</div>

<script>
var selectedCategoryId = 0;
var servicesData = <?php echo json_encode($services); ?>;
var categoriesData = <?php echo json_encode($categories); ?>;

function selectCategory(categoryId) {
    selectedCategoryId = categoryId;
    
    // Atualizar visual da seleção
    document.querySelectorAll('.category-item').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelector('.category-item[data-id="' + categoryId + '"]').classList.add('active');
    
    // Atualizar título
    var category = categoriesData[categoryId];
    document.getElementById('services-title').textContent = 'Serviços: ' + category.name;
    
    // Mostrar botão de adicionar
    document.getElementById('btn-add-service').style.display = 'flex';
    
    // Filtrar e exibir serviços
    var categoryServices = Object.values(servicesData).filter(function(s) {
        return s.category_id == categoryId;
    });
    
    var listHtml = '';
    if (categoryServices.length === 0) {
        listHtml = '<div class="empty-list"><i class="ti ti-file-off"></i>Nenhum serviço nesta categoria.<br>Clique em "Novo Serviço" para criar.</div>';
    } else {
        categoryServices.forEach(function(svc) {
            var typeClass = svc.ticket_type == 1 ? 'type-incident' : 'type-request';
            var typeText = svc.ticket_type == 1 ? 'Incidente' : 'Requisição';
            var inactiveClass = svc.is_active == 1 ? '' : 'inactive';
            
            listHtml += '<div class="service-item ' + inactiveClass + '">';
            listHtml += '<i class="' + (svc.icon || 'ti ti-file-text') + ' svc-icon"></i>';
            listHtml += '<div class="svc-info">';
            listHtml += '<div class="svc-name">' + escapeHtml(svc.name) + '</div>';
            listHtml += '<div class="svc-desc">' + escapeHtml(svc.description || 'Sem descrição') + '</div>';
            listHtml += '<div class="svc-meta">';
            listHtml += '<span class="' + typeClass + '">' + typeText + '</span>';
            if (svc.ticket_category_id) {
                listHtml += '<span>Cat. GLPI: ' + svc.ticket_category_id + '</span>';
            }
            listHtml += '</div>';
            listHtml += '</div>';
            listHtml += '<div class="svc-actions">';
            listHtml += '<button type="button" class="btn-sm btn-edit" onclick="editService(' + svc.id + ')"><i class="ti ti-pencil"></i></button>';
            listHtml += '<button type="button" class="btn-sm btn-delete" onclick="deleteService(' + svc.id + ', \'' + escapeHtml(svc.name).replace(/'/g, "\\'") + '\')"><i class="ti ti-trash"></i></button>';
            listHtml += '</div>';
            listHtml += '</div>';
        });
    }
    
    document.getElementById('services-list').innerHTML = listHtml;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateIconPreview(prefix) {
    var iconInput = document.getElementById(prefix + '-icon');
    var preview = document.getElementById(prefix + '-icon-preview');
    preview.innerHTML = '<i class="' + iconInput.value + '"></i>';
}

// CATEGORIA
function openCategoryModal() {
    document.getElementById('modal-category-title').textContent = 'Nova Categoria';
    document.getElementById('form-category').reset();
    document.getElementById('cat-id').value = '';
    document.getElementById('cat-icon').value = 'ti ti-folder';
    updateIconPreview('cat');
    document.getElementById('cat-active-group').style.display = 'none';
    document.getElementById('btn-save-category').name = 'add_category';
    document.getElementById('modal-category').classList.add('active');
}

function editCategory(cat) {
    document.getElementById('modal-category-title').textContent = 'Editar Categoria';
    document.getElementById('cat-id').value = cat.id;
    document.getElementById('cat-name').value = cat.name;
    document.getElementById('cat-icon').value = cat.icon || 'ti ti-folder';
    updateIconPreview('cat');
    document.getElementById('cat-active').checked = cat.is_active == 1;
    document.getElementById('cat-active-group').style.display = 'block';
    document.getElementById('btn-save-category').name = 'edit_category';
    document.getElementById('modal-category').classList.add('active');
}

function deleteCategory(id, name) {
    document.getElementById('delete-cat-id').value = id;
    document.getElementById('delete-svc-id').value = '';
    document.getElementById('delete-message').innerHTML = 'Tem certeza que deseja excluir a categoria <strong>' + name + '</strong>?<br><br><small style="color:#dc3545;">Todos os serviços desta categoria também serão excluídos.</small>';
    document.getElementById('btn-delete-confirm').name = 'delete_category';
    document.getElementById('modal-delete').classList.add('active');
}

// SERVIÇO
function openServiceModal() {
    document.getElementById('modal-service-title').textContent = 'Novo Serviço';
    document.getElementById('form-service').reset();
    document.getElementById('svc-id').value = '';
    document.getElementById('svc-category-id').value = selectedCategoryId;
    document.getElementById('svc-category-select').value = selectedCategoryId;
    document.getElementById('svc-icon').value = 'ti ti-file-text';
    updateIconPreview('svc');
    document.getElementById('svc-active-group').style.display = 'none';
    document.getElementById('btn-save-service').name = 'add_service';
    
    // Reset selects
    document.getElementById('svc-type').value = '1';
    document.getElementById('svc-priority').value = '3';
    document.getElementById('svc-urgency').value = '3';
    document.getElementById('svc-impact').value = '3';
    
    document.getElementById('modal-service').classList.add('active');
}

function editService(serviceId) {
    var svc = servicesData[serviceId];
    if (!svc) return;
    
    document.getElementById('modal-service-title').textContent = 'Editar Serviço';
    document.getElementById('svc-id').value = svc.id;
    document.getElementById('svc-category-id').value = svc.category_id;
    document.getElementById('svc-category-select').value = svc.category_id;
    document.getElementById('svc-name').value = svc.name;
    document.getElementById('svc-desc').value = svc.description || '';
    document.getElementById('svc-icon').value = svc.icon || 'ti ti-file-text';
    updateIconPreview('svc');
    
    document.getElementById('svc-entity').value = svc.ticket_entity_id || '';
    document.getElementById('svc-type').value = svc.ticket_type || '1';
    document.getElementById('svc-glpi-cat').value = svc.ticket_category_id || '';
    document.getElementById('svc-requesttype').value = svc.ticket_requesttype_id || '';
    document.getElementById('svc-priority').value = svc.ticket_priority || '3';
    document.getElementById('svc-urgency').value = svc.ticket_urgency || '3';
    document.getElementById('svc-impact').value = svc.ticket_impact || '3';
    document.getElementById('svc-group-observer').value = svc.ticket_group_observer || '';
    document.getElementById('svc-ticket-title').value = svc.ticket_title || '';
    document.getElementById('svc-ticket-desc').value = svc.ticket_description || '';
    
    document.getElementById('svc-active').checked = svc.is_active == 1;
    document.getElementById('svc-active-group').style.display = 'block';
    document.getElementById('btn-save-service').name = 'edit_service';
    
    document.getElementById('modal-service').classList.add('active');
}

function deleteService(id, name) {
    document.getElementById('delete-cat-id').value = '';
    document.getElementById('delete-svc-id').value = id;
    document.getElementById('delete-message').innerHTML = 'Tem certeza que deseja excluir o serviço <strong>' + name + '</strong>?';
    document.getElementById('btn-delete-confirm').name = 'delete_service';
    document.getElementById('modal-delete').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Fechar modal ao clicar fora
document.querySelectorAll('.modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
            modal.classList.remove('active');
        });
    }
});

// Selecionar primeira categoria se existir
<?php if (!empty($categories)): ?>
document.addEventListener('DOMContentLoaded', function() {
    var firstCat = document.querySelector('.category-item');
    if (firstCat) {
        selectCategory(<?php echo array_key_first($categories); ?>);
    }
});
<?php endif; ?>

// Lista de ícones disponíveis
var availableIcons = [
    'ti-folder', 'ti-folder-open', 'ti-file', 'ti-file-text', 'ti-file-description',
    'ti-files', 'ti-archive', 'ti-box', 'ti-package', 'ti-inbox',
    'ti-mail', 'ti-mail-opened', 'ti-send', 'ti-message', 'ti-messages',
    'ti-user', 'ti-users', 'ti-user-plus', 'ti-user-check', 'ti-user-circle',
    'ti-settings', 'ti-settings-2', 'ti-adjustments', 'ti-tool', 'ti-tools',
    'ti-home', 'ti-building', 'ti-building-skyscraper', 'ti-office', 'ti-door',
    'ti-phone', 'ti-phone-call', 'ti-device-mobile', 'ti-device-desktop', 'ti-device-laptop',
    'ti-printer', 'ti-keyboard', 'ti-mouse', 'ti-cpu', 'ti-server',
    'ti-database', 'ti-cloud', 'ti-cloud-upload', 'ti-cloud-download', 'ti-download',
    'ti-upload', 'ti-share', 'ti-link', 'ti-unlink', 'ti-external-link',
    'ti-lock', 'ti-lock-open', 'ti-key', 'ti-shield', 'ti-shield-check',
    'ti-alert-triangle', 'ti-alert-circle', 'ti-info-circle', 'ti-help', 'ti-question-mark',
    'ti-check', 'ti-check-circle', 'ti-x', 'ti-x-circle', 'ti-ban',
    'ti-clock', 'ti-calendar', 'ti-calendar-event', 'ti-calendar-time', 'ti-hourglass',
    'ti-bell', 'ti-bell-ringing', 'ti-volume', 'ti-volume-off', 'ti-microphone',
    'ti-camera', 'ti-photo', 'ti-video', 'ti-player-play', 'ti-player-pause',
    'ti-search', 'ti-zoom-in', 'ti-zoom-out', 'ti-filter', 'ti-sort-ascending',
    'ti-edit', 'ti-pencil', 'ti-eraser', 'ti-trash', 'ti-copy',
    'ti-clipboard', 'ti-clipboard-check', 'ti-clipboard-list', 'ti-list', 'ti-list-check',
    'ti-layout', 'ti-layout-grid', 'ti-layout-list', 'ti-table', 'ti-columns',
    'ti-chart-bar', 'ti-chart-line', 'ti-chart-pie', 'ti-chart-dots', 'ti-report',
    'ti-bug', 'ti-code', 'ti-terminal', 'ti-git-branch', 'ti-git-merge',
    'ti-wifi', 'ti-wifi-off', 'ti-bluetooth', 'ti-antenna', 'ti-satellite',
    'ti-world', 'ti-map', 'ti-map-pin', 'ti-location', 'ti-compass',
    'ti-car', 'ti-truck', 'ti-plane', 'ti-train', 'ti-bike',
    'ti-shopping-cart', 'ti-basket', 'ti-credit-card', 'ti-wallet', 'ti-coin',
    'ti-receipt', 'ti-report-money', 'ti-currency-dollar', 'ti-building-bank', 'ti-cash',
    'ti-bulb', 'ti-bolt', 'ti-battery', 'ti-plug', 'ti-power',
    'ti-sun', 'ti-moon', 'ti-cloud-rain', 'ti-snowflake', 'ti-temperature',
    'ti-heart', 'ti-star', 'ti-thumb-up', 'ti-thumb-down', 'ti-mood-smile',
    'ti-award', 'ti-trophy', 'ti-certificate', 'ti-medal', 'ti-crown',
    'ti-book', 'ti-book-2', 'ti-notebook', 'ti-school', 'ti-backpack',
    'ti-briefcase', 'ti-id', 'ti-id-badge', 'ti-license', 'ti-fingerprint',
    'ti-eye', 'ti-eye-off', 'ti-ear', 'ti-hand-click', 'ti-hand-finger',
    'ti-refresh', 'ti-rotate', 'ti-repeat', 'ti-reload', 'ti-arrows-exchange',
    'ti-arrow-up', 'ti-arrow-down', 'ti-arrow-left', 'ti-arrow-right', 'ti-arrows-maximize',
    'ti-plus', 'ti-minus', 'ti-equal', 'ti-percentage', 'ti-calculator',
    'ti-hash', 'ti-at', 'ti-brackets', 'ti-code-plus', 'ti-variable',
    'ti-palette', 'ti-paint', 'ti-brush', 'ti-color-swatch', 'ti-droplet',
    'ti-scissors', 'ti-ruler', 'ti-pencil-ruler', 'ti-geometry', 'ti-vector',
    'ti-music', 'ti-headphones', 'ti-microphone-2', 'ti-radio', 'ti-disc',
    'ti-puzzle', 'ti-ghost', 'ti-robot', 'ti-rocket', 'ti-ufo',
    'ti-anchor', 'ti-lifebuoy', 'ti-flag', 'ti-bookmark', 'ti-tag',
    'ti-tags', 'ti-pin', 'ti-paperclip', 'ti-notes', 'ti-note'
];

// Renderizar grade de ícones
function renderIconGrid(prefix) {
    var grid = document.getElementById(prefix + '-icon-grid');
    var currentIcon = document.getElementById(prefix + '-icon').value.replace('ti ', '');
    
    var html = '';
    availableIcons.forEach(function(icon) {
        var selectedClass = (currentIcon === icon) ? 'selected' : '';
        html += '<div class="icon-grid-item ' + selectedClass + '" data-icon="' + icon + '" onclick="selectIcon(\'' + prefix + '\', \'' + icon + '\')">';
        html += '<i class="ti ' + icon + '"></i>';
        html += '</div>';
    });
    
    grid.innerHTML = html;
}

// Selecionar ícone
function selectIcon(prefix, icon) {
    var fullIcon = 'ti ' + icon;
    document.getElementById(prefix + '-icon').value = fullIcon;
    updateIconPreview(prefix);
    
    // Atualizar seleção visual
    var grid = document.getElementById(prefix + '-icon-grid');
    grid.querySelectorAll('.icon-grid-item').forEach(function(item) {
        item.classList.remove('selected');
        if (item.dataset.icon === icon) {
            item.classList.add('selected');
        }
    });
}

// Filtrar ícones
function filterIcons(prefix) {
    var search = document.getElementById(prefix + '-icon-search').value.toLowerCase();
    var grid = document.getElementById(prefix + '-icon-grid');
    
    grid.querySelectorAll('.icon-grid-item').forEach(function(item) {
        var iconName = item.dataset.icon.toLowerCase();
        if (iconName.includes(search)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Atualizar seleção visual quando o input muda
function updateIconGridSelection(prefix) {
    var currentIcon = document.getElementById(prefix + '-icon').value.replace('ti ', '');
    var grid = document.getElementById(prefix + '-icon-grid');
    
    grid.querySelectorAll('.icon-grid-item').forEach(function(item) {
        item.classList.remove('selected');
        if (item.dataset.icon === currentIcon) {
            item.classList.add('selected');
        }
    });
}

// Inicializar grades de ícones ao abrir modais
var originalOpenCategoryModal = openCategoryModal;
openCategoryModal = function() {
    originalOpenCategoryModal();
    setTimeout(function() {
        renderIconGrid('cat');
        document.getElementById('cat-icon-search').value = '';
    }, 100);
};

var originalEditCategory = editCategory;
editCategory = function(cat) {
    originalEditCategory(cat);
    setTimeout(function() {
        renderIconGrid('cat');
        updateIconGridSelection('cat');
        document.getElementById('cat-icon-search').value = '';
    }, 100);
};

var originalOpenServiceModal = openServiceModal;
openServiceModal = function() {
    originalOpenServiceModal();
    setTimeout(function() {
        renderIconGrid('svc');
        document.getElementById('svc-icon-search').value = '';
    }, 100);
};

var originalEditService = editService;
editService = function(serviceId) {
    originalEditService(serviceId);
    setTimeout(function() {
        renderIconGrid('svc');
        updateIconGridSelection('svc');
        document.getElementById('svc-icon-search').value = '';
    }, 100);
};


</script>

<?php
Html::footer();