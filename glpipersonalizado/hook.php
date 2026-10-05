<?php

/**
 * Hook de instalação e desinstalação do plugin glpipersonalizado
 */

function plugin_glpipersonalizado_install(): bool {
    global $DB;

    // Tabela de usuários selecionados para redirecionamento
    if (!$DB->tableExists('glpi_plugin_glpipersonalizado_users')) {
        $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_users` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
        $DB->doQuery($query);
    }

    // Tabela de grupos selecionados para redirecionamento
    if (!$DB->tableExists('glpi_plugin_glpipersonalizado_groups')) {
        $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_groups` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `groups_id` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `groups_id` (`groups_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
        $DB->doQuery($query);
    }

    // Tabela de perfis selecionados para redirecionamento
    if (!$DB->tableExists('glpi_plugin_glpipersonalizado_profiles')) {
        $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_profiles` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `profiles_id` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `profiles_id` (`profiles_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
        $DB->doQuery($query);
    }

    // Tabela de categorias do catálogo
    if (!$DB->tableExists('glpi_plugin_glpipersonalizado_categories')) {
        $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_categories` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `icon` varchar(100) DEFAULT 'ti ti-folder',
            `position` int unsigned NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `position` (`position`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
        $DB->doQuery($query);
    }

    // Tabela de serviços
    if (!$DB->tableExists('glpi_plugin_glpipersonalizado_services')) {
        $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_services` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `category_id` int unsigned NOT NULL,
            `name` varchar(255) NOT NULL,
            `description` text,
            `icon` varchar(100) DEFAULT 'ti ti-file-text',
            `position` int unsigned NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `ticket_title` varchar(255) DEFAULT NULL,
            `ticket_description` text,
            `ticket_category_id` int unsigned DEFAULT NULL,
            `ticket_type` int unsigned DEFAULT 1,
            `ticket_priority` int unsigned DEFAULT 3,
            `ticket_urgency` int unsigned DEFAULT 3,
            `ticket_impact` int unsigned DEFAULT 3,
            `ticket_entity_id` int unsigned DEFAULT NULL,
            `ticket_group_observer` int unsigned DEFAULT NULL,
            `ticket_requesttype_id` int unsigned DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `category_id` (`category_id`),
            KEY `position` (`position`),
            KEY `is_active` (`is_active`),
            KEY `ticket_entity_id` (`ticket_entity_id`),
            KEY `ticket_category_id` (`ticket_category_id`),
            KEY `ticket_group_observer` (`ticket_group_observer`),
            KEY `ticket_requesttype_id` (`ticket_requesttype_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
        $DB->doQuery($query);
    } else {
        // Atualizar tabela existente - adicionar novos campos se não existirem
        
        // ticket_entity_id
        if (!$DB->fieldExists('glpi_plugin_glpipersonalizado_services', 'ticket_entity_id')) {
            $DB->doQuery("ALTER TABLE `glpi_plugin_glpipersonalizado_services` 
                ADD COLUMN `ticket_entity_id` int unsigned DEFAULT NULL AFTER `ticket_impact`");
            $DB->doQuery("ALTER TABLE `glpi_plugin_glpipersonalizado_services` 
                ADD KEY `ticket_entity_id` (`ticket_entity_id`)");
        }
        
        // ticket_group_observer
        if (!$DB->fieldExists('glpi_plugin_glpipersonalizado_services', 'ticket_group_observer')) {
            $DB->doQuery("ALTER TABLE `glpi_plugin_glpipersonalizado_services` 
                ADD COLUMN `ticket_group_observer` int unsigned DEFAULT NULL AFTER `ticket_entity_id`");
            $DB->doQuery("ALTER TABLE `glpi_plugin_glpipersonalizado_services` 
                ADD KEY `ticket_group_observer` (`ticket_group_observer`)");
        }
        
        // ticket_requesttype_id
        if (!$DB->fieldExists('glpi_plugin_glpipersonalizado_services', 'ticket_requesttype_id')) {
            $DB->doQuery("ALTER TABLE `glpi_plugin_glpipersonalizado_services` 
                ADD COLUMN `ticket_requesttype_id` int unsigned DEFAULT NULL AFTER `ticket_group_observer`");
            $DB->doQuery("ALTER TABLE `glpi_plugin_glpipersonalizado_services` 
                ADD KEY `ticket_requesttype_id` (`ticket_requesttype_id`)");
        }
    }

    // Tabela de vínculos: serviços disponíveis para usuários/grupos/perfis
    if (!$DB->tableExists('glpi_plugin_glpipersonalizado_services_targets')) {
        $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_services_targets` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `service_id` int unsigned NOT NULL,
            `target_type` varchar(50) NOT NULL,
            `target_id` int unsigned NOT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_target` (`service_id`, `target_type`, `target_id`),
            KEY `service_id` (`service_id`),
            KEY `target_type` (`target_type`),
            KEY `target_id` (`target_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
        $DB->doQuery($query);
    }

    // Tabela de personalização do portal
if (!$DB->tableExists('glpi_plugin_glpipersonalizado_portal_settings')) {
    $query = "CREATE TABLE `glpi_plugin_glpipersonalizado_portal_settings` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `setting_name` varchar(100) NOT NULL,
        `setting_value` text,
        `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_name` (`setting_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
    $DB->doQuery($query);
    
    // Inserir configurações padrão
    $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
        'setting_name' => 'primary_color',
        'setting_value' => '#3b82f6'
    ]);
    
    $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
        'setting_name' => 'logo_url',
        'setting_value' => ''
    ]);
    
    $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
        'setting_name' => 'portal_title',
        'setting_value' => 'Portal de Serviços'
    ]);
    
    $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
        'setting_name' => 'welcome_message',
        'setting_value' => ''
    ]);
    
    $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
        'setting_name' => 'header_text_color',
        'setting_value' => '#ffffff'
    ]);
} else {
    // Verificar se o novo campo existe, se não, adicionar
    $iterator = $DB->request([
        'FROM' => 'glpi_plugin_glpipersonalizado_portal_settings',
        'WHERE' => ['setting_name' => 'header_text_color']
    ]);
    
    if (count($iterator) === 0) {
        $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
            'setting_name' => 'header_text_color',
            'setting_value' => '#ffffff'
        ]);
    }
}

    return true;
}

function plugin_glpipersonalizado_uninstall(): bool {
    // As tabelas sao mantidas: reinstalar o plugin recupera usuarios, catalogo e personalizacao
    if (isset($_SESSION['glpipersonalizado_checked'])) {
        unset($_SESSION['glpipersonalizado_checked']);
    }

    return true;
}