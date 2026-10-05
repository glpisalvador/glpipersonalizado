<?php

/**
 * Classe de configuração do plugin glpipersonalizado
 */

class PluginGlpipersonalizadoConfig extends CommonGLPI {

    // $rightname nao e redeclarada: e tipada (string) no GLPI 12 e sem tipo no GLPI 11.
    // Os direitos sao verificados pelos metodos can*() abaixo.

    /**
     * GLPI 11 exige token CSRF nos formularios; no 12 a protecao e por cabecalho e o token foi removido
     */
    static function usaTokenCsrf(): bool {
        return version_compare(GLPI_VERSION, '12.0.0-dev', '<');
    }

    static function tokenCsrf(): string {
        return self::usaTokenCsrf() ? Session::getNewCSRFToken() : '';
    }

    /**
     * Nega o acesso a pagina (Html::displayRightError nao existe mais no GLPI 12)
     */
    static function negarAcesso(): never {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }

    static function canUpdate(): bool {
        return Session::haveRight('config', UPDATE);
    }

    static function canDelete(): bool {
        return Session::haveRight('config', UPDATE);
    }

    static function canPurge(): bool {
        return Session::haveRight('config', UPDATE);
    }

    static function getTypeName($nb = 0): string {
        return 'GLPI Personalizado';
    }

    static function canView(): bool {
        return Session::haveRight('config', READ);
    }

    static function canCreate(): bool {
        return Session::haveRight('config', UPDATE);
    }

    // =========================================================================
    // MÉTODOS PARA USUÁRIOS
    // =========================================================================

    static function getSelectedUsers(): array {
        global $DB;
        $users = [];

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_users')) {
            return $users;
        }

        $iterator = $DB->request(['FROM' => 'glpi_plugin_glpipersonalizado_users']);
        foreach ($iterator as $row) {
            $users[] = (int) $row['users_id'];
        }

        return $users;
    }

    static function saveSelectedUsers(array $users_ids): bool {
        global $DB;

        $DB->delete('glpi_plugin_glpipersonalizado_users', ['id' => ['>', 0]]); // limpa a lista (criterio aceito no GLPI 11 e 12)

        foreach ($users_ids as $user_id) {
            $user_id = (int) $user_id;
            if ($user_id > 0) {
                $DB->insert('glpi_plugin_glpipersonalizado_users', [
                    'users_id' => $user_id
                ]);
            }
        }

        return true;
    }

    // =========================================================================
    // MÉTODOS PARA GRUPOS
    // =========================================================================

    static function getSelectedGroups(): array {
        global $DB;
        $groups = [];

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_groups')) {
            return $groups;
        }

        $iterator = $DB->request(['FROM' => 'glpi_plugin_glpipersonalizado_groups']);
        foreach ($iterator as $row) {
            $groups[] = (int) $row['groups_id'];
        }

        return $groups;
    }

    static function saveSelectedGroups(array $groups_ids): bool {
        global $DB;

        $DB->delete('glpi_plugin_glpipersonalizado_groups', ['id' => ['>', 0]]); // limpa a lista (criterio aceito no GLPI 11 e 12)

        foreach ($groups_ids as $group_id) {
            $group_id = (int) $group_id;
            if ($group_id > 0) {
                $DB->insert('glpi_plugin_glpipersonalizado_groups', [
                    'groups_id' => $group_id
                ]);
            }
        }

        return true;
    }

    // =========================================================================
    // MÉTODOS PARA PERFIS
    // =========================================================================

    static function getSelectedProfiles(): array {
        global $DB;
        $profiles = [];

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_profiles')) {
            return $profiles;
        }

        $iterator = $DB->request(['FROM' => 'glpi_plugin_glpipersonalizado_profiles']);
        foreach ($iterator as $row) {
            $profiles[] = (int) $row['profiles_id'];
        }

        return $profiles;
    }

    static function saveSelectedProfiles(array $profiles_ids): bool {
        global $DB;

        $DB->delete('glpi_plugin_glpipersonalizado_profiles', ['id' => ['>', 0]]); // limpa a lista (criterio aceito no GLPI 11 e 12)

        foreach ($profiles_ids as $profile_id) {
            $profile_id = (int) $profile_id;
            if ($profile_id > 0) {
                $DB->insert('glpi_plugin_glpipersonalizado_profiles', [
                    'profiles_id' => $profile_id
                ]);
            }
        }

        return true;
    }

    // =========================================================================
    // MÉTODOS PARA VÍNCULOS DE SERVIÇOS
    // =========================================================================

    /**
     * Obtém os serviços vinculados a um alvo (user, group, profile)
     */
    static function getServicesForTarget(string $target_type, int $target_id): array {
        global $DB;
        $services = [];

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_services_targets')) {
            return $services;
        }

        $iterator = $DB->request([
            'SELECT' => 'service_id',
            'FROM' => 'glpi_plugin_glpipersonalizado_services_targets',
            'WHERE' => [
                'target_type' => $target_type,
                'target_id' => $target_id
            ]
        ]);

        foreach ($iterator as $row) {
            $services[] = (int) $row['service_id'];
        }

        return $services;
    }

    /**
     * Salva os serviços vinculados a um alvo
     */
    static function saveServicesForTarget(string $target_type, int $target_id, array $service_ids): bool {
        global $DB;

        // Remove vínculos antigos
        $DB->delete('glpi_plugin_glpipersonalizado_services_targets', [
            'target_type' => $target_type,
            'target_id' => $target_id
        ]);

        // Insere novos vínculos
        foreach ($service_ids as $service_id) {
            $service_id = (int) $service_id;
            if ($service_id > 0) {
                $DB->insert('glpi_plugin_glpipersonalizado_services_targets', [
                    'service_id' => $service_id,
                    'target_type' => $target_type,
                    'target_id' => $target_id
                ]);
            }
        }

        return true;
    }

    /**
     * Obtém todos os alvos vinculados a um serviço
     */
    static function getTargetsForService(int $service_id): array {
        global $DB;
        $targets = [
            'users' => [],
            'groups' => [],
            'profiles' => []
        ];

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_services_targets')) {
            return $targets;
        }

        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_glpipersonalizado_services_targets',
            'WHERE' => ['service_id' => $service_id]
        ]);

        foreach ($iterator as $row) {
            switch ($row['target_type']) {
                case 'user':
                    $targets['users'][] = (int) $row['target_id'];
                    break;
                case 'group':
                    $targets['groups'][] = (int) $row['target_id'];
                    break;
                case 'profile':
                    $targets['profiles'][] = (int) $row['target_id'];
                    break;
            }
        }

        return $targets;
    }

    // =========================================================================
    // MÉTODOS PARA PERSONALIZAÇÃO DO PORTAL
    // =========================================================================

    /**
     * Obtém uma configuração do portal
     */
    static function getPortalSetting(string $setting_name, $default = null) {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_portal_settings')) {
            return $default;
        }

        $iterator = $DB->request([
            'SELECT' => 'setting_value',
            'FROM' => 'glpi_plugin_glpipersonalizado_portal_settings',
            'WHERE' => ['setting_name' => $setting_name]
        ]);

        foreach ($iterator as $row) {
            return $row['setting_value'];
        }

        return $default;
    }

    /**
     * Salva uma configuração do portal
     */
    static function savePortalSetting(string $setting_name, $setting_value): bool {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_portal_settings')) {
            return false;
        }

        $exists = $DB->request([
            'COUNT' => 'cnt',
            'FROM' => 'glpi_plugin_glpipersonalizado_portal_settings',
            'WHERE' => ['setting_name' => $setting_name]
        ])->current();

        if ($exists['cnt'] > 0) {
            return $DB->update('glpi_plugin_glpipersonalizado_portal_settings',
                ['setting_value' => $setting_value],
                ['setting_name' => $setting_name]
            );
        } else {
            return $DB->insert('glpi_plugin_glpipersonalizado_portal_settings', [
                'setting_name' => $setting_name,
                'setting_value' => $setting_value
            ]);
        }
    }

    /**
     * Obtém todas as configurações do portal
     */
    static function getAllPortalSettings(): array {
        global $DB;

        $settings = [
            'primary_color' => '#3b82f6',
            'logo_url' => '',
            'portal_title' => 'Portal de Serviços',
            'welcome_message' => ''
        ];

        if (!$DB->tableExists('glpi_plugin_glpipersonalizado_portal_settings')) {
            return $settings;
        }

        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_glpipersonalizado_portal_settings'
        ]);

        foreach ($iterator as $row) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Salva todas as configurações do portal de uma vez
     */
    static function saveAllPortalSettings(array $settings): bool {
        foreach ($settings as $name => $value) {
            self::savePortalSetting($name, $value);
        }
        return true;
    }

    /**
     * Gera as variações de cor a partir da cor primária
     */
    static function generateColorVariations(string $hex_color): array {
        // Remove # se existir
        $hex = ltrim($hex_color, '#');
        
        // Converte para RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return [
            'primary' => $hex_color,
            'primary_rgb' => "$r, $g, $b",
            'primary_light' => "rgba($r, $g, $b, 0.1)",
            'primary_lighter' => "rgba($r, $g, $b, 0.08)",
            'primary_border' => "rgba($r, $g, $b, 0.25)",
            'primary_icon_bg' => "rgba($r, $g, $b, 0.15)",
            'primary_hover' => self::adjustBrightness($hex_color, -20),
            'primary_dark' => self::adjustBrightness($hex_color, -40),
        ];
    }

    /**
     * Ajusta o brilho de uma cor hex
     */
    static function adjustBrightness(string $hex_color, int $steps): string {
        $hex = ltrim($hex_color, '#');
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

    // =========================================================================
    // VERIFICAR SE USUÁRIO DEVE SER REDIRECIONADO
    // =========================================================================

    static function shouldRedirectUser(int $user_id): bool {
        global $DB;

        if ($user_id <= 0) {
            return false;
        }

        $selected_users = self::getSelectedUsers();
        if (in_array($user_id, $selected_users)) {
            return true;
        }

        $selected_profiles = self::getSelectedProfiles();
        $active_profile_id = $_SESSION['glpiactiveprofile']['id'] ?? 0;
        if ($active_profile_id > 0 && in_array($active_profile_id, $selected_profiles)) {
            return true;
        }

        $selected_groups = self::getSelectedGroups();
        if (!empty($selected_groups)) {
            $user_groups = [];
            
            if ($DB->tableExists('glpi_groups_users')) {
                $iterator = $DB->request([
                    'SELECT' => 'groups_id',
                    'FROM'   => 'glpi_groups_users',
                    'WHERE'  => ['users_id' => $user_id]
                ]);
                
                foreach ($iterator as $row) {
                    $user_groups[] = (int) $row['groups_id'];
                }
            }

            foreach ($user_groups as $group_id) {
                if (in_array($group_id, $selected_groups)) {
                    return true;
                }
            }
        }

        return false;
    }

    // =========================================================================
    // OBTER SERVIÇOS DISPONÍVEIS PARA O USUÁRIO
    // =========================================================================

    /**
     * Retorna os serviços disponíveis para um usuário específico
     * Considera vínculos diretos, por grupo e por perfil
     */
    static function getAvailableServicesForUser(int $user_id): array {
        global $DB;

        $service_ids = [];

        // 1. Serviços vinculados diretamente ao usuário
        $user_services = self::getServicesForTarget('user', $user_id);
        $service_ids = array_merge($service_ids, $user_services);

        // 2. Serviços vinculados ao perfil ativo
        $active_profile_id = $_SESSION['glpiactiveprofile']['id'] ?? 0;
        if ($active_profile_id > 0) {
            $profile_services = self::getServicesForTarget('profile', $active_profile_id);
            $service_ids = array_merge($service_ids, $profile_services);
        }

        // 3. Serviços vinculados aos grupos do usuário
        if ($DB->tableExists('glpi_groups_users')) {
            $iterator = $DB->request([
                'SELECT' => 'groups_id',
                'FROM'   => 'glpi_groups_users',
                'WHERE'  => ['users_id' => $user_id]
            ]);
            
            foreach ($iterator as $row) {
                $group_services = self::getServicesForTarget('group', (int) $row['groups_id']);
                $service_ids = array_merge($service_ids, $group_services);
            }
        }

        // Remover duplicados
        $service_ids = array_unique($service_ids);

        // Carregar dados completos dos serviços
        $services = [];
        if (!empty($service_ids)) {
            $iterator = $DB->request([
                'FROM' => 'glpi_plugin_glpipersonalizado_services',
                'WHERE' => [
                    'id' => $service_ids,
                    'is_active' => 1
                ],
                'ORDER' => 'category_id ASC, position ASC'
            ]);

            foreach ($iterator as $row) {
                $services[] = $row;
            }
        }

        return $services;
    }

    /**
     * Retorna os serviços agrupados por categoria para um usuário
     */
    static function getAvailableServicesByCategoryForUser(int $user_id): array {
        global $DB;

        $services = self::getAvailableServicesForUser($user_id);
        
        if (empty($services)) {
            return [];
        }

        // Agrupar por categoria
        $category_ids = array_unique(array_column($services, 'category_id'));
        
        // Carregar categorias
        $categories = [];
        if (!empty($category_ids)) {
            $iterator = $DB->request([
                'FROM' => 'glpi_plugin_glpipersonalizado_categories',
                'WHERE' => [
                    'id' => $category_ids,
                    'is_active' => 1
                ],
                'ORDER' => 'position ASC'
            ]);

            foreach ($iterator as $row) {
                $categories[$row['id']] = [
                    'info' => $row,
                    'services' => []
                ];
            }
        }

        // Adicionar serviços às categorias
        foreach ($services as $service) {
            if (isset($categories[$service['category_id']])) {
                $categories[$service['category_id']]['services'][] = $service;
            }
        }

        // Remover categorias sem serviços
        foreach ($categories as $cat_id => $cat_data) {
            if (empty($cat_data['services'])) {
                unset($categories[$cat_id]);
            }
        }

        return $categories;
    }

    // =========================================================================
    // INFORMAÇÕES DO USUÁRIO PARA O PORTAL
    // =========================================================================

    static function getUserFullInfo(int $user_id): array {
        global $DB;

        $info = [
            'user'       => [],
            'profile'    => [],
            'profiles'   => [],
            'entity'     => [],
            'entities'   => [],
            'groups'     => [],
            'rights'     => [],
            'session'    => []
        ];

        if ($user_id <= 0) {
            return $info;
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_users',
            'WHERE' => ['id' => $user_id]
        ]);

        foreach ($iterator as $row) {
            $info['user'] = [
                'id'         => $row['id'],
                'name'       => $row['name'],
                'realname'   => $row['realname'] ?? '',
                'firstname'  => $row['firstname'] ?? '',
                'email'      => '',
                'phone'      => $row['phone'] ?? '',
                'mobile'     => $row['mobile'] ?? '',
                'language'   => $row['language'] ?? '',
                'date_mod'   => $row['date_mod'] ?? '',
                'last_login' => $row['last_login'] ?? ''
            ];
        }

        $iterator = $DB->request([
            'SELECT' => 'email',
            'FROM'   => 'glpi_useremails',
            'WHERE'  => [
                'users_id'   => $user_id,
                'is_default' => 1
            ]
        ]);

        foreach ($iterator as $row) {
            $info['user']['email'] = $row['email'];
        }

        if (isset($_SESSION['glpiactiveprofile'])) {
            $info['profile'] = [
                'id'        => $_SESSION['glpiactiveprofile']['id'] ?? 0,
                'name'      => $_SESSION['glpiactiveprofile']['name'] ?? '',
                'interface' => $_SESSION['glpiactiveprofile']['interface'] ?? ''
            ];
        }

        if (isset($_SESSION['glpiprofiles'])) {
            foreach ($_SESSION['glpiprofiles'] as $prof_id => $prof_data) {
                $info['profiles'][] = [
                    'id'   => $prof_id,
                    'name' => $prof_data['name'] ?? ''
                ];
            }
        }

        if (isset($_SESSION['glpiactive_entity'])) {
            $entity_id = $_SESSION['glpiactive_entity'];
            $iterator = $DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM'   => 'glpi_entities',
                'WHERE'  => ['id' => $entity_id]
            ]);

            foreach ($iterator as $row) {
                $info['entity'] = [
                    'id'           => $row['id'],
                    'name'         => $row['name'],
                    'completename' => $row['completename'],
                    'is_recursive' => $_SESSION['glpiactive_entity_recursive'] ?? false
                ];
            }
        }

        $iterator = $DB->request([
            'SELECT' => ['g.id', 'g.name', 'g.completename'],
            'FROM'   => 'glpi_groups AS g',
            'INNER JOIN' => [
                'glpi_groups_users AS gu' => [
                    'ON' => [
                        'gu' => 'groups_id',
                        'g'  => 'id'
                    ]
                ]
            ],
            'WHERE' => ['gu.users_id' => $user_id],
            'ORDER' => 'g.completename ASC'
        ]);

        foreach ($iterator as $row) {
            $info['groups'][] = [
                'id'           => $row['id'],
                'name'         => $row['name'],
                'completename' => $row['completename']
            ];
        }

        $info['session'] = [
            'glpiID'             => $_SESSION['glpiID'] ?? null,
            'glpiname'           => $_SESSION['glpiname'] ?? '',
            'glpifriendlyname'   => $_SESSION['glpifriendlyname'] ?? '',
            'glpilanguage'       => $_SESSION['glpilanguage'] ?? '',
            'glpi_currenttime'   => $_SESSION['glpi_currenttime'] ?? '',
            'glpiactiveentities' => $_SESSION['glpiactiveentities'] ?? [],
            'glpigroups'         => $_SESSION['glpigroups'] ?? []
        ];

        return $info;
    }

    // =========================================================================
    // AÇÕES DO PORTAL (classes nativas do GLPI: regras, SLA, notificações e histórico)
    // =========================================================================

    /**
     * Texto simples digitado no portal -> HTML do GLPI
     */
    static function textoParaHtml(string $texto): string {
        return '<p>' . nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    /**
     * Mensagens de erro que o GLPI deixou na sessão durante add/update
     */
    static function errosDaSessao(): string {
        $erros = $_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] ?? [];
        unset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR]);
        return trim(strip_tags(implode(' ', (array) $erros)));
    }

    /**
     * Abre o chamado de um serviço do catálogo em nome do usuário
     */
    static function criarChamado(int $service_id, int $user_id, string $titulo, string $conteudo, bool $notificar = true): array {
        global $DB;

        $servico = null;
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_glpipersonalizado_services',
            'WHERE' => ['id' => $service_id, 'is_active' => 1]
        ]) as $row) {
            $servico = $row;
        }
        if ($servico === null) {
            return ['ok' => false, 'id' => 0, 'erro' => 'Serviço não encontrado.'];
        }
        // O serviço precisa estar liberado para este usuário
        $liberados = array_column(self::getAvailableServicesForUser($user_id), 'id');
        if (!in_array($service_id, array_map('intval', $liberados), true)) {
            return ['ok' => false, 'id' => 0, 'erro' => 'Serviço não disponível para você.'];
        }

        $entidade = (int) ($servico['ticket_entity_id'] ?? 0);
        if ($entidade <= 0) {
            $entidade = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        }
        $origem = (int) ($servico['ticket_requesttype_id'] ?? 0);

        $atores = [
            'requester' => [['itemtype' => 'User', 'items_id' => $user_id, 'use_notification' => 1, 'alternative_email' => '']]
        ];
        $grupo_observador = (int) ($servico['ticket_group_observer'] ?? 0);
        if ($grupo_observador > 0) {
            $atores['observer'] = [['itemtype' => 'Group', 'items_id' => $grupo_observador]];
        }

        $entrada = [
            'name'              => $titulo,
            'content'           => self::textoParaHtml($conteudo),
            'entities_id'       => $entidade,
            'type'              => (int) ($servico['ticket_type'] ?? 1) ?: 1,
            'urgency'           => (int) ($servico['ticket_urgency'] ?? 3) ?: 3,
            'impact'            => (int) ($servico['ticket_impact'] ?? 3) ?: 3,
            'priority'          => (int) ($servico['ticket_priority'] ?? 3) ?: 3,
            'itilcategories_id' => (int) ($servico['ticket_category_id'] ?? 0),
            '_actors'           => $atores
        ];
        if ($origem > 0) {
            $entrada['requesttypes_id'] = $origem;
        }
        if (!$notificar) {
            $entrada['_disablenotif'] = true;
        }

        $ticket = new Ticket();
        $id = (int) $ticket->add($entrada);
        if ($id <= 0) {
            $erro = self::errosDaSessao();
            return ['ok' => false, 'id' => 0, 'erro' => 'Erro ao criar o chamado.' . ($erro !== '' ? ' ' . $erro : '')];
        }
        return ['ok' => true, 'id' => $id, 'erro' => ''];
    }

    /**
     * Usuário é requerente do chamado ou aprovador de uma validação dele
     */
    static function participaDoChamado(int $ticket_id, int $user_id): bool {
        global $DB;

        if (countElementsInTable('glpi_tickets_users', ['tickets_id' => $ticket_id, 'users_id' => $user_id, 'type' => CommonITILActor::REQUESTER]) > 0) {
            return true;
        }
        return countElementsInTable('glpi_ticketvalidations', [
            'tickets_id' => $ticket_id,
            'OR' => [
                ['users_id_validate' => $user_id],
                ['itemtype_target' => 'User', 'items_id_target' => $user_id]
            ]
        ]) > 0;
    }

    /**
     * Comentário (acompanhamento público) do usuário no chamado
     */
    static function adicionarComentario(int $ticket_id, int $user_id, string $conteudo, bool $notificar = true): array {
        if ($ticket_id <= 0 || trim($conteudo) === '') {
            return ['ok' => false, 'erro' => 'Preencha o comentário.'];
        }
        if (!self::participaDoChamado($ticket_id, $user_id)) {
            return ['ok' => false, 'erro' => 'Você não tem permissão para comentar neste chamado.'];
        }

        $entrada = [
            'itemtype'   => 'Ticket',
            'items_id'   => $ticket_id,
            'users_id'   => $user_id,
            'content'    => self::textoParaHtml($conteudo),
            'is_private' => 0
        ];
        if (!$notificar) {
            $entrada['_disablenotif'] = true;
        }

        $followup = new ITILFollowup();
        if (!$followup->add($entrada)) {
            $erro = self::errosDaSessao();
            return ['ok' => false, 'erro' => 'Erro ao adicionar comentário.' . ($erro !== '' ? ' ' . $erro : '')];
        }
        return ['ok' => true, 'erro' => ''];
    }

    /**
     * Aprova ou recusa uma validação pendente do usuário.
     * O GLPI registra a data, recalcula a validação global do chamado e notifica.
     */
    static function responderValidacao(int $validation_id, int $user_id, string $acao, string $comentario, bool $notificar = true): array {
        global $DB;

        if ($validation_id <= 0 || !in_array($acao, ['approve', 'refuse'], true)) {
            return ['ok' => false, 'tickets_id' => 0, 'erro' => 'Ação inválida.'];
        }
        if ($acao === 'refuse' && trim($comentario) === '') {
            return ['ok' => false, 'tickets_id' => 0, 'erro' => 'Informe o motivo da recusa.'];
        }

        $pendente = null;
        foreach ($DB->request([
            'FROM'  => 'glpi_ticketvalidations',
            'WHERE' => [
                'id'     => $validation_id,
                'status' => CommonITILValidation::WAITING,
                'OR'     => [
                    ['users_id_validate' => $user_id],
                    ['itemtype_target' => 'User', 'items_id_target' => $user_id]
                ]
            ]
        ]) as $row) {
            $pendente = $row;
        }
        if ($pendente === null) {
            return ['ok' => false, 'tickets_id' => 0, 'erro' => 'Validação não encontrada ou você não tem permissão.'];
        }

        $entrada = [
            'id'                 => $validation_id,
            'status'             => $acao === 'approve' ? CommonITILValidation::ACCEPTED : CommonITILValidation::REFUSED,
            'comment_validation' => trim($comentario)
        ];
        if (!$notificar) {
            $entrada['_disablenotif'] = true;
        }

        $validacao = new TicketValidation();
        if (!$validacao->getFromDB($validation_id) || !$validacao->update($entrada)) {
            $erro = self::errosDaSessao();
            return ['ok' => false, 'tickets_id' => (int) $pendente['tickets_id'], 'erro' => 'Erro ao processar validação.' . ($erro !== '' ? ' ' . $erro : '')];
        }
        return ['ok' => true, 'tickets_id' => (int) $pendente['tickets_id'], 'erro' => ''];
    }
}