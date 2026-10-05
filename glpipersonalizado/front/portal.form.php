<?php

/**
 * Página de configurações do plugin glpipersonalizado
 * Aba: Personalização do Portal
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_portal_settings'])) {
    $settings = [
    'primary_color' => trim($_POST['primary_color'] ?? '#3b82f6'),
    'logo_url' => trim($_POST['logo_url'] ?? ''),
    'portal_title' => trim($_POST['portal_title'] ?? 'Portal de Serviços'),
    'welcome_message' => trim($_POST['welcome_message'] ?? ''),
    'header_text_color' => trim($_POST['header_text_color'] ?? '#ffffff')
];
    
    // Validar cor hex
    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $settings['primary_color'])) {
        $settings['primary_color'] = '#3b82f6';
    }
    
    PluginGlpipersonalizadoConfig::saveAllPortalSettings($settings);
    Session::addMessageAfterRedirect('Configurações do portal salvas com sucesso.', true, INFO);
    Html::back();
}

// ============================================================================
// CARREGAR DADOS
// ============================================================================

$portal_settings = PluginGlpipersonalizadoConfig::getAllPortalSettings();

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
$current_color = $portal_settings['primary_color'] ?? '#3b82f6';

// ============================================================================
// EXIBIR PÁGINA
// ============================================================================

Html::header('GLPI Personalizado - Personalização do Portal', $_SERVER['PHP_SELF'], 'config', 'PluginGlpipersonalizadoConfig');

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

.portal-settings-form {
    background: #fff;
    border: 1px solid #e0e5eb;
    border-radius: 8px;
    overflow: hidden;
}

.portal-settings-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e0e5eb;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.portal-settings-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.portal-settings-header h3 i {
    font-size: 20px;
    color: #5a6f8a;
}

.btn-save {
    padding: 8px 20px;
    background: #2c3e50;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-save:hover {
    background: #1a252f;
}

.portal-settings-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

.settings-section {
    padding: 24px;
    border-right: 1px solid #e0e5eb;
}

.settings-section:last-child {
    border-right: none;
}

.settings-section h4 {
    margin: 0 0 20px 0;
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
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
}

.form-group input[type='text'],
.form-group input[type='url'],
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dce1e8;
    border-radius: 6px;
    font-size: 13px;
    color: #333;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #5a6f8a;
}

.form-group .form-hint {
    font-size: 11px;
    color: #7a8599;
    margin-top: 6px;
}

/* Paleta de Cores */
.color-section-title {
    font-size: 12px;
    font-weight: 600;
    color: #5a6f8a;
    margin-bottom: 10px;
}

.color-palette {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.color-option {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 8px;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.15s;
    position: relative;
}

.color-option:hover {
    transform: scale(1.15);
    z-index: 1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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
    text-shadow: 0 1px 3px rgba(0,0,0,0.4);
}

.color-option[title]:hover::before {
    content: attr(title);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #2c3e50;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 10;
}

.custom-color-row {
    display: flex;
    gap: 10px;
    align-items: center;
    padding-top: 12px;
    border-top: 1px dashed #e0e5eb;
}

.custom-color-row label {
    font-size: 12px;
    color: #5a6f8a;
    margin: 0;
    white-space: nowrap;
}

.custom-color-row input[type='color'] {
    width: 44px;
    height: 36px;
    border: 1px solid #dce1e8;
    border-radius: 6px;
    cursor: pointer;
    padding: 3px;
    background: #fff;
}

.custom-color-row input[type='text'] {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #dce1e8;
    border-radius: 6px;
    font-size: 13px;
    font-family: monospace;
}

/* Logo Preview */
.logo-input-group {
    display: flex;
    gap: 10px;
}

.logo-input-group input {
    flex: 1;
}

.btn-test-logo {
    padding: 10px 16px;
    background: #f8f9fa;
    border: 1px solid #dce1e8;
    border-radius: 6px;
    font-size: 12px;
    color: #5a6f8a;
    cursor: pointer;
    white-space: nowrap;
}

.btn-test-logo:hover {
    background: #eef1f5;
}

.logo-preview-container {
    margin-top: 12px;
    padding: 20px;
    background: linear-gradient(45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(-45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f0f0f0 75%),
                linear-gradient(-45deg, transparent 75%, #f0f0f0 75%);
    background-size: 16px 16px;
    background-position: 0 0, 0 8px, 8px -8px, -8px 0px;
    background-color: #fff;
    border: 1px dashed #dce1e8;
    border-radius: 8px;
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
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.logo-preview-container .no-logo i {
    font-size: 28px;
    opacity: 0.4;
}

/* Preview do Portal */
.portal-preview-section {
    grid-column: 1 / -1;
    padding: 24px;
    background: #f8f9fa;
    border-top: 1px solid #e0e5eb;
}

.portal-preview-section h4 {
    margin: 0 0 16px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.portal-preview-section h4 i {
    font-size: 18px;
    color: #5a6f8a;
}

.preview-box {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e0e5eb;
}

.preview-header {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    border-bottom: none;
    background: var(--preview-color, #3b82f6);
}

.preview-header .preview-greeting small {
    color: var(--preview-text-color, #fff);
    opacity: 0.8;
}

.preview-header .preview-greeting strong {
    color: var(--preview-text-color, #fff);
}

.preview-header span {
    color: var(--preview-text-color, #fff);
}

.preview-header .preview-logo-placeholder {
    background: rgba(255,255,255,0.2) !important;
}

.preview-header .preview-avatar {
    background: rgba(255,255,255,0.2) !important;
    box-shadow: none !important;
}

.preview-header .preview-logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
}

.preview-header .preview-logo {
    height: 36px;
    max-width: 140px;
    object-fit: contain;
}

.preview-header .preview-logo-placeholder {
    width: 36px;
    height: 36px;
    background: var(--preview-color, #3b82f6);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
}

.preview-header .preview-greeting {
    flex: 1;
}

.preview-header .preview-greeting small {
    font-size: 11px;
    color: #64748b;
    display: block;
}

.preview-header .preview-greeting strong {
    font-size: 14px;
    color: #1e293b;
}

.preview-header .preview-user {
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-header .preview-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}

.preview-body {
    display: flex;
}

.preview-sidebar {
    width: 220px;
    border-right: 1px solid #eee;
    padding: 12px 0;
    background: #fff;
}

.preview-sidebar-title {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 8px 16px;
}

.preview-sidebar-item {
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: #475569;
    border-left: 3px solid transparent;
    cursor: default;
}

.preview-sidebar-item.active {
    border-left-color: var(--preview-color, #3b82f6);
    background: var(--preview-bg, rgba(59, 130, 246, 0.08));
    color: #1e293b;
}

.preview-sidebar-item i {
    font-size: 16px;
    color: #64748b;
}

.preview-sidebar-item.active i {
    color: var(--preview-color, #3b82f6);
}

.preview-content {
    flex: 1;
    padding: 16px;
    background: #f8fafc;
}

.preview-content-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12px;
}

.preview-cards {
    display: flex;
    gap: 12px;
}

.preview-card {
    flex: 1;
    padding: 16px;
    border: 2px solid var(--preview-border, rgba(59, 130, 246, 0.25));
    background: var(--preview-card-bg, rgba(59, 130, 246, 0.08));
    border-radius: 10px;
    cursor: default;
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
    margin: 0 0 4px 0;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}

.preview-card p {
    margin: 0;
    font-size: 11px;
    color: #64748b;
}

.preview-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: var(--preview-color, #3b82f6);
    color: #fff;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    margin-top: 16px;
    cursor: default;
}

.preview-status {
    display: inline-block;
    padding: 4px 10px;
    background: var(--preview-bg, rgba(59, 130, 246, 0.1));
    color: var(--preview-color, #3b82f6);
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
}

@media (max-width: 1000px) {
    .portal-settings-body {
        grid-template-columns: 1fr;
    }
    
    .settings-section {
        border-right: none;
        border-bottom: 1px solid #e0e5eb;
    }
    
    .settings-section:last-child {
        border-bottom: none;
    }
    
    .color-palette {
        grid-template-columns: repeat(5, 1fr);
    }
    
    .preview-body {
        flex-direction: column;
    }
    
    .preview-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #eee;
    }
    
    .preview-cards {
        flex-direction: column;
    }
}

.header-text-color-options {
    display: flex;
    gap: 12px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.color-radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px 16px;
    border: 2px solid #e0e5eb;
    border-radius: 8px;
    transition: all 0.2s;
    background: #fff;
}

.color-radio-option:hover {
    border-color: #5a6f8a;
    background: #f8f9fa;
}

.color-radio-option.selected {
    border-color: #2c3e50;
    background: #f0f4f8;
}

.color-radio-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.color-radio-preview {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 1px solid #ddd;
}

.color-radio-option.selected .color-radio-preview {
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px #2c3e50;
}

.color-radio-label {
    font-size: 13px;
    color: #333;
}

.color-radio-option.selected .color-radio-label {
    font-weight: 600;
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
        <a href="services.form.php" class="gp-tab">
            <i class="ti ti-link"></i> Vínculos de Serviços
        </a>
        <a href="portal.form.php" class="gp-tab active">
            <i class="ti ti-palette"></i> Personalização
        </a>
    </div>

    <!-- INFO BOX -->
    <div class="info-box">
        <i class="ti ti-info-circle"></i>
        <p>
            <strong>Personalização do Portal:</strong> Configure a aparência do Portal de Serviços. 
            Escolha uma cor principal que será aplicada em todo o layout (botões, ícones, destaques) e adicione o logo do seu cliente. 
            As alterações serão refletidas imediatamente no portal.
        </p>
    </div>

    <!-- FORMULÁRIO -->
    <form method="post" class="portal-settings-form">
        <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_portal_settings" value="1">
        <input type="hidden" name="primary_color" id="primary_color_input" value="<?php echo htmlspecialchars($current_color); ?>">
        
        <div class="portal-settings-header">
            <h3><i class="ti ti-settings"></i> Configurações de Aparência</h3>
            <button type="submit" class="btn-save">
                <i class="ti ti-device-floppy"></i> Salvar Configurações
            </button>
        </div>
        
        <div class="portal-settings-body">
            
            <!-- COLUNA 1: Cores -->
            <div class="settings-section">
                <h4><i class="ti ti-palette"></i> Cor Principal</h4>
                
                <div class="form-group">
                    <div class="color-section-title">Selecione uma cor da paleta:</div>
                    <div class="color-palette">
                        <?php foreach ($color_palette as $hex => $name): ?>
                            <div class="color-option <?php echo ($hex === $current_color) ? 'selected' : ''; ?>" 
                                 style="background: <?php echo $hex; ?>;" 
                                 data-color="<?php echo $hex; ?>"
                                 title="<?php echo $name; ?>"
                                 onclick="selectColor('<?php echo $hex; ?>')">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="custom-color-row">
                        <label>Ou escolha uma cor personalizada:</label>
                        <input type="color" id="custom_color_picker" value="<?php echo htmlspecialchars($current_color); ?>" onchange="selectColor(this.value)">
                        <input type="text" id="custom_color_text" value="<?php echo htmlspecialchars($current_color); ?>" placeholder="#000000" maxlength="7" onchange="selectColorFromText(this.value)">
                    </div>
                </div>
            </div>
            
            <!-- COLUNA 2: Logo e Textos -->
            <div class="settings-section">
                <h4><i class="ti ti-photo"></i> Logo e Identidade</h4>
                
                <div class="form-group">
                    <label>URL do Logo</label>
                    <div class="logo-input-group">
                        <input type="url" name="logo_url" id="logo_url" value="<?php echo htmlspecialchars($portal_settings['logo_url'] ?? ''); ?>" placeholder="https://exemplo.com/logo.png">
                        <button type="button" class="btn-test-logo" onclick="testLogo()">
                            <i class="ti ti-eye"></i> Testar
                        </button>
                    </div>
                    <div class="form-hint">Insira a URL completa de uma imagem PNG ou SVG com fundo transparente. Tamanho recomendado: altura de 40-60px.</div>
                    
                    <div class="logo-preview-container" id="logo_preview_container">
                        <?php if (!empty($portal_settings['logo_url'])): ?>
                            <img src="<?php echo htmlspecialchars($portal_settings['logo_url']); ?>" alt="Logo Preview" id="logo_preview_img" onerror="logoError()">
                        <?php else: ?>
                            <div class="no-logo" id="no_logo_msg">
                                <i class="ti ti-photo-off"></i>
                                Nenhum logo configurado
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Título do Portal</label>
                    <input type="text" name="portal_title" value="<?php echo htmlspecialchars($portal_settings['portal_title'] ?? 'Portal de Serviços'); ?>" placeholder="Portal de Serviços">
                    <div class="form-hint">Título exibido na aba do navegador.</div>
                </div>
                
                <div class="form-group">
                    <label>Mensagem de Boas-vindas (opcional)</label>
                    <input type="text" name="welcome_message" value="<?php echo htmlspecialchars($portal_settings['welcome_message'] ?? ''); ?>" placeholder="Ex: Bem-vindo ao Portal de TI">
                    <div class="form-hint">Se preenchido, substitui a saudação padrão (Bom dia, Boa tarde, etc.).</div>
                </div>
                <div class="form-group">
    <label>Cor do Texto do Cabeçalho</label>
    <div class="header-text-color-options" id="headerTextColorOptions">
        <?php $current_text_color = $portal_settings['header_text_color'] ?? '#ffffff'; ?>
        <label class="color-radio-option <?php echo ($current_text_color === '#ffffff') ? 'selected' : ''; ?>" onclick="selectHeaderTextColor('#ffffff', this)">
            <input type="radio" name="header_text_color" value="#ffffff" <?php echo ($current_text_color === '#ffffff') ? 'checked' : ''; ?>>
            <span class="color-radio-preview" style="background: #ffffff;"></span>
            <span class="color-radio-label">Branco</span>
        </label>
        <label class="color-radio-option <?php echo ($current_text_color === '#000000') ? 'selected' : ''; ?>" onclick="selectHeaderTextColor('#000000', this)">
            <input type="radio" name="header_text_color" value="#000000" <?php echo ($current_text_color === '#000000') ? 'checked' : ''; ?>>
            <span class="color-radio-preview" style="background: #000000;"></span>
            <span class="color-radio-label">Preto</span>
        </label>
        <label class="color-radio-option <?php echo ($current_text_color === '#1e293b') ? 'selected' : ''; ?>" onclick="selectHeaderTextColor('#1e293b', this)">
            <input type="radio" name="header_text_color" value="#1e293b" <?php echo ($current_text_color === '#1e293b') ? 'checked' : ''; ?>>
            <span class="color-radio-preview" style="background: #1e293b;"></span>
            <span class="color-radio-label">Cinza Escuro</span>
        </label>
    </div>
    <div class="form-hint">Escolha a cor do texto que melhor contrasta com a cor principal.</div>
</div>
            </div>
            
            <!-- PREVIEW -->
            <div class="portal-preview-section">
                <h4><i class="ti ti-eye"></i> Pré-visualização</h4>
                
                <div class="preview-box" id="preview_box">
                    <div class="preview-header">
                        <div class="preview-logo-area">
                            <span class="preview-logo-placeholder" id="preview_logo_placeholder">
                                <i class="ti ti-apps"></i>
                            </span>
                            <img src="" alt="Logo" class="preview-logo" id="preview_logo" style="display: none;">
                            <div class="preview-greeting">
                                <small id="preview_welcome">Bom dia,</small>
                                <strong>Usuário</strong>
                            </div>
                        </div>
                        <div class="preview-user">
                            <span class="preview-avatar" id="preview_avatar" style="background: linear-gradient(135deg, <?php echo $current_color; ?>, <?php echo PluginGlpipersonalizadoConfig::adjustBrightness($current_color, -40); ?>);">U</span>
                            <span style="font-size: 12px; color: #64748b;">Usuário</span>
                        </div>
                    </div>
                    
                    <div class="preview-body">
                        <div class="preview-sidebar">
                            <div class="preview-sidebar-title">Catálogo</div>
                            <div class="preview-sidebar-item active">
                                <i class="ti ti-folder"></i>
                                <span>Categoria Ativa</span>
                            </div>
                            <div class="preview-sidebar-item">
                                <i class="ti ti-folder"></i>
                                <span>Outra Categoria</span>
                            </div>
                        </div>
                        
                        <div class="preview-content">
                            <div class="preview-content-title">Serviços Disponíveis</div>
                            
                            <div class="preview-cards">
                                <div class="preview-card">
                                    <div class="preview-card-icon">
                                        <i class="ti ti-file-text"></i>
                                    </div>
                                    <h5>Serviço Exemplo</h5>
                                    <p>Descrição do serviço</p>
                                </div>
                                
                                <div class="preview-card">
                                    <div class="preview-card-icon">
                                        <i class="ti ti-settings"></i>
                                    </div>
                                    <h5>Outro Serviço</h5>
                                    <p>Descrição do serviço</p>
                                </div>
                            </div>
                            
                            <button type="button" class="preview-btn">
                                <i class="ti ti-send"></i> Enviar Chamado
                            </button>
                            
                            <div style="margin-top: 16px;">
                                <span style="font-size: 11px; color: #64748b;">Status exemplo: </span>
                                <span class="preview-status">Novo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </form>

</div>

<script>
var currentColor = '<?php echo $current_color; ?>';

function selectColor(color) {
    currentColor = color;
    
    // Atualizar input hidden
    document.getElementById('primary_color_input').value = color;
    
    // Atualizar color picker e texto
    document.getElementById('custom_color_picker').value = color;
    document.getElementById('custom_color_text').value = color;
    
    // Atualizar seleção na paleta
    document.querySelectorAll('.color-option').forEach(function(el) {
        el.classList.toggle('selected', el.dataset.color === color);
    });
    
    // Atualizar preview
    updatePreview(color);
}

function selectColorFromText(value) {
    // Validar formato hex
    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
        selectColor(value);
    }
}

function hexToRgb(hex) {
    hex = hex.replace('#', '');
    var r = parseInt(hex.substring(0, 2), 16);
    var g = parseInt(hex.substring(2, 4), 16);
    var b = parseInt(hex.substring(4, 6), 16);
    return { r: r, g: g, b: b };
}

function adjustBrightness(hex, steps) {
    var rgb = hexToRgb(hex);
    rgb.r = Math.max(0, Math.min(255, rgb.r + steps));
    rgb.g = Math.max(0, Math.min(255, rgb.g + steps));
    rgb.b = Math.max(0, Math.min(255, rgb.b + steps));
    return '#' + ((1 << 24) + (rgb.r << 16) + (rgb.g << 8) + rgb.b).toString(16).slice(1);
}

function updatePreview(color) {
    var rgb = hexToRgb(color);
    var rgbStr = rgb.r + ', ' + rgb.g + ', ' + rgb.b;
    
    var previewBox = document.getElementById('preview_box');
    
    // Definir variáveis CSS
    previewBox.style.setProperty('--preview-color', color);
    previewBox.style.setProperty('--preview-bg', 'rgba(' + rgbStr + ', 0.08)');
    previewBox.style.setProperty('--preview-card-bg', 'rgba(' + rgbStr + ', 0.08)');
    previewBox.style.setProperty('--preview-border', 'rgba(' + rgbStr + ', 0.25)');
    previewBox.style.setProperty('--preview-icon-bg', 'rgba(' + rgbStr + ', 0.15)');
    
    // Atualizar avatar
    var darker = adjustBrightness(color, -40);
    document.getElementById('preview_avatar').style.background = 'linear-gradient(135deg, ' + color + ', ' + darker + ')';
    
    // Atualizar placeholder do logo
    document.getElementById('preview_logo_placeholder').style.background = color;
}

function selectHeaderTextColor(color, element) {
    // Remover selected de todos
    document.querySelectorAll('#headerTextColorOptions .color-radio-option').forEach(function(el) {
        el.classList.remove('selected');
    });
    
    // Adicionar selected ao clicado
    element.classList.add('selected');
    
    // Marcar o radio
    element.querySelector('input[type="radio"]').checked = true;
    
    // Atualizar preview
    updateHeaderTextColorPreview(color);
}

function updateHeaderTextColorPreview(color) {
    var previewBox = document.getElementById('preview_box');
    
    // Atualizar todos os textos do header no preview
    var greetingSmall = previewBox.querySelector('.preview-greeting small');
    var greetingStrong = previewBox.querySelector('.preview-greeting strong');
    var userName = previewBox.querySelector('.preview-user span:last-child');
    var avatar = document.getElementById('preview_avatar');
    
    if (greetingSmall) {
        greetingSmall.style.color = color;
        greetingSmall.style.opacity = '0.8';
    }
    if (greetingStrong) {
        greetingStrong.style.color = color;
    }
    if (userName) {
        userName.style.color = color;
    }
    if (avatar) {
        avatar.style.color = color;
        // Ajustar fundo do avatar baseado na cor do texto
        if (color === '#ffffff') {
            avatar.style.background = 'rgba(255,255,255,0.2)';
        } else {
            avatar.style.background = 'rgba(0,0,0,0.1)';
        }
    }
}

function testLogo() {
    var url = document.getElementById('logo_url').value.trim();
    var container = document.getElementById('logo_preview_container');
    
    if (!url) {
        container.innerHTML = '<div class="no-logo" id="no_logo_msg"><i class="ti ti-photo-off"></i>Nenhum logo configurado</div>';
        updatePreviewLogo('');
        return;
    }
    
    container.innerHTML = '<div class="no-logo"><i class="ti ti-loader" style="animation: spin 1s linear infinite;"></i>Carregando...</div>';
    
    var img = new Image();
    img.onload = function() {
        container.innerHTML = '<img src="' + url + '" alt="Logo Preview" id="logo_preview_img">';
        updatePreviewLogo(url);
    };
    img.onerror = function() {
        container.innerHTML = '<div class="no-logo" style="color: #dc3545;"><i class="ti ti-photo-x"></i>Erro ao carregar a imagem</div>';
        updatePreviewLogo('');
    };
    img.src = url;
}

function logoError() {
    var container = document.getElementById('logo_preview_container');
    container.innerHTML = '<div class="no-logo" style="color: #dc3545;"><i class="ti ti-photo-x"></i>Erro ao carregar a imagem</div>';
    updatePreviewLogo('');
}

function updatePreviewLogo(url) {
    var previewLogo = document.getElementById('preview_logo');
    var placeholder = document.getElementById('preview_logo_placeholder');
    
    if (url) {
        previewLogo.src = url;
        previewLogo.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        previewLogo.style.display = 'none';
        placeholder.style.display = 'flex';
    }
}

// Inicializar preview
document.addEventListener('DOMContentLoaded', function() {
    updatePreview(currentColor);
    
    // Se já tem logo configurado, atualizar preview
    var logoUrl = document.getElementById('logo_url').value.trim();
    if (logoUrl) {
        updatePreviewLogo(logoUrl);
    }
    
    // Inicializar cor do texto do header
    var checkedTextColor = document.querySelector('input[name="header_text_color"]:checked');
    if (checkedTextColor) {
        updateHeaderTextColorPreview(checkedTextColor.value);
    }
});

// Estilo para animação de loading
var style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>

<?php
Html::footer();