# GLPI Personalizado para GLPI

> Autor: **GLPI Salvador** · Licença: **GPLv3** · Compatível com GLPI **11.0.0 a 12.x**

Um **portal de serviços próprio** para os usuários que você escolher, separado da interface padrão do GLPI. É indicado para clientes ou áreas que só precisam abrir e acompanhar chamados.

## O que o plugin faz

### Redirecionamento após o login
- Os usuários escolhidos (individualmente, por **grupo** ou por **perfil**) vão direto para o portal depois de entrar no GLPI.
- O redirecionamento só acontece na navegação de páginas. Requisições de API, arquivos e logout não são afetados.

### Catálogo de serviços
- **Categorias** e **serviços** cadastrados pela tela, com ícone (os ícones Tabler do GLPI), descrição, ordem e ativação.
- Cada serviço já define como o chamado nasce: título, descrição, categoria, tipo, prioridade, urgência, impacto, entidade, grupo observador e origem da requisição.
- **Vínculos:** você define quais serviços cada usuário, grupo ou perfil pode ver.
- Ao escolher um serviço, o usuário abre o **chamado** em seu nome, pela criação nativa do GLPI.

### Portal
- Visual próprio, com as cores geradas a partir da **cor principal** configurada.
- **Meus chamados:** situação, linha do tempo e **comentários**, que entram como acompanhamento público.
- **Validações:** o usuário vê os pedidos de aprovação que são dele e **aprova ou recusa** pelo próprio portal.
- Mensagens de erro do GLPI aparecem no portal, para o usuário entender o que faltou.

## Configuração

Em *Configurar → Plugins → GLPI Personalizado*, nas abas:
- **Usuários e acesso:** quem vai para o portal (usuários, grupos e perfis);
- **Catálogo de serviços:** categorias e serviços;
- **Vínculos de serviços:** quem pode usar cada serviço;
- **Personalização do portal:** nome, textos, cor principal e demais ajustes visuais.

---

## Download e instalação

1. Baixe o arquivo `glpipersonalizado-X.Y.Z.zip` da **[última versão](../../releases/latest)**. Use o arquivo anexado à release, não o "Source code".
2. Descompacte dentro da pasta `plugins/` do GLPI. O resultado deve ser `plugins/glpipersonalizado/setup.php`.
3. Ajuste o dono dos arquivos para o usuário do servidor web, por exemplo:
   ```bash
   chown -R www-data:www-data /var/www/glpi/plugins/glpipersonalizado
   ```
4. No GLPI, vá em **Configurar → Plugins** e clique em **Instalar** e depois em **Ativar**. Pela linha de comando:
   ```bash
   php bin/console plugin:install glpipersonalizado -u <usuário administrador>
   php bin/console plugin:activate glpipersonalizado
   ```

A instalação cria as tabelas, as configurações padrão e as ações automáticas do plugin, e funciona num GLPI sem nada configurado antes.

### Atualização

Substitua a pasta `plugins/glpipersonalizado` pela versão nova e rode **Instalar** de novo, ou `php bin/console plugin:install glpipersonalizado -f`. Depois, ative o plugin. As tabelas e colunas novas são criadas sem perder os dados.

### Desinstalação

A desinstalação **não apaga as tabelas do plugin**: reinstalar recupera os dados.

## Versões

O histórico, com o que mudou em cada versão e o arquivo para download, está em **[Releases](../../releases)**. Cada versão entrou por um **[pull request](../../pulls?q=is%3Apr)**.

## Licença

Distribuído sob a **GNU General Public License v3.0**. Veja o arquivo [LICENSE](LICENSE).