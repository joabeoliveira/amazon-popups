<?php
/**
 * Plugin Name: Popup de Afiliados Amazon
 * Description: Exibe popups com produtos de afiliado Amazon do WooCommerce e posts comuns
 * Version: 2.8
 * Author: Joabe Antonio de Oliveira
 */

// Segurança - impede acesso direto
if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliatePopup {
    
    private $options;
    private $associate_tag;
    
    public function __construct() {
        // Inicializa custom post types
        add_action('init', array($this, 'register_custom_post_types'));
        
        // Adiciona metabox para termos do glossário
        add_action('add_meta_boxes', array($this, 'add_glossary_meta_boxes'));
        
        // Inicializa o plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Carrega configurações
        $this->options = get_option('amazon_affiliate_settings');
        $this->associate_tag = isset($this->options['associate_tag']) ? $this->options['associate_tag'] : '';
        
        // Adiciona menu de administração
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        
        // Adiciona scripts e estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Adiciona AJAX para buscar produtos
        add_action('wp_ajax_get_amazon_products', array($this, 'get_amazon_products'));
        add_action('wp_ajax_nopriv_get_amazon_products', array($this, 'get_amazon_products'));
        
        // Shortcodes para exibir produtos manualmente
        add_shortcode('amazon_products', array($this, 'amazon_products_shortcode'));
        add_shortcode('amazon_banner', array($this, 'amazon_banner_shortcode'));
        add_shortcode('amazon_campaign', array($this, 'amazon_campaign_shortcode'));
        add_shortcode('amazon_glossary', array($this, 'amazon_glossary_shortcode'));
        
        // Hook para verificar links Amazon em produtos WooCommerce
        add_action('save_post', array($this, 'check_amazon_links'), 20, 3);
        
        // Hook para salvar dados dos metaboxes
        add_action('save_post', array($this, 'save_meta_box_data'), 10, 2);
        
        // Adiciona coluna personalizada na lista de produtos
        add_filter('manage_product_posts_columns', array($this, 'add_affiliate_column'));
        add_action('manage_product_posts_custom_column', array($this, 'affiliate_column_content'), 10, 2);
        
        // Adiciona coluna personalizada na lista de posts
        add_filter('manage_post_posts_columns', array($this, 'add_affiliate_column'));
        add_action('manage_post_posts_custom_column', array($this, 'affiliate_column_content'), 10, 2);
        
        // Adiciona dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    // Adiciona menu de administração
    public function add_admin_menu() {
        add_menu_page(
            'Popup Amazon Afiliados',
            'Popup Amazon',
            'manage_options',
            'amazon_affiliate_popup',
            array($this, 'options_page'),
            'dashicons-cart',
            30
        );

        add_submenu_page(
            'amazon_affiliate_popup',
            'Configurações',
            'Configurações',
            'manage_options',
            'amazon_affiliate_popup',
            array($this, 'options_page')
        );
        
        add_submenu_page(
            'amazon_affiliate_popup',
            'Estatísticas',
            'Estatísticas',
            'manage_options',
            'amazon_affiliate_stats',
            array($this, 'stats_page')
        );
        
        add_submenu_page(
            'amazon_affiliate_popup',
            'Documentação',
            'Documentação',
            'manage_options',
            'amazon_affiliate_docs',
            array($this, 'docs_page')
        );
        
        add_submenu_page(
            'amazon_affiliate_popup',
            'Campanhas Ativas',
            'Campanhas Ativas',
            'manage_options',
            'amazon_affiliate_campaigns',
            array($this, 'campaigns_page')
        );
    }
    
    // Inicializa as configurações
    public function settings_init() {
        register_setting('amazon_affiliate_settings', 'amazon_affiliate_settings');
        
        add_settings_section(
            'amazon_affiliate_general_section',
            'Configurações Gerais',
            array($this, 'settings_section_callback'),
            'amazon_affiliate_settings'
        );
        
        add_settings_field(
            'associate_tag',
            'ID de Associado Amazon',
            array($this, 'associate_tag_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
        
        add_settings_field(
            'popup_delay',
            'Tempo para exibir (segundos)',
            array($this, 'popup_delay_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
        
        add_settings_field(
            'popup_display',
            'Onde exibir',
            array($this, 'popup_display_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
        
        add_settings_field(
            'auto_detect',
            'Detecção automática de links Amazon',
            array($this, 'auto_detect_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );

        add_settings_field(
            'show_in',
            'Exibir em quais tipos de conteúdo',
            array($this, 'show_in_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
        
        add_settings_field(
            'display_position',
            'Posição de exibição',
            array($this, 'display_position_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
        
        add_settings_field(
            'display_type',
            'Tipo de exibição',
            array($this, 'display_type_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
        
        add_settings_field(
            'campaign_duration',
            'Duração das campanhas',
            array($this, 'campaign_duration_render'),
            'amazon_affiliate_settings',
            'amazon_affiliate_general_section'
        );
    }
    
    public function settings_section_callback() {
        echo '<div class="amazon-settings-info">';
        echo '<p>Configure as opções do popup de produtos Amazon. O plugin detecta automaticamente produtos do WooCommerce e posts comuns com links de afiliado Amazon.</p>';
        echo '</div>';
    }
    
    public function associate_tag_render() {
        $tag = isset($this->options['associate_tag']) ? $this->options['associate_tag'] : '';
        echo '<input type="text" name="amazon_affiliate_settings[associate_tag]" value="' . esc_attr($tag) . '" class="regular-text">';
        echo '<p class="description">Seu ID de associado Amazon (ex: seuid-20). Este tag será adicionado automaticamente a todos os links Amazon.</p>';
    }
    
    public function popup_delay_render() {
        $delay = isset($this->options['popup_delay']) ? $this->options['popup_delay'] : 15;
        echo '<input type="number" name="amazon_affiliate_settings[popup_delay]" value="' . esc_attr($delay) . '" min="5" max="60">';
        echo '<p class="description">Tempo em segundos antes do popup ser exibido (entre 5 e 60 segundos).</p>';
    }
    
    public function popup_display_render() {
        $display = isset($this->options['popup_display']) ? $this->options['popup_display'] : 'all';
        
        echo '<select name="amazon_affiliate_settings[popup_display]">';
        echo '<option value="all" ' . selected($display, 'all', false) . '>Todos os posts</option>';
        echo '<option value="category" ' . selected($display, 'category', false) . '>Apenas em categorias específicas</option>';
        echo '<option value="none" ' . selected($display, 'none', false) . '>Não exibir automaticamente</option>';
        echo '</select>';
        echo '<p class="description">Controla onde o popup será exibido automaticamente.</p>';
    }

    public function show_in_render() {
        $show_in = isset($this->options['show_in']) ? $this->options['show_in'] : array('post');
        
        echo '<label><input type="checkbox" name="amazon_affiliate_settings[show_in][]" value="post" ' . checked(in_array('post', $show_in), true, false) . '> Posts</label><br>';
        echo '<label><input type="checkbox" name="amazon_affiliate_settings[show_in][]" value="page" ' . checked(in_array('page', $show_in), true, false) . '> Páginas</label><br>';
        
        if (post_type_exists('product')) {
            echo '<label><input type="checkbox" name="amazon_affiliate_settings[show_in][]" value="product" ' . checked(in_array('product', $show_in), true, false) . '> Produtos WooCommerce</label>';
        }
        
        echo '<p class="description">Selecione em quais tipos de conteúdo o popup deve aparecer.</p>';
    }
    
    public function auto_detect_render() {
        $auto_detect = isset($this->options['auto_detect']) ? $this->options['auto_detect'] : '1';
        echo '<input type="checkbox" name="amazon_affiliate_settings[auto_detect]" value="1" ' . checked($auto_detect, '1', false) . '>';
        echo '<label>Ativar detecção automática de links Amazon</label>';
        echo '<p class="description">Quando ativado, o plugin irá verificar automaticamente links Amazon em novos conteúdos.</p>';
    }
    
    public function display_position_render() {
        $position = isset($this->options['display_position']) ? $this->options['display_position'] : 'auto';
        
        echo '<select name="amazon_affiliate_settings[display_position]">';
        echo '<option value="auto" ' . selected($position, 'auto', false) . '>Automático (popup)</option>';
        echo '<option value="header" ' . selected($position, 'header', false) . '>Cabeçalho</option>';
        echo '<option value="footer" ' . selected($position, 'footer', false) . '>Rodapé</option>';
        echo '<option value="before_content" ' . selected($position, 'before_content', false) . '>Antes do conteúdo</option>';
        echo '<option value="after_content" ' . selected($position, 'after_content', false) . '>Depois do conteúdo</option>';
        echo '<option value="sidebar" ' . selected($position, 'sidebar', false) . '>Sidebar</option>';
        echo '</select>';
        echo '<p class="description">Onde os produtos Amazon serão exibidos na página.</p>';
    }
    
    public function display_type_render() {
        $type = isset($this->options['display_type']) ? $this->options['display_type'] : 'popup';
        
        echo '<select name="amazon_affiliate_settings[display_type]">';
        echo '<option value="popup" ' . selected($type, 'popup', false) . '>Popup</option>';
        echo '<option value="banner_horizontal" ' . selected($type, 'banner_horizontal', false) . '>Banner Horizontal</option>';
        echo '<option value="banner_vertical" ' . selected($type, 'banner_vertical', false) . '>Banner Vertical</option>';
        echo '<option value="sticky_bar" ' . selected($type, 'sticky_bar', false) . '>Barra Fixa</option>';
        echo '<option value="card" ' . selected($type, 'card', false) . '>Cartão</option>';
        echo '</select>';
        echo '<p class="description">Formato de exibição dos produtos Amazon.</p>';
    }
    
    public function campaign_duration_render() {
        $start_time = isset($this->options['campaign_start_time']) ? $this->options['campaign_start_time'] : '';
        $end_time = isset($this->options['campaign_end_time']) ? $this->options['campaign_end_time'] : '';
        
        echo '<div style="margin-bottom: 10px;">';
        echo '<label>Início da campanha:</label><br>';
        echo '<input type="datetime-local" name="amazon_affiliate_settings[campaign_start_time]" value="' . esc_attr($start_time) . '">';
        echo '</div>';
        
        echo '<div>';
        echo '<label>Fim da campanha:</label><br>';
        echo '<input type="datetime-local" name="amazon_affiliate_settings[campaign_end_time]" value="' . esc_attr($end_time) . '">';
        echo '</div>';
        
        echo '<p class="description">Defina períodos específicos para exibir campanhas. Deixe em branco para exibir sempre.</p>';
    }
    
    
    // Página de opções
    public function options_page() {
        ?>
        <div class="wrap amazon-settings-wrap">
            <h1><span class="dashicons dashicons-cart"></span> Configurações do Popup Amazon</h1>
            
            <div class="amazon-settings-container">
                <div class="amazon-settings-main">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('amazon_affiliate_settings');
                        do_settings_sections('amazon_affiliate_settings');
                        submit_button('Salvar Configurações');
                        ?>
                    </form>
                    
                    <div class="amazon-tools-section">
                        <h2>Ferramentas</h2>
                        <div class="amazon-tool-card">
                            <h3>Verificar Conteúdo Existente</h3>
                            <p>Procura por links Amazon em todos os produtos e posts existentes com barra de progresso em tempo real</p>
                            <button type="button" class="button button-primary" id="scan-all-products">Verificar Todos os Conteúdos</button>
                            <div id="scan-results" style="margin-top: 20px; display: none;">
                                <div id="scan-progress"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="amazon-settings-sidebar">
                    <div class="amazon-status-card">
                        <h3>Status do Sistema</h3>
                        <div class="amazon-status-item">
                            <span class="status-label">Posts com links Amazon:</span>
                            <span class="status-value"><?php echo $this->count_amazon_products('post'); ?></span>
                        </div>
                        <div class="amazon-status-item">
                            <span class="status-label">Produtos com links Amazon:</span>
                            <span class="status-value"><?php echo $this->count_amazon_products('product'); ?></span>
                        </div>
                        <div class="amazon-status-item">
                            <span class="status-label">Tag de associado:</span>
                            <span class="status-value"><?php echo !empty($this->associate_tag) ? 'Configurado' : 'Não configurado'; ?></span>
                        </div>
                    </div>
                    
                    <div class="amazon-help-card">
                        <h3>Ajuda Rápida</h3>
                        <ul>
                            <li><a href="<?php echo admin_url('admin.php?page=amazon_affiliate_docs'); ?>">Documentação completa</a></li>
                            <li><a href="<?php echo admin_url('admin.php?page=amazon_affiliate_stats'); ?>">Ver estatísticas detalhadas</a></li>
                            <li><a href="#" id="test-popup">Testar popup</a></li>
                            <li><a href="#" id="force-cache-clear" style="color: #d63384;">Forçar limpeza de cache</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Página de estatísticas
    public function stats_page() {
        ?>
        <div class="wrap">
            <h1>Estatísticas do Popup Amazon</h1>
            
            <div class="amazon-stats-grid">
                <div class="amazon-stat-card">
                    <h3>Conteúdo com Links Amazon</h3>
                    <div class="stat-number"><?php echo $this->count_amazon_products('post') + $this->count_amazon_products('product'); ?></div>
                    <p>itens no total</p>
                </div>
                
                <div class="amazon-stat-card">
                    <h3>Posts</h3>
                    <div class="stat-number"><?php echo $this->count_amazon_products('post'); ?></div>
                    <p>com links Amazon</p>
                </div>
                
                <div class="amazon-stat-card">
                    <h3>Produtos</h3>
                    <div class="stat-number"><?php echo $this->count_amazon_products('product'); ?></div>
                    <p>com links Amazon</p>
                </div>
            </div>
            
            <h2>Top Categorias com Produtos Amazon</h2>
            <?php
            $categories = get_categories(array(
                'orderby' => 'count',
                'order' => 'DESC',
                'number' => 10
            ));
            
            echo '<ul>';
            foreach ($categories as $category) {
                $count = $this->count_posts_in_category($category->term_id);
                if ($count > 0) {
                    echo '<li>' . $category->name . ' (' . $count . ' produtos)</li>';
                }
            }
            echo '</ul>';
            ?>
        </div>
        <?php
    }
    
    // Página de documentação
    public function docs_page() {
        ?>
        <div class="wrap">
            <h1>Documentação do Popup Amazon</h1>
            
            <div class="amazon-docs-container">
                <div class="amazon-doc-section">
                    <h2>Como usar o plugin</h2>
                    <p>O plugin Popup Amazon v2.0 oferece um sistema completo de afiliados com recursos avançados de exibição e campanhas programadas.</p>
                    
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Popup automático inteligente</strong> - Sistema de prioridade para exibição de produtos</li>
                        <li><strong>Múltiplos shortcodes</strong> - 4 shortcodes diferentes para diversos tipos de exibição</li>
                        <li><strong>Sistema de campanhas</strong> - Campanhas programáveis com agendamento e segmentação</li>
                        <li><strong>Glossário integrado</strong> - Termos com produtos Amazon relacionados</li>
                        <li><strong>Múltiplas posições</strong> - Header, footer, antes/depois do conteúdo, sidebar</li>
                        <li><strong>Tipos de exibição variáveis</strong> - Popup, banners, sticky bar, cartões</li>
                        <li><strong>Detecção automática</strong> - Encontra links Amazon automaticamente</li>
                        <li><strong>Tag de associado</strong> - Adiciona seu ID automaticamente aos links</li>
                    </ul>
                    
                    <h3>Sistema de Prioridade</h3>
                    <div class="amazon-priority-system">
                        <p>O plugin usa um sistema inteligente de prioridade para exibir produtos:</p>
                        <ol>
                            <li><strong>Produtos customizados por página</strong> - IDs específicos definidos na metabox da página/post</li>
                            <li><strong>Campanha específica da página</strong> - Campanha selecionada na metabox</li>
                            <li><strong>Campanhas ativas por URL</strong> - Campanhas que correspondem à URL atual</li>
                            <li><strong>Produtos padrão por categoria</strong> - Comportamento original do plugin</li>
                        </ol>
                    </div>
                    
                    <h3>Shortcodes Disponíveis</h3>
                    
                    <h4>1. [amazon_products] - Exibir Produtos (Expandido)</h4>
                    <div class="amazon-code-example">
                        <p><strong>Parâmetros básicos:</strong></p>
                        <p><code>[amazon_products]</code> - Exibe 3 produtos padrão</p>
                        <p><code>[amazon_products count="5"]</code> - Exibe 5 produtos</p>
                        <p><code>[amazon_products post_type="product"]</code> - Apenas produtos WooCommerce</p>
                        
                        <p><strong>Filtros avançados:</strong></p>
                        <p><code>[amazon_products category="tecnologia"]</code> - Produtos de categoria específica</p>
                        <p><code>[amazon_products tag="promocao"]</code> - Produtos com tag específica</p>
                        <p><code>[amazon_products specific_urls="https://amazon.com/produto1,https://amazon.com/produto2"]</code> - URLs Amazon específicas</p>
                        
                        <p><strong>Personalização visual:</strong></p>
                        <p><code>[amazon_products template="grid"]</code> - Layout em grade (padrão)</p>
                        <p><code>[amazon_products template="list"]</code> - Layout em lista horizontal</p>
                        <p><code>[amazon_products template="carousel"]</code> - Layout carrossel com Bootstrap 5</p>
                        
                        <p><strong>Opções de exibição:</strong></p>
                        <p><code>[amazon_products show_description="no"]</code> - Ocultar descrições</p>
                        <p><code>[amazon_products target_blank="no"]</code> - Abrir na mesma janela</p>
                    </div>
                    
                    <h4>2. [amazon_banner] - Banners Promocionais</h4>
                    <div class="amazon-code-example">
                        <p><strong>Banner horizontal:</strong></p>
                        <p><code>[amazon_banner product_id="123" type="horizontal"]</code></p>
                        <p><code>[amazon_banner product_id="123" type="horizontal" title="Oferta Especial" subtitle="50% de desconto hoje!"]</code></p>
                        
                        <p><strong>Banner vertical:</strong></p>
                        <p><code>[amazon_banner product_id="456" type="vertical"]</code></p>
                        
                        <p><strong>Personalização completa:</strong></p>
                        <p><code>[amazon_banner product_id="789" type="horizontal" title="Black Friday" subtitle="Mega desconto!" button_text="Comprar Agora" background_color="#000000" text_color="#ffffff"]</code></p>
                    </div>
                    
                    <h4>3. [amazon_campaign] - Campanhas Específicas</h4>
                    <div class="amazon-code-example">
                        <p><code>[amazon_campaign campaign_id="123"]</code> - Exibe campanha por ID</p>
                        <p><code>[amazon_campaign campaign_id="456" display_type="banner_horizontal"]</code> - Força tipo de exibição</p>
                        
                        <p><strong>Campanhas condicionais:</strong></p>
                        <p><code>[amazon_campaign campaign_id="789" start_date="2024-12-01" end_date="2024-12-31"]</code></p>
                        <p><code>[amazon_campaign campaign_id="101" target_urls="/categoria/tech,/produto-especial"]</code></p>
                    </div>
                    
                    <h4>4. [amazon_glossary] - Glossário com Produtos</h4>
                    <div class="amazon-code-example">
                        <p><code>[amazon_glossary term="SEO"]</code> - Exibe definição do termo</p>
                        <p><code>[amazon_glossary term="Marketing Digital" show_products="yes"]</code> - Com produtos relacionados</p>
                        <p><code>[amazon_glossary term="E-commerce" show_products="yes" products_count="5"]</code> - 5 produtos relacionados</p>
                    </div>
                    
                    <h3>Sistema de Campanhas</h3>
                    <div class="amazon-campaigns-guide">
                        <h4>Criando Campanhas</h4>
                        <ol>
                            <li>Vá em <strong>Popup Amazon → Campanhas Ativas</strong></li>
                            <li>Clique em <strong>Criar Nova Campanha</strong></li>
                            <li>Configure os parâmetros da campanha</li>
                            <li>Defina produtos e URLs alvo</li>
                            <li>Publique a campanha</li>
                        </ol>
                        
                        <h4>Tipos de Campanhas</h4>
                        <ul>
                            <li><strong>Campanhas Globais:</strong> Exibidas em todo o site (deixe URLs alvo em branco)</li>
                            <li><strong>Campanhas Segmentadas:</strong> Apenas em URLs específicas</li>
                            <li><strong>Campanhas Temporais:</strong> Com data de início e fim</li>
                            <li><strong>Campanhas por Página:</strong> Configuradas individualmente em cada post/página</li>
                        </ul>
                        
                        <h4>Posições de Exibição</h4>
                        <ul>
                            <li><strong>Automático:</strong> Popup tradicional com delay</li>
                            <li><strong>Cabeçalho:</strong> Fixo no topo da página</li>
                            <li><strong>Rodapé:</strong> Fixo no final da página</li>
                            <li><strong>Antes do conteúdo:</strong> Incorporado antes do texto principal</li>
                            <li><strong>Depois do conteúdo:</strong> Incorporado após o texto principal</li>
                            <li><strong>Sidebar:</strong> Na barra lateral (se suportada pelo tema)</li>
                        </ul>
                        
                        <h4>Tipos de Exibição</h4>
                        <ul>
                            <li><strong>Popup:</strong> Modal sobreposto tradicional</li>
                            <li><strong>Banner Horizontal:</strong> Faixa larga com produto</li>
                            <li><strong>Banner Vertical:</strong> Coluna estreita com produto</li>
                            <li><strong>Sticky Bar:</strong> Barra fixa na parte inferior</li>
                            <li><strong>Cartão:</strong> Box destacado no conteúdo</li>
                        </ul>
                    </div>
                    
                    <h3>Sistema de Glossário</h3>
                    <div class="amazon-glossary-guide">
                        <h4>Criando Termos do Glossário</h4>
                        <ol>
                            <li>Vá em <strong>Glossário Amazon → Adicionar Termo</strong></li>
                            <li>Digite o título do termo</li>
                            <li>Escreva a definição no editor</li>
                            <li>Configure produtos relacionados na metabox</li>
                            <li>Publique o termo</li>
                        </ol>
                        
                        <h4>Configurando Produtos Relacionados</h4>
                        <ul>
                            <li><strong>Termos de Busca:</strong> Palavras-chave para busca automática</li>
                            <li><strong>IDs de Produtos:</strong> Posts/produtos específicos separados por vírgula</li>
                        </ul>
                    </div>
                    
                    <h3>Novidades da Versão 2.6 🆕</h3>
                    <div class="amazon-update-notice">
                        <h4>🎆 Carrossel Bootstrap Implementado!</h4>
                        <ul>
                            <li><strong>Performance Superior:</strong> Carrossel Bootstrap 5 nativo</li>
                            <li><strong>Responsividade Total:</strong> Funciona perfeitamente em todos os dispositivos</li>
                            <li><strong>Navegação Suave:</strong> Controles nativos com animações fluidas</li>
                            <li><strong>Acessibilidade:</strong> Totalmente acessível com navegação por teclado</li>
                            <li><strong>Design Premium:</strong> Cards modernos com hover effects</li>
                            <li><strong>3 Produtos por Slide:</strong> Layout otimizado para melhor visualização</li>
                        </ul>
                        <p><strong>Como usar:</strong> <code>[amazon_products template="carousel" count="9"]</code></p>
                        <p class="amazon-tip">💡 <strong>Dica:</strong> Use múltiplos de 3 no count para melhor aproveitamento dos slides!</p>
                    </div>
                    
                    <h3>Configurações Avançadas</h3>
                    <div class="amazon-advanced-settings">
                        <h4>Metaboxes por Página/Post</h4>
                        <p>Cada post e página possui uma metabox <strong>"Campanha Amazon Específica"</strong> onde você pode:</p>
                        <ul>
                            <li>Selecionar uma campanha específica para aquela página</li>
                            <li>Definir IDs de produtos customizados</li>
                            <li>Sobrescrever o comportamento global</li>
                        </ul>
                        
                        <h4>Configurações Globais</h4>
                        <p>Em <strong>Popup Amazon → Configurações</strong> você pode definir:</p>
                        <ul>
                            <li><strong>Posição padrão:</strong> Onde exibir por padrão</li>
                            <li><strong>Tipo de exibição padrão:</strong> Formato visual padrão</li>
                            <li><strong>Duração de campanhas:</strong> Períodos globais</li>
                        </ul>
                    </div>
                    
                    <h3>Dicas de Otimização</h3>
                    <ul>
                        <li><strong>Use o sistema de prioridade:</strong> Configure produtos específicos para páginas importantes</li>
                        <li><strong>Teste diferentes posições:</strong> Sticky bar pode ter melhor performance que popup</li>
                        <li><strong>Campanhas temporais:</strong> Crie campanhas para datas especiais (Black Friday, Natal)</li>
                        <li><strong>Segmentação por URL:</strong> Produtos diferentes para categorias diferentes</li>
                        <li><strong>Glossário SEO:</strong> Use termos do glossário para melhorar SEO e conversões</li>
                        <li><strong>Templates variados:</strong> Combine grid, list e carousel para diferentes contextos</li>
                        <li><strong>Monitoramento:</strong> Verifique regularmente as campanhas ativas</li>
                    </ul>
                    
                    <h3>Exemplos Práticos de Uso</h3>
                    <div class="amazon-examples">
                        <h4>Cenário 1: Blog de Tecnologia</h4>
                        <p><code>[amazon_products category="tecnologia" template="carousel" count="6"]</code></p>
                        <p><em>Exibe 6 produtos de tecnologia em carrossel Bootstrap responsivo</em></p>
                        
                        <h4>Cenário 2: Post sobre Fitness</h4>
                        <p><code>[amazon_glossary term="Whey Protein" show_products="yes" products_count="4"]</code></p>
                        <p><em>Explica o termo e mostra 4 produtos relacionados</em></p>
                        
                        <h4>Cenário 3: Promoção Black Friday</h4>
                        <p><code>[amazon_banner product_id="123" type="horizontal" title="BLACK FRIDAY" subtitle="Até 70% OFF" background_color="#000000" text_color="#ffffff"]</code></p>
                        
                        <h4>Cenário 4: Review de Produto</h4>
                        <p><code>[amazon_products specific_urls="https://amazon.com/produto-review" template="list"]</code></p>
                        <p><em>Exibe apenas o produto específico sendo revisado</em></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Página de gerenciamento de campanhas
    public function campaigns_page() {
        $campaigns = get_posts(array(
            'post_type' => 'amazon_campaign',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        ?>
        <div class="wrap">
            <h1>Campanhas Amazon Ativas</h1>
            
            <p><a href="<?php echo admin_url('post-new.php?post_type=amazon_campaign'); ?>" class="button button-primary">Criar Nova Campanha</a></p>
            
            <?php if (!empty($campaigns)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Campanha</th>
                            <th>Status</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>URLs Alvo</th>
                            <th>Produtos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): 
                            $start_date = get_post_meta($campaign->ID, '_campaign_start_date', true);
                            $end_date = get_post_meta($campaign->ID, '_campaign_end_date', true);
                            $target_urls = get_post_meta($campaign->ID, '_campaign_target_urls', true);
                            $products = get_post_meta($campaign->ID, '_campaign_products', true);
                            
                            $current_time = current_time('Y-m-d\\TH:i');
                            $is_active = true;
                            
                            if (!empty($start_date) && $current_time < $start_date) {
                                $is_active = false;
                                $status = 'Agendada';
                            } elseif (!empty($end_date) && $current_time > $end_date) {
                                $is_active = false;
                                $status = 'Expirada';
                            } else {
                                $status = 'Ativa';
                            }
                            
                            $status_class = $is_active ? 'active' : 'inactive';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($campaign->post_title); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo get_edit_post_link($campaign->ID); ?>">Editar</a></span>
                                    </div>
                                </td>
                                <td><span class="campaign-status campaign-status-<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                <td><?php echo !empty($start_date) ? date('d/m/Y H:i', strtotime($start_date)) : 'Imediato'; ?></td>
                                <td><?php echo !empty($end_date) ? date('d/m/Y H:i', strtotime($end_date)) : 'Sem fim'; ?></td>
                                <td>
                                    <?php 
                                    if (!empty($target_urls)) {
                                        $urls = explode("\n", $target_urls);
                                        echo esc_html(implode(', ', array_slice($urls, 0, 2)));
                                        if (count($urls) > 2) {
                                            echo ' (+' . (count($urls) - 2) . ' mais)';
                                        }
                                    } else {
                                        echo 'Todas as páginas';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($products)) {
                                        $product_ids = explode(',', $products);
                                        echo count($product_ids) . ' produtos';
                                    } else {
                                        echo 'Nenhum produto';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($campaign->ID); ?>" class="button button-small">Editar</a>
                                    <a href="#" class="button button-small test-campaign" data-campaign-id="<?php echo $campaign->ID; ?>">Testar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhuma campanha criada ainda.</p>
                <p><a href="<?php echo admin_url('post-new.php?post_type=amazon_campaign'); ?>" class="button button-primary">Criar Sua Primeira Campanha</a></p>
            <?php endif; ?>
            
            <div class="amazon-campaigns-help" style="margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 6px;">
                <h3>Como usar as campanhas</h3>
                <ul>
                    <li><strong>Campanhas Globais:</strong> Deixe o campo "URLs Alvo" em branco para exibir em todo o site</li>
                    <li><strong>Campanhas Específicas:</strong> Defina URLs específicas onde a campanha deve aparecer</li>
                    <li><strong>Agendamento:</strong> Configure datas de início e fim para controlar quando a campanha é exibida</li>
                    <li><strong>Produtos:</strong> Associe IDs de posts/produtos que contêm links Amazon</li>
                </ul>
            </div>
        </div>
        
        <style>
        .campaign-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .campaign-status-active {
            background: #46b450;
            color: white;
        }
        .campaign-status-inactive {
            background: #dc3232;
            color: white;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.test-campaign').on('click', function(e) {
                e.preventDefault();
                var campaignId = $(this).data('campaign-id');
                alert('Teste de campanha seria implementado aqui para a campanha ID: ' + campaignId);
            });
        });
        </script>
        <?php
    }
    
    // Adiciona widget no dashboard
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'amazon_affiliate_dashboard',
            'Status do Popup Amazon',
            array($this, 'dashboard_widget_content')
        );
    }
    
    // Conteúdo do widget do dashboard
    public function dashboard_widget_content() {
        $post_count = $this->count_amazon_products('post');
        $product_count = $this->count_amazon_products('product');
        $total = $post_count + $product_count;
        
        echo '<div class="amazon-dashboard-widget">';
        echo '<p>Conteúdo com links Amazon: <strong>' . $total . '</strong></p>';
        echo '<ul>';
        echo '<li>Posts: ' . $post_count . '</li>';
        echo '<li>Produtos: ' . $product_count . '</li>';
        echo '</ul>';
        
        if ($total === 0) {
            echo '<div class="amazon-widget-alert">';
            echo '<p>Nenhum link Amazon detectado. <a href="' . admin_url('options-general.php?page=amazon_affiliate_popup') . '">Verifique seu conteúdo</a>.</p>';
            echo '</div>';
        }
        
        echo '<p><a href="' . admin_url('options-general.php?page=amazon_affiliate_popup') . '" class="button">Configurar Plugin</a></p>';
        echo '</div>';
    }
    
    // Adiciona coluna personalizada na lista de posts/produtos
    public function add_affiliate_column($columns) {
        $columns['amazon_affiliate'] = 'Link Amazon';
        return $columns;
    }
    
    // Conteúdo da coluna personalizada
    public function affiliate_column_content($column, $post_id) {
        if ($column === 'amazon_affiliate') {
            $amazon_url = $this->get_amazon_url($post_id);
            if (!empty($amazon_url)) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="Possui link Amazon"></span>';
                echo '<a href="' . esc_url($amazon_url) . '" target="_blank" style="margin-left: 5px;">Ver link</a>';
            } else {
                echo '<span class="dashicons dashicons-no" style="color: #dc3232;"></span>';
            }
        }
    }
    
    // Verifica links Amazon em produtos WooCommerce e posts
    public function check_amazon_links($post_id, $post, $update) {
        // Verifica se a detecção automática está ativada
        if (!isset($this->options['auto_detect']) || !$this->options['auto_detect']) {
            return;
        }
        
        // Evita execução em autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verifica permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verifica se é um rascunho
        if ($post->post_status === 'auto-draft') {
            return;
        }
        
        // Extrai links Amazon do conteúdo
        $amazon_url = $this->extract_amazon_url($post_id, $post->post_content);
        
        if ($amazon_url) {
            update_post_meta($post_id, '_amazon_affiliate_url', $amazon_url);
            
            // Para produtos WooCommerce, também verifica o campo product_url
            if ($post->post_type === 'product') {
                $product_url = get_post_meta($post_id, '_product_url', true);
                if (!empty($product_url) && $this->is_amazon_url($product_url)) {
                    update_post_meta($post_id, '_amazon_affiliate_url', $product_url);
                }
            }
        }
    }
    
    // Extrai URL Amazon do conteúdo
    private function extract_amazon_url($post_id, $content) {
        $pattern = '/https?:\/\/(www\.)?amazon\.([a-z\.]+)\/[^"\'\s]*/i';
        preg_match_all($pattern, $content, $matches);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $url) {
                if ($this->is_amazon_url($url)) {
                    return $url;
                }
            }
        }
        
        return false;
    }
    
    // Verifica se uma URL é da Amazon
    private function is_amazon_url($url) {
        return strpos($url, 'amazon.') !== false || strpos($url, 'amzn.') !== false;
    }
    
    // Obtém URL Amazon de um post
    private function get_amazon_url($post_id) {
        $amazon_url = get_post_meta($post_id, '_amazon_affiliate_url', true);
        
        // Se não encontrou, verifica no conteúdo
        if (empty($amazon_url)) {
            $post = get_post($post_id);
            $amazon_url = $this->extract_amazon_url($post_id, $post->post_content);
            
            if ($amazon_url) {
                update_post_meta($post_id, '_amazon_affiliate_url', $amazon_url);
            }
        }
        
        return $amazon_url;
    }
    
    // Adiciona o tag de associado a uma URL Amazon
    private function add_associate_tag($url) {
        if (empty($this->associate_tag) || empty($url)) {
            return $url;
        }
        
        // Verifica se a URL já tem um tag de associado
        if (strpos($url, 'tag=') !== false) {
            // Substitui o tag existente
            $url = preg_replace('/tag=[^&]*/', 'tag=' . $this->associate_tag, $url);
        } else {
            // Adiciona o tag
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . 'tag=' . $this->associate_tag;
        }
        
        return $url;
    }
    
    // Conta produtos/posts com links Amazon
    private function count_amazon_products($post_type = 'product') {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_amazon_affiliate_url'
                AND p.post_type = %s
                AND p.post_status = 'publish'",
                $post_type
            )
        );
        
        return $count ? $count : 0;
    }
    
    // Adiciona scripts e estilos no frontend
    public function enqueue_scripts() {
        // Verifica se é um post individual (artigo do blog)
        if (!is_singular('post') && !is_singular('page') && !is_singular('product')) {
            return;
        }
        
        global $post;
        
        // Obtém as categorias do post atual
        $categories = get_the_category($post->ID);
        $category_ids = array();
        
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $category_ids[] = $category->term_id;
            }
        }
        
        // Verifica configurações de exibição
        $display = isset($this->options['popup_display']) ? $this->options['popup_display'] : 'all';
        if ($display === 'none') {
            return;
        }
        
        // Carrega Bootstrap apenas se necessário (para carrossel)
        $post_content = get_post_field('post_content', $post->ID);
        if (strpos($post_content, 'template="carousel"') !== false) {
            wp_enqueue_style('bootstrap-carousel', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
            wp_enqueue_script('bootstrap-carousel', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array(), '5.3.0', true);
        }
        
        // SEMPRE carrega os scripts, independentemente de ter produtos Amazon
        wp_enqueue_style('amazon-affiliate-popup', plugin_dir_url(__FILE__) . 'popup-style.css', array(), '2.8');
        wp_enqueue_script('amazon-affiliate-popup', plugin_dir_url(__FILE__) . 'popup-script.js', array('jquery'), '2.8', true);
        
        $delay = isset($this->options['popup_delay']) ? intval($this->options['popup_delay']) : 15;
        $display_position = isset($this->options['display_position']) ? $this->options['display_position'] : 'auto';
        $display_type = isset($this->options['display_type']) ? $this->options['display_type'] : 'popup';
        
        wp_localize_script('amazon-affiliate-popup', 'amazon_popup_vars', array(
            'delay' => $delay * 1000,
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => $post->ID,
            'categories' => $category_ids,
            'nonce' => wp_create_nonce('get_amazon_products'),
            'display_position' => $display_position,
            'display_type' => $display_type
        ));
        
        // Adiciona ganchos para diferentes posições
        if ($display_position !== 'auto') {
            $this->add_display_hooks($display_position, $display_type);
        }
    }
    
    // Adiciona ganchos para diferentes posições de exibição
    private function add_display_hooks($position, $type) {
        switch ($position) {
            case 'header':
                add_action('wp_head', array($this, 'display_amazon_content_in_head'));
                add_action('wp_body_open', array($this, 'display_amazon_content'));
                add_action('get_header', array($this, 'display_amazon_content'));
                break;
            case 'footer':
                add_action('wp_footer', array($this, 'display_amazon_content'));
                add_action('get_footer', array($this, 'display_amazon_content'));
                break;
            case 'before_content':
                add_filter('the_content', array($this, 'add_before_content'));
                break;
            case 'after_content':
                add_filter('the_content', array($this, 'add_after_content'));
                break;
            case 'sidebar':
                add_action('dynamic_sidebar_before', array($this, 'display_amazon_content'));
                add_action('wp_footer', array($this, 'inject_sidebar_content'));
                break;
        }
    }
    
    // Função especial para cabeçalho
    public function display_amazon_content_in_head() {
        echo '<script>console.log("Amazon plugin: Tentando exibir no cabeçalho");</script>';
    }
    
    // Função para injetar conteúdo na sidebar via JavaScript
    public function inject_sidebar_content() {
        global $post;
        
        if (!$post) return;
        
        // Busca produto para exibir
        $args = array(
            'post_type' => array('product', 'post'),
            'posts_per_page' => 1,
            'meta_key' => '_amazon_affiliate_url',
            'meta_compare' => 'EXISTS',
            'post__not_in' => array($post->ID),
            'orderby' => 'rand'
        );
        
        $products_query = new WP_Query($args);
        
        if ($products_query->have_posts()) {
            $products_query->the_post();
            $amazon_url = $this->get_amazon_url(get_the_ID());
            $amazon_url = $this->add_associate_tag($amazon_url);
            $image = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            $title = get_the_title();
            
            echo '<script>';
            echo 'jQuery(document).ready(function($) {';
            echo '  var sidebarContent = `';
            echo '    <div class="amazon-sidebar-widget" style="background: white; padding: 15px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #FF9900;">';
            echo '      <h4 style="color: #FF9900; margin: 0 0 10px 0; font-size: 16px;">Produto Amazon</h4>';
            if ($image) {
                echo '      <img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '" style="width: 100%; height: 120px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">';
            }
            echo '      <h5 style="margin: 0 0 8px 0; font-size: 14px; line-height: 1.4;">' . esc_html($title) . '</h5>';
            echo '      <a href="' . esc_url($amazon_url) . '" target="_blank" style="display: inline-block; background: #FF9900; color: white; padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold;">Ver na Amazon</a>';
            echo '    </div>';
            echo '  `;';
            echo '  ';
            echo '  // Tenta encontrar sidebar e inserir conteúdo';
            echo '  var sidebarSelectors = [".sidebar", "#sidebar", ".widget-area", "#secondary", "aside", ".aside"];';
            echo '  var inserted = false;';
            echo '  ';
            echo '  $.each(sidebarSelectors, function(index, selector) {';
            echo '    var $sidebar = $(selector).first();';
            echo '    if ($sidebar.length > 0 && !inserted) {';
            echo '      $sidebar.prepend(sidebarContent);';
            echo '      inserted = true;';
            echo '      console.log("Amazon plugin: Conteúdo inserido na sidebar via: " + selector);';
            echo '      return false;';
            echo '    }';
            echo '  });';
            echo '  ';
            echo '  if (!inserted) {';
            echo '    console.log("Amazon plugin: Nenhuma sidebar encontrada. Seletores tentados:", sidebarSelectors);';
            echo '  }';
            echo '});';
            echo '</script>';
            
            wp_reset_postdata();
        }
    }
    
    // Exibe conteúdo Amazon
    public function display_amazon_content() {
        global $post;
        
        if (!$post) return;
        
        $display_type = isset($this->options['display_type']) ? $this->options['display_type'] : 'popup';
        $display_position = isset($this->options['display_position']) ? $this->options['display_position'] : 'auto';
        
        // Para exibição automática em posições fixas, não exibe nada
        // O JavaScript cuidará da exibição
        if (in_array($display_type, ['popup', 'sticky_bar']) && $display_position === 'auto') {
            return;
        }
        
        // Adiciona debug para cabeçalho
        if ($display_position === 'header') {
            echo '<!-- Amazon Plugin: Exibindo no cabeçalho -->';
        }
        
        // Busca produtos relacionados apenas para tipos que são inseridos no conteúdo
        $args = array(
            'post_type' => array('product', 'post'),
            'posts_per_page' => 1,
            'meta_key' => '_amazon_affiliate_url',
            'meta_compare' => 'EXISTS',
            'post__not_in' => array($post->ID),
            'orderby' => 'rand'
        );
        
        $products_query = new WP_Query($args);
        
        if ($products_query->have_posts()) {
            $products_query->the_post();
            $amazon_url = $this->get_amazon_url(get_the_ID());
            $amazon_url = $this->add_associate_tag($amazon_url);
            $image = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            $title = get_the_title();
            
            // Container com identificação da posição
            echo '<div class="amazon-display amazon-display-' . esc_attr($display_type) . ' amazon-position-' . esc_attr($display_position) . '">';
            
            if ($display_type === 'banner_horizontal') {
                echo '<div class="amazon-banner amazon-banner-horizontal" style="background: linear-gradient(135deg, #FF9900 0%, #FF6600 100%); color: white;">';
                echo '<div class="amazon-banner-content">';
                echo '<div class="amazon-banner-text">';
                echo '<h3>' . esc_html($title) . '</h3>';
                echo '<p>Confira esta oferta especial na Amazon!</p>';
                echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-banner-cta">Ver Oferta</a>';
                echo '</div>';
                if ($image) {
                    echo '<div class="amazon-banner-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '"></div>';
                }
                echo '</div></div>';
            } elseif ($display_type === 'banner_vertical') {
                echo '<div class="amazon-banner amazon-banner-vertical" style="background: linear-gradient(135deg, #FF9900 0%, #FF6600 100%); color: white;">';
                if ($image) {
                    echo '<div class="amazon-banner-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '"></div>';
                }
                echo '<div class="amazon-banner-text">';
                echo '<h3>' . esc_html($title) . '</h3>';
                echo '<p>Oferta especial</p>';
                echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-banner-cta">Ver na Amazon</a>';
                echo '</div></div>';
            } elseif ($display_type === 'card') {
                echo '<div class="amazon-display-card">';
                echo '<button class="amazon-card-close">&times;</button>';
                echo '<div class="amazon-card-content">';
                if ($image) {
                    echo '<div class="amazon-card-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '"></div>';
                }
                echo '<div class="amazon-card-text">';
                echo '<h4>' . esc_html($title) . '</h4>';
                echo '<p>Encontramos este produto que pode interessar você. Confira na Amazon!</p>';
                echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-card-cta">Ver na Amazon</a>';
                echo '</div></div></div>';
            } elseif ($display_type === 'sticky_bar') {
                // Força exibição da sticky bar mesmo em posições diferentes
                echo '<div class="amazon-banner amazon-banner-horizontal" style="background: linear-gradient(135deg, #FF9900 0%, #FF6600 100%); color: white; position: relative;">';
                echo '<div class="amazon-banner-content">';
                echo '<div class="amazon-banner-text">';
                echo '<h3>🎯 ' . esc_html($title) . '</h3>';
                echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-banner-cta">Ver na Amazon</a>';
                echo '</div>';
                if ($image) {
                    echo '<div class="amazon-banner-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '"></div>';
                }
                echo '</div></div>';
            }
            
            echo '</div>';
            
            // Debug no cabeçalho
            if ($display_position === 'header') {
                echo '<!-- Amazon Plugin: Conteúdo exibido com sucesso no cabeçalho -->';
            }
            
            wp_reset_postdata();
        } else {
            // Debug quando não encontra produtos
            if ($display_position === 'header') {
                echo '<!-- Amazon Plugin: Nenhum produto Amazon encontrado para exibir no cabeçalho -->';
            }
        }
    }
    
    // Adiciona conteúdo antes do texto
    public function add_before_content($content) {
        if (is_singular()) {
            ob_start();
            $this->display_amazon_content();
            $amazon_content = ob_get_clean();
            return $amazon_content . $content;
        }
        return $content;
    }
    
    // Adiciona conteúdo depois do texto
    public function add_after_content($content) {
        if (is_singular()) {
            ob_start();
            $this->display_amazon_content();
            $amazon_content = ob_get_clean();
            return $content . $amazon_content;
        }
        return $content;
    }
    
    // Adiciona scripts e estilos no admin
    public function admin_enqueue_scripts($hook) {
        wp_enqueue_style('amazon-affiliate-admin', plugin_dir_url(__FILE__) . 'admin-style.css', array(), '2.5');
        
        if ($hook === 'settings_page_amazon_affiliate_popup' || $hook === 'toplevel_page_amazon_affiliate_popup') {
            wp_enqueue_script('amazon-affiliate-admin', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '2.5', true);
            
            wp_localize_script('amazon-affiliate-admin', 'amazon_affiliate_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scan_all_amazon_products')
            ));
        }
    }
    
    // AJAX para buscar produtos Amazon
    public function get_amazon_products() {
        check_ajax_referer('get_amazon_products', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        
        // Primeiro verifica se há uma campanha específica para esta página
        $page_campaign = get_post_meta($post_id, '_amazon_page_campaign', true);
        $custom_products = get_post_meta($post_id, '_amazon_custom_products', true);
        
        // Se há produtos customizados, use-os
        if (!empty($custom_products)) {
            $product_ids = array_map('trim', explode(',', $custom_products));
            $this->send_specific_products($product_ids);
            return;
        }
        
        // Se há uma campanha específica, use-a
        if (!empty($page_campaign)) {
            $this->send_campaign_products($page_campaign);
            return;
        }
        
        // Verifica campanhas ativas para a URL atual
        $active_campaign = $this->get_active_campaign_for_url();
        if ($active_campaign) {
            $this->send_campaign_products($active_campaign->ID);
            return;
        }
        
        // Comportamento padrão - busca produtos por categoria
        $this->send_default_products($post_id, $categories);
    }
    
    // Busca campanha ativa para a URL atual
    private function get_active_campaign_for_url() {
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Busca campanhas ativas
        $campaigns = get_posts(array(
            'post_type' => 'amazon_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_campaign_start_date',
                        'value' => current_time('Y-m-d\\TH:i'),
                        'compare' => '<=',
                        'type' => 'DATETIME'
                    ),
                    array(
                        'key' => '_campaign_start_date',
                        'compare' => 'NOT EXISTS'
                    )
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_campaign_end_date',
                        'value' => current_time('Y-m-d\\TH:i'),
                        'compare' => '>=',
                        'type' => 'DATETIME'
                    ),
                    array(
                        'key' => '_campaign_end_date',
                        'compare' => 'NOT EXISTS'
                    )
                )
            )
        ));
        
        foreach ($campaigns as $campaign) {
            $target_urls = get_post_meta($campaign->ID, '_campaign_target_urls', true);
            
            // Se não há URLs alvo, a campanha é global
            if (empty($target_urls)) {
                return $campaign;
            }
            
            // Verifica se a URL atual corresponde
            $urls = explode("\n", $target_urls);
            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url) && strpos($current_url, $url) !== false) {
                    return $campaign;
                }
            }
        }
        
        return null;
    }
    
    // Envia produtos de uma campanha específica
    private function send_campaign_products($campaign_id) {
        $products = get_post_meta($campaign_id, '_campaign_products', true);
        
        if (!empty($products)) {
            $product_ids = array_map('trim', explode(',', $products));
            $this->send_specific_products($product_ids);
        } else {
            wp_send_json_error('Campanha sem produtos configurados.');
        }
    }
    
    // Envia produtos específicos
    private function send_specific_products($product_ids) {
        if (empty($product_ids)) {
            wp_send_json_error('Nenhum produto especificado.');
            return;
        }
        
        $products_query = new WP_Query(array(
            'post__in' => $product_ids,
            'post_type' => array('product', 'post'),
            'posts_per_page' => 1,
            'orderby' => 'rand'
        ));
        
        if ($products_query->have_posts()) {
            $products_query->the_post();
            $amazon_url = $this->get_amazon_url(get_the_ID());
            
            if (!empty($amazon_url)) {
                $product_data = array(
                    'url' => $this->add_associate_tag($amazon_url),
                    'title' => get_the_title(),
                    'image' => get_the_post_thumbnail_url(get_the_ID(), 'medium')
                );
                
                ob_start();
                $this->render_popup_product($product_data);
                $content = ob_get_clean();
                
                wp_reset_postdata();
                wp_send_json_success($content);
            }
        }
        
        wp_send_json_error('Produto não encontrado ou sem link Amazon.');
    }
    
    // Envia produtos padrão (comportamento original)
    private function send_default_products($post_id, $categories) {
        // Busca produtos por categoria (não por links no post)
        $args = array(
            'post_type' => array('product', 'post'),
            'posts_per_page' => 5,
            'meta_key' => '_amazon_affiliate_url',
            'meta_compare' => 'EXISTS',
            'post__not_in' => array($post_id)
        );
        
        // Se temos categorias, filtra por elas
        if (!empty($categories)) {
            $args['category__in'] = array_map('intval', $categories);
        }
        
        $products_query = new WP_Query($args);
        
        if (!$products_query->have_posts()) {
            // Se não encontrou produtos nas categorias, busca quaisquer produtos
            unset($args['category__in']);
            $products_query = new WP_Query($args);
        }
        
        if ($products_query->have_posts()) {
            $products = array();
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $amazon_url = $this->get_amazon_url(get_the_ID());
                
                if (!empty($amazon_url)) {
                    $products[] = array(
                        'url' => $this->add_associate_tag($amazon_url),
                        'title' => get_the_title(),
                        'image' => get_the_post_thumbnail_url(get_the_ID(), 'medium')
                    );
                }
            }
            
            // Seleciona um produto aleatório
            if (!empty($products)) {
                $random_product = $products[array_rand($products)];
                
                ob_start();
                $this->render_popup_product($random_product);
                $content = ob_get_clean();
                
                wp_send_json_success($content);
            }
        }
        
        wp_send_json_error('Nenhum produto relacionado encontrado.');
    }
    
    // Renderiza produto para popup
    private function render_popup_product($product) {
        ?>
        <div class="amazon-popup-product">
            <h3 class="amazon-popup-headline">Oferta Especial para Leitores!</h3>

            <div class="amazon-product-image">
                <img src="<?php echo esc_url($product['image'] ?: plugin_dir_url(__FILE__) . 'default-product.jpg'); ?>" alt="<?php echo esc_attr($product['title']); ?>">
            </div>
            <h4><?php echo esc_html($product['title']); ?></h4>

            <p>Encontramos esta oferta na Amazon que pode ser do seu interesse. Confira antes que acabe!</p>

            <a href="<?php echo esc_url($product['url']); ?>" target="_blank" class="amazon-popup-cta">
                Conferir Oferta Agora
            </a>
            <p class="amazon-disclaimer">* Este é um link de afiliado. Podemos receber uma comissão por compras qualificadas.</p>
        </div>
        <?php
    }
    
    // Shortcode para exibir produtos manualmente - EXPANDIDO
    public function amazon_products_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => 3,
            'post_type' => 'product',
            'category' => '',
            'tag' => '',
            'specific_urls' => '',
            'template' => 'grid',
            'show_price' => 'yes',
            'show_description' => 'yes',
            'target_blank' => 'yes'
        ), $atts);
        
        // Verifica se há URLs específicas
        if (!empty($atts['specific_urls'])) {
            return $this->render_specific_amazon_products($atts);
        }
        
        // Argumentos da consulta
        $args = array(
            'post_type' => $atts['post_type'],
            'posts_per_page' => intval($atts['count']),
            'meta_key' => '_amazon_affiliate_url',
            'meta_compare' => 'EXISTS'
        );
        
        // Filtra por categoria se especificada
        if (!empty($atts['category'])) {
            if (is_numeric($atts['category'])) {
                $args['cat'] = $atts['category'];
            } else {
                $args['category_name'] = $atts['category'];
            }
        }
        
        // Filtra por tag se especificada
        if (!empty($atts['tag'])) {
            $args['tag'] = $atts['tag'];
        }
        
        $products = new WP_Query($args);
        
        if (!$products->have_posts()) {
            return '<p>Nenhum produto Amazon encontrado.</p>';
        }
        
        return $this->render_amazon_products($products, $atts);
    }
    
    // Shortcode para banners Amazon
    public function amazon_banner_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'horizontal',
            'product_id' => '',
            'title' => 'Oferta Especial',
            'subtitle' => 'Confira esta oferta imperdível!',
            'button_text' => 'Ver na Amazon',
            'background_color' => '#ff9900',
            'text_color' => '#ffffff'
        ), $atts);
        
        if (empty($atts['product_id'])) {
            return '<p>ID do produto não especificado.</p>';
        }
        
        $amazon_url = $this->get_amazon_url($atts['product_id']);
        if (empty($amazon_url)) {
            return '<p>Produto não possui link Amazon.</p>';
        }
        
        return $this->render_amazon_banner($atts, $amazon_url);
    }
    
    // Shortcode para campanhas específicas
    public function amazon_campaign_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'start_date' => '',
            'end_date' => '',
            'target_urls' => '',
            'display_type' => 'popup'
        ), $atts);
        
        // Verifica se a campanha está ativa
        if (!$this->is_campaign_active($atts)) {
            return '';
        }
        
        // Verifica se a URL atual está nas URLs alvo
        if (!$this->is_target_url($atts['target_urls'])) {
            return '';
        }
        
        return $this->render_campaign($atts);
    }
    
    // Shortcode para glossário
    public function amazon_glossary_shortcode($atts) {
        $atts = shortcode_atts(array(
            'term' => '',
            'show_products' => 'yes',
            'products_count' => 3
        ), $atts);
        
        if (empty($atts['term'])) {
            return '<p>Termo não especificado.</p>';
        }
        
        return $this->render_glossary_term($atts);
    }
    
    // Verifica todos os produtos existentes
    public function scan_all_amazon_products($offset = 0) {
        error_log('scan_all_amazon_products iniciado com offset: ' . $offset);
        
        $batch_size = 10;
        
        // Busca posts e produtos
        $args = array(
            'post_type' => array('post', 'product'),
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'publish'
        );
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return array('continue' => false, 'processed' => 0);
        }
        
        $processed = 0;
        foreach ($posts as $post) {
            // Extrai links Amazon do conteúdo
            $amazon_url = $this->extract_amazon_url($post->ID, $post->post_content);
            
            if ($amazon_url) {
                update_post_meta($post->ID, '_amazon_affiliate_url', $amazon_url);
                $processed++;
            }
            
            // Para produtos WooCommerce, também verifica o campo product_url
            if ($post->post_type === 'product') {
                $product_url = get_post_meta($post->ID, '_product_url', true);
                if (!empty($product_url) && $this->is_amazon_url($product_url)) {
                    update_post_meta($post->ID, '_amazon_affiliate_url', $product_url);
                    $processed++;
                }
            }
        }
        
        return array(
            'continue' => count($posts) === $batch_size,
            'offset' => $offset + $batch_size,
            'processed' => $processed
        );
    }

    // Função para contar posts em uma categoria
private function count_posts_in_category($category_id) {
    global $wpdb;
    
    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->term_relationships tr
            INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN $wpdb->posts p ON tr.object_id = p.ID
            INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
            WHERE tt.term_id = %d
            AND tt.taxonomy = 'category'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_amazon_affiliate_url'
            AND pm.meta_value != ''",
            $category_id
        )
    );
    
    return $count ? $count : 0;
}

// Funções auxiliares para os novos shortcodes
private function render_amazon_products($products, $atts) {
    ob_start();
    
    $template_class = 'amazon-products-' . $atts['template'];
    echo '<div class="amazon-products-list ' . esc_attr($template_class) . '">';
    echo '<h3>Produtos Recomendados</h3>';
    
    // Container específico para cada template
    if ($atts['template'] === 'carousel') {
        $carousel_id = 'amazon-carousel-' . wp_rand(1000, 9999);
        $carousel_desktop_id = $carousel_id . '-desktop';
        $carousel_mobile_id = $carousel_id . '-mobile';
        
        echo '<div class="amazon-bootstrap-carousel-wrapper">';
        
        // Carrossel Desktop
        echo '<div id="' . $carousel_desktop_id . '" class="carousel slide amazon-bootstrap-carousel d-none d-md-block" data-bs-ride="false" data-bs-interval="false">';
        echo '<div class="carousel-inner amazon-desktop-slides">';
        
        $product_count = 0;
        $products_per_slide = 3; // 3 produtos por slide
        $slide_count = 0;
        $all_products = array();
        
        // Coleta todos os produtos primeiro
        while ($products->have_posts()) {
            $products->the_post();
            $all_products[] = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'image' => get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: plugin_dir_url(__FILE__) . 'default-product.svg',
                'excerpt' => get_the_excerpt() ?: wp_trim_words(get_the_content(), 15),
                'amazon_url' => $this->add_associate_tag($this->get_amazon_url(get_the_ID())),
                'price' => get_post_meta(get_the_ID(), '_price', true),
                'regular_price' => get_post_meta(get_the_ID(), '_regular_price', true),
                'sale_price' => get_post_meta(get_the_ID(), '_sale_price', true)
            );
        }
        
        // Cria slides com produtos responsivos
        $total_products = count($all_products);
        $products_per_slide_desktop = 3;
        $slide_count = 0;
        
        for ($i = 0; $i < $total_products; $i += $products_per_slide_desktop) {
            $active_class = ($slide_count === 0) ? ' active' : '';
            echo '<div class="carousel-item' . $active_class . '">';
            echo '<div class="container-fluid px-4">';
            echo '<div class="row g-4 justify-content-center amazon-slide-row">';
            
            // Adiciona produtos para este slide
            for ($j = 0; $j < $products_per_slide_desktop && ($i + $j) < $total_products; $j++) {
                $product = $all_products[$i + $j];
                $product_index = $i + $j;
                
                // Classes responsivas: desktop 3 colunas, tablet 2, mobile 1
                $col_classes = 'col-lg-4 col-md-6 col-12';
                if ($j >= 2) {
                    $col_classes .= ' d-lg-block d-md-none'; // Terceiro produto: apenas desktop
                } elseif ($j >= 1) {
                    $col_classes .= ' d-md-block d-sm-none'; // Segundo produto: tablet e desktop
                }
                
                echo '<div class="' . $col_classes . ' amazon-product-col" data-product-index="' . $product_index . '">';
                echo '<div class="card amazon-bootstrap-card h-100 shadow-sm border-0">';
                echo '<div class="amazon-card-img-container position-relative overflow-hidden">';
                echo '<img src="' . esc_url($product['image']) . '" class="card-img-top amazon-card-img" alt="' . esc_attr($product['title']) . '" loading="lazy">';
                echo '</div>';
                echo '<div class="card-body d-flex flex-column p-3">';
                echo '<h5 class="card-title amazon-card-title mb-2 fw-bold">' . esc_html(wp_trim_words($product['title'], 6)) . '</h5>';
                
                if ($atts['show_description'] === 'yes') {
                    echo '<p class="card-text amazon-card-desc text-muted small flex-grow-1 mb-3">' . esc_html(wp_trim_words($product['excerpt'], 15)) . '</p>';
                }
                
                // Preço
                if ($atts['show_price'] === 'yes') {
                    echo '<div class="amazon-bootstrap-price mb-3">';
                    if (!empty($product['sale_price']) && !empty($product['regular_price']) && $product['sale_price'] < $product['regular_price']) {
                        echo '<div class="d-flex align-items-center gap-2 mb-1">';
                        echo '<span class="text-danger fw-bold fs-5">R$ ' . number_format(floatval($product['sale_price']), 2, ',', '.') . '</span>';
                        echo '<span class="text-muted text-decoration-line-through small">R$ ' . number_format(floatval($product['regular_price']), 2, ',', '.') . '</span>';
                        echo '</div>';
                    } elseif (!empty($product['price'])) {
                        echo '<span class="text-success fw-bold fs-5">R$ ' . number_format(floatval($product['price']), 2, ',', '.') . '</span>';
                    } elseif (!empty($product['regular_price'])) {
                        echo '<span class="text-success fw-bold fs-5">R$ ' . number_format(floatval($product['regular_price']), 2, ',', '.') . '</span>';
                    } else {
                        echo '<span class="text-primary small">Ver preço na Amazon</span>';
                    }
                    echo '</div>';
                }
                
                // Avaliação fake para visual
                echo '<div class="amazon-bootstrap-rating mb-3 d-flex align-items-center gap-1">';
                echo '<span class="text-warning fs-6">★★★★☆</span>';
                echo '<small class="text-muted ms-1">(4.2/5)</small>';
                echo '</div>';
                
                $target = ($atts['target_blank'] === 'yes') ? '_blank' : '_self';
                echo '<a href="' . esc_url($product['amazon_url']) . '" target="' . $target . '" class="btn btn-warning amazon-btn-custom mt-auto fw-bold d-flex align-items-center justify-content-center gap-2">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/></svg>Ver na Amazon';
                echo '</a>';
                echo '</div></div></div>';
            }
            
            echo '</div></div></div>'; // Fecha row, container-fluid e carousel-item
            $slide_count++;
        }
        
        echo '</div>'; // Fecha carousel-inner desktop
        
        // Controles e indicadores desktop
        if ($slide_count > 1) {
            echo '<button class="carousel-control-prev amazon-carousel-control" type="button" data-bs-target="#' . $carousel_desktop_id . '" data-bs-slide="prev">';
            echo '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
            echo '<span class="visually-hidden">Anterior</span>';
            echo '</button>';
            echo '<button class="carousel-control-next amazon-carousel-control" type="button" data-bs-target="#' . $carousel_desktop_id . '" data-bs-slide="next">';
            echo '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
            echo '<span class="visually-hidden">Próximo</span>';
            echo '</button>';
            
            echo '<div class="carousel-indicators">';
            for ($ind = 0; $ind < $slide_count; $ind++) {
                $active = ($ind === 0) ? ' active' : '';
                $aria_current = ($ind === 0) ? ' aria-current="true"' : '';
                echo '<button type="button" data-bs-target="#' . $carousel_desktop_id . '" data-bs-slide-to="' . $ind . '" class="amazon-carousel-indicator' . $active . '"' . $aria_current . '></button>';
            }
            echo '</div>';
        }
        
        echo '</div>'; // Fecha carousel desktop
        
        // Carrossel Mobile
        echo '<div id="' . $carousel_mobile_id . '" class="carousel slide amazon-bootstrap-carousel d-md-none" data-bs-ride="false" data-bs-interval="false">';
        echo '<div class="carousel-inner amazon-mobile-slides">';
        for ($i = 0; $i < $total_products; $i++) {
            $active_class = ($i === 0) ? ' active' : '';
            $product = $all_products[$i];
            
            echo '<div class="carousel-item' . $active_class . '">';
            echo '<div class="container-fluid px-4">';
            echo '<div class="row g-4 justify-content-center">';
            echo '<div class="col-12">';
            
            // Card do produto para mobile
            echo '<div class="card amazon-bootstrap-card h-100 shadow-sm border-0 mx-auto" style="max-width: 320px;">';
            echo '<div class="amazon-card-img-container position-relative overflow-hidden">';
            echo '<img src="' . esc_url($product['image']) . '" class="card-img-top amazon-card-img" alt="' . esc_attr($product['title']) . '" loading="lazy">';
            echo '</div>';
            echo '<div class="card-body d-flex flex-column p-3">';
            echo '<h5 class="card-title amazon-card-title mb-2 fw-bold text-center">' . esc_html(wp_trim_words($product['title'], 8)) . '</h5>';
            
            if ($atts['show_description'] === 'yes') {
                echo '<p class="card-text amazon-card-desc text-muted small flex-grow-1 mb-3 text-center">' . esc_html(wp_trim_words($product['excerpt'], 20)) . '</p>';
            }
            
            // Preço para mobile
            if ($atts['show_price'] === 'yes') {
                echo '<div class="amazon-bootstrap-price mb-3 text-center">';
                if (!empty($product['sale_price']) && !empty($product['regular_price']) && $product['sale_price'] < $product['regular_price']) {
                    echo '<div class="d-flex align-items-center justify-content-center gap-2 mb-1">';
                    echo '<span class="text-danger fw-bold fs-5">R$ ' . number_format(floatval($product['sale_price']), 2, ',', '.') . '</span>';
                    echo '<span class="text-muted text-decoration-line-through small">R$ ' . number_format(floatval($product['regular_price']), 2, ',', '.') . '</span>';
                    echo '</div>';
                } elseif (!empty($product['price'])) {
                    echo '<span class="text-success fw-bold fs-5">R$ ' . number_format(floatval($product['price']), 2, ',', '.') . '</span>';
                } elseif (!empty($product['regular_price'])) {
                    echo '<span class="text-success fw-bold fs-5">R$ ' . number_format(floatval($product['regular_price']), 2, ',', '.') . '</span>';
                } else {
                    echo '<span class="text-primary small">Ver preço na Amazon</span>';
                }
                echo '</div>';
            }
            
            // Avaliação para mobile
            echo '<div class="amazon-bootstrap-rating mb-3 d-flex align-items-center justify-content-center gap-1">';
            echo '<span class="text-warning fs-6">★★★★☆</span>';
            echo '<small class="text-muted ms-1">(4.2/5)</small>';
            echo '</div>';
            
            $target = ($atts['target_blank'] === 'yes') ? '_blank' : '_self';
            echo '<a href="' . esc_url($product['amazon_url']) . '" target="' . $target . '" class="btn btn-warning amazon-btn-custom mt-auto fw-bold d-flex align-items-center justify-content-center gap-2">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/></svg>Ver na Amazon';
            echo '</a>';
            echo '</div></div></div>';
            
            echo '</div></div></div>'; // Fecha col, row, container e carousel-item
        }
        echo '</div>'; // Fecha carousel-inner mobile
        
        // Controles e indicadores mobile
        if ($total_products > 1) {
            echo '<button class="carousel-control-prev amazon-carousel-control" type="button" data-bs-target="#' . $carousel_mobile_id . '" data-bs-slide="prev">';
            echo '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
            echo '<span class="visually-hidden">Anterior</span>';
            echo '</button>';
            echo '<button class="carousel-control-next amazon-carousel-control" type="button" data-bs-target="#' . $carousel_mobile_id . '" data-bs-slide="next">';
            echo '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
            echo '<span class="visually-hidden">Próximo</span>';
            echo '</button>';
            
            echo '<div class="carousel-indicators">';
            for ($ind = 0; $ind < $total_products; $ind++) {
                $active = ($ind === 0) ? ' active' : '';
                $aria_current = ($ind === 0) ? ' aria-current="true"' : '';
                echo '<button type="button" data-bs-target="#' . $carousel_mobile_id . '" data-bs-slide-to="' . $ind . '" class="amazon-carousel-indicator' . $active . '"' . $aria_current . '></button>';
            }
            echo '</div>';
        }
        
        echo '</div>'; // Fecha carousel mobile
        echo '</div>'; // Fecha carousel wrapper
        
        echo '</div>'; // Fecha carousel
    } else {
        echo '<div class="amazon-products-grid">';
        
        $product_count = 0;
        while ($products->have_posts()) {
            $products->the_post();
            $amazon_url = $this->get_amazon_url(get_the_ID());
            $amazon_url = $this->add_associate_tag($amazon_url);
            $image = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            $price = get_post_meta(get_the_ID(), '_price', true);
            $regular_price = get_post_meta(get_the_ID(), '_regular_price', true);
            $sale_price = get_post_meta(get_the_ID(), '_sale_price', true);
            
            if (empty($image)) {
                $image = plugin_dir_url(__FILE__) . 'default-product.svg';
            }
            
            echo '<div class="amazon-product-item" data-index="' . $product_count . '">';
            echo '<div class="amazon-product-image"><img src="' . esc_url($image) . '" alt="' . esc_attr(get_the_title()) . '"></div>';
            echo '<h4>' . esc_html(get_the_title()) . '</h4>';
            
            if ($atts['show_description'] === 'yes') {
                $excerpt = get_the_excerpt();
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words(get_the_content(), 15);
                }
                echo '<p class="amazon-product-description">' . wp_trim_words($excerpt, 12) . '</p>';
            }
            
            // Exibe preço se disponível
            if ($atts['show_price'] === 'yes') {
                echo '<div class="amazon-product-price">';
                if (!empty($sale_price) && !empty($regular_price) && $sale_price < $regular_price) {
                    echo '<span class="amazon-price-sale">R$ ' . number_format(floatval($sale_price), 2, ',', '.') . '</span>';
                    echo '<span class="amazon-price-regular">R$ ' . number_format(floatval($regular_price), 2, ',', '.') . '</span>';
                } elseif (!empty($price)) {
                    echo '<span class="amazon-price-current">R$ ' . number_format(floatval($price), 2, ',', '.') . '</span>';
                } elseif (!empty($regular_price)) {
                    echo '<span class="amazon-price-current">R$ ' . number_format(floatval($regular_price), 2, ',', '.') . '</span>';
                } else {
                    echo '<span class="amazon-price-check">Ver preço na Amazon</span>';
                }
                echo '</div>';
            }
            
            // Avaliação fake para melhor visual
            echo '<div class="amazon-product-rating">';
            echo '<span class="amazon-stars">★★★★☆</span>';
            echo '<span class="amazon-rating-text">(4.2/5 - Ver mais avaliações)</span>';
            echo '</div>';
            
            $target = ($atts['target_blank'] === 'yes') ? '_blank' : '_self';
            echo '<a href="' . esc_url($amazon_url) . '" target="' . $target . '" class="amazon-product-link">Ver na Amazon</a>';
            echo '</div>';
            
            $product_count++;
        }
        
        echo '</div>'; // Fecha amazon-products-grid
    }
    
    echo '<p class="amazon-disclaimer">* Links de afiliado. Podemos receber uma comissão por compras qualificadas.</p>';
    echo '</div>'; // Fecha amazon-products-list
    
    wp_reset_postdata();
    return ob_get_clean();
}

private function render_specific_amazon_products($atts) {
    $urls = explode(',', $atts['specific_urls']);
    $urls = array_map('trim', $urls);
    
    ob_start();
    echo '<div class="amazon-products-specific">';
    echo '<h3>Produtos Selecionados</h3>';
    echo '<div class="amazon-products-grid">';
    
    foreach ($urls as $url) {
        if (!empty($url)) {
            echo '<div class="amazon-product-item">';
            echo '<div class="amazon-product-image"><img src="https://via.placeholder.com/300x200?text=Produto+Amazon" alt="Produto Amazon"></div>';
            echo '<h4>Produto Amazon</h4>';
            echo '<a href="' . esc_url($this->add_associate_tag($url)) . '" target="_blank" class="amazon-product-link">Ver na Amazon</a>';
            echo '</div>';
        }
    }
    
    echo '</div>';
    echo '<p class="amazon-disclaimer">* Links de afiliado. Podemos receber uma comissão por compras qualificadas.</p>';
    echo '</div>';
    
    return ob_get_clean();
}

private function render_amazon_banner($atts, $amazon_url) {
    $amazon_url = $this->add_associate_tag($amazon_url);
    $product = get_post($atts['product_id']);
    $image = get_the_post_thumbnail_url($atts['product_id'], 'medium');
    
    ob_start();
    $banner_class = 'amazon-banner amazon-banner-' . $atts['type'];
    
    echo '<div class="' . esc_attr($banner_class) . '" style="background-color: ' . esc_attr($atts['background_color']) . '; color: ' . esc_attr($atts['text_color']) . ';">';
    
    if ($atts['type'] === 'horizontal') {
        echo '<div class="amazon-banner-content">';
        echo '<div class="amazon-banner-text">';
        echo '<h3>' . esc_html($atts['title']) . '</h3>';
        echo '<p>' . esc_html($atts['subtitle']) . '</p>';
        echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-banner-cta">' . esc_html($atts['button_text']) . '</a>';
        echo '</div>';
        if ($image) {
            echo '<div class="amazon-banner-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($product->post_title) . '"></div>';
        }
        echo '</div>';
    } else {
        if ($image) {
            echo '<div class="amazon-banner-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($product->post_title) . '"></div>';
        }
        echo '<div class="amazon-banner-text">';
        echo '<h3>' . esc_html($atts['title']) . '</h3>';
        echo '<p>' . esc_html($atts['subtitle']) . '</p>';
        echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-banner-cta">' . esc_html($atts['button_text']) . '</a>';
        echo '</div>';
    }
    
    echo '</div>';
    
    return ob_get_clean();
}

private function is_campaign_active($atts) {
    $current_time = current_time('timestamp');
    
    if (!empty($atts['start_date'])) {
        $start_time = strtotime($atts['start_date']);
        if ($current_time < $start_time) {
            return false;
        }
    }
    
    if (!empty($atts['end_date'])) {
        $end_time = strtotime($atts['end_date']);
        if ($current_time > $end_time) {
            return false;
        }
    }
    
    return true;
}

private function is_target_url($target_urls) {
    if (empty($target_urls)) {
        return true; // Se não especificado, exibe em todas as URLs
    }
    
    $current_url = home_url($_SERVER['REQUEST_URI']);
    $urls = explode(',', $target_urls);
    
    foreach ($urls as $url) {
        $url = trim($url);
        if (strpos($current_url, $url) !== false) {
            return true;
        }
    }
    
    return false;
}

private function render_campaign($atts) {
    // Implementação básica de campanha
    ob_start();
    echo '<div class="amazon-campaign amazon-campaign-' . esc_attr($atts['display_type']) . '">';
    echo '<h3>Campanha Especial</h3>';
    echo '<p>Campanha ID: ' . esc_html($atts['campaign_id']) . '</p>';
    echo '</div>';
    return ob_get_clean();
}

private function render_glossary_term($atts) {
    // Busca o termo no glossário
    $glossary_query = new WP_Query(array(
        'post_type' => 'amazon_glossary',
        'title' => $atts['term'],
        'posts_per_page' => 1
    ));
    
    ob_start();
    
    if ($glossary_query->have_posts()) {
        $glossary_query->the_post();
        $related_products = get_post_meta(get_the_ID(), '_amazon_related_products', true);
        $search_terms = get_post_meta(get_the_ID(), '_amazon_search_terms', true);
        
        echo '<div class="amazon-glossary-term">';
        echo '<h4 class="glossary-term">' . get_the_title() . '</h4>';
        echo '<div class="glossary-definition">' . get_the_content() . '</div>';
        
        if ($atts['show_products'] === 'yes') {
            echo '<div class="glossary-related-products">';
            echo '<h5>Produtos Relacionados</h5>';
            
            // Busca produtos relacionados por IDs
            if (!empty($related_products)) {
                $product_ids = array_map('trim', explode(',', $related_products));
                $products_query = new WP_Query(array(
                    'post__in' => $product_ids,
                    'post_type' => array('post', 'product'),
                    'posts_per_page' => intval($atts['products_count'])
                ));
                
                if ($products_query->have_posts()) {
                    echo '<div class="glossary-products-grid">';
                    while ($products_query->have_posts()) {
                        $products_query->the_post();
                        $amazon_url = $this->get_amazon_url(get_the_ID());
                        if (!empty($amazon_url)) {
                            $amazon_url = $this->add_associate_tag($amazon_url);
                            $image = get_the_post_thumbnail_url(get_the_ID(), 'medium');
                            
                            echo '<div class="glossary-product-item">';
                            if ($image) {
                                echo '<img src="' . esc_url($image) . '" alt="' . esc_attr(get_the_title()) . '" class="glossary-product-image">';
                            }
                            echo '<h6>' . get_the_title() . '</h6>';
                            echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="glossary-product-link">Ver na Amazon</a>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                    wp_reset_postdata();
                } else {
                    echo '<p>Nenhum produto relacionado encontrado.</p>';
                }
            } else {
                echo '<p>Nenhum produto configurado para este termo.</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<div class="amazon-glossary-term">';
        echo '<h4 class="glossary-term">' . esc_html($atts['term']) . '</h4>';
        echo '<div class="glossary-definition">Termo não encontrado no glossário.</div>';
        echo '</div>';
    }
    
    return ob_get_clean();
}

// Registra custom post types
public function register_custom_post_types() {
    // Custom post type para glossário
    register_post_type('amazon_glossary', array(
        'labels' => array(
            'name' => 'Glossário Amazon',
            'singular_name' => 'Termo do Glossário',
            'add_new' => 'Adicionar Termo',
            'add_new_item' => 'Adicionar Novo Termo',
            'edit_item' => 'Editar Termo',
            'new_item' => 'Novo Termo',
            'view_item' => 'Ver Termo',
            'search_items' => 'Buscar Termos',
            'not_found' => 'Nenhum termo encontrado',
            'not_found_in_trash' => 'Nenhum termo na lixeira'
        ),
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-book-alt',
        'supports' => array('title', 'editor', 'thumbnail'),
        'rewrite' => array('slug' => 'glossario'),
        'show_in_rest' => true,
        'show_in_menu' => 'amazon_affiliate_popup'
    ));
    
    // Custom post type para campanhas
    register_post_type('amazon_campaign', array(
        'labels' => array(
            'name' => 'Campanhas Amazon',
            'singular_name' => 'Campanha',
            'add_new' => 'Adicionar Campanha',
            'add_new_item' => 'Adicionar Nova Campanha',
            'edit_item' => 'Editar Campanha',
            'new_item' => 'Nova Campanha',
            'view_item' => 'Ver Campanha',
            'search_items' => 'Buscar Campanhas',
            'not_found' => 'Nenhuma campanha encontrada',
            'not_found_in_trash' => 'Nenhuma campanha na lixeira'
        ),
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-megaphone',
        'supports' => array('title', 'editor'),
        'show_in_rest' => true,
        'show_in_menu' => 'amazon_affiliate_popup'
    ));
}

// Adiciona metaboxes
public function add_glossary_meta_boxes() {
    // Metabox para termos do glossário
    add_meta_box(
        'amazon_glossary_products',
        'Produtos Amazon Relacionados',
        array($this, 'glossary_products_meta_box'),
        'amazon_glossary',
        'normal',
        'high'
    );
    
    // Metabox para campanhas
    add_meta_box(
        'amazon_campaign_settings',
        'Configurações da Campanha',
        array($this, 'campaign_settings_meta_box'),
        'amazon_campaign',
        'normal',
        'high'
    );
    
    // Metabox para posts/páginas configurarem campanhas específicas
    add_meta_box(
        'amazon_page_campaign',
        'Campanha Amazon Específica',
        array($this, 'page_campaign_meta_box'),
        array('post', 'page'),
        'side',
        'default'
    );
}

// Metabox para produtos relacionados no glossário
public function glossary_products_meta_box($post) {
    wp_nonce_field('amazon_glossary_meta', 'amazon_glossary_nonce');
    
    $related_products = get_post_meta($post->ID, '_amazon_related_products', true);
    $amazon_search_terms = get_post_meta($post->ID, '_amazon_search_terms', true);
    
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="amazon_search_terms">Termos de Busca Amazon</label></th>';
    echo '<td><input type="text" id="amazon_search_terms" name="amazon_search_terms" value="' . esc_attr($amazon_search_terms) . '" class="widefat" placeholder="palavra-chave1, palavra-chave2">';
    echo '<p class="description">Palavras-chave separadas por vírgula para buscar produtos relacionados.</p></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th><label for="amazon_related_products">IDs de Produtos Relacionados</label></th>';
    echo '<td><textarea id="amazon_related_products" name="amazon_related_products" rows="3" class="widefat" placeholder="123, 456, 789">' . esc_textarea($related_products) . '</textarea>';
    echo '<p class="description">IDs de posts/produtos separados por vírgula que contêm links Amazon.</p></td>';
    echo '</tr>';
    echo '</table>';
}

// Metabox para configurações de campanha
public function campaign_settings_meta_box($post) {
    wp_nonce_field('amazon_campaign_meta', 'amazon_campaign_nonce');
    
    $start_date = get_post_meta($post->ID, '_campaign_start_date', true);
    $end_date = get_post_meta($post->ID, '_campaign_end_date', true);
    $target_urls = get_post_meta($post->ID, '_campaign_target_urls', true);
    $display_type = get_post_meta($post->ID, '_campaign_display_type', true);
    $display_position = get_post_meta($post->ID, '_campaign_display_position', true);
    $products = get_post_meta($post->ID, '_campaign_products', true);
    
    echo '<table class="form-table">';
    
    echo '<tr>';
    echo '<th><label for="campaign_start_date">Data de Início</label></th>';
    echo '<td><input type="datetime-local" id="campaign_start_date" name="campaign_start_date" value="' . esc_attr($start_date) . '"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th><label for="campaign_end_date">Data de Fim</label></th>';
    echo '<td><input type="datetime-local" id="campaign_end_date" name="campaign_end_date" value="' . esc_attr($end_date) . '"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th><label for="campaign_target_urls">URLs Alvo</label></th>';
    echo '<td><textarea id="campaign_target_urls" name="campaign_target_urls" rows="3" class="widefat" placeholder="/categoria/produto, /post-especifico">' . esc_textarea($target_urls) . '</textarea>';
    echo '<p class="description">URLs onde a campanha deve aparecer (uma por linha). Deixe em branco para exibir em todas.</p></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th><label for="campaign_display_type">Tipo de Exibição</label></th>';
    echo '<td><select id="campaign_display_type" name="campaign_display_type">';
    echo '<option value="popup"' . selected($display_type, 'popup', false) . '>Popup</option>';
    echo '<option value="banner_horizontal"' . selected($display_type, 'banner_horizontal', false) . '>Banner Horizontal</option>';
    echo '<option value="banner_vertical"' . selected($display_type, 'banner_vertical', false) . '>Banner Vertical</option>';
    echo '<option value="sticky_bar"' . selected($display_type, 'sticky_bar', false) . '>Barra Fixa</option>';
    echo '</select></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th><label for="campaign_display_position">Posição</label></th>';
    echo '<td><select id="campaign_display_position" name="campaign_display_position">';
    echo '<option value="auto"' . selected($display_position, 'auto', false) . '>Automático</option>';
    echo '<option value="header"' . selected($display_position, 'header', false) . '>Cabeçalho</option>';
    echo '<option value="footer"' . selected($display_position, 'footer', false) . '>Rodapé</option>';
    echo '<option value="before_content"' . selected($display_position, 'before_content', false) . '>Antes do Conteúdo</option>';
    echo '<option value="after_content"' . selected($display_position, 'after_content', false) . '>Depois do Conteúdo</option>';
    echo '</select></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th><label for="campaign_products">IDs dos Produtos</label></th>';
    echo '<td><textarea id="campaign_products" name="campaign_products" rows="3" class="widefat" placeholder="123, 456, 789">' . esc_textarea($products) . '</textarea>';
    echo '<p class="description">IDs de posts/produtos com links Amazon para esta campanha.</p></td>';
    echo '</tr>';
    
    echo '</table>';
}

// Metabox para campanhas específicas em posts/páginas
public function page_campaign_meta_box($post) {
    wp_nonce_field('amazon_page_campaign_meta', 'amazon_page_campaign_nonce');
    
    $campaign_id = get_post_meta($post->ID, '_amazon_page_campaign', true);
    $custom_products = get_post_meta($post->ID, '_amazon_custom_products', true);
    
    // Busca campanhas disponíveis
    $campaigns = get_posts(array(
        'post_type' => 'amazon_campaign',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    echo '<p><label for="amazon_page_campaign">Campanha Ativa:</label></p>';
    echo '<select id="amazon_page_campaign" name="amazon_page_campaign" class="widefat">';
    echo '<option value="">Nenhuma campanha específica</option>';
    
    foreach ($campaigns as $campaign) {
        echo '<option value="' . $campaign->ID . '"' . selected($campaign_id, $campaign->ID, false) . '>' . esc_html($campaign->post_title) . '</option>';
    }
    
    echo '</select>';
    
    echo '<p style="margin-top: 15px;"><label for="amazon_custom_products">Ou, IDs de Produtos Customizados:</label></p>';
    echo '<textarea id="amazon_custom_products" name="amazon_custom_products" rows="3" class="widefat" placeholder="123, 456, 789">' . esc_textarea($custom_products) . '</textarea>';
    echo '<p class="description">IDs de produtos específicos para esta página (substitui a campanha selecionada).</p>';
}

// Salva dados dos metaboxes
public function save_meta_box_data($post_id, $post) {
    // Verifica se é um autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Verifica permissões
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Salva dados do glossário
    if (isset($_POST['amazon_glossary_nonce']) && wp_verify_nonce($_POST['amazon_glossary_nonce'], 'amazon_glossary_meta')) {
        if (isset($_POST['amazon_search_terms'])) {
            update_post_meta($post_id, '_amazon_search_terms', sanitize_text_field($_POST['amazon_search_terms']));
        }
        
        if (isset($_POST['amazon_related_products'])) {
            update_post_meta($post_id, '_amazon_related_products', sanitize_textarea_field($_POST['amazon_related_products']));
        }
    }
    
    // Salva dados da campanha
    if (isset($_POST['amazon_campaign_nonce']) && wp_verify_nonce($_POST['amazon_campaign_nonce'], 'amazon_campaign_meta')) {
        $fields = array(
            'campaign_start_date' => '_campaign_start_date',
            'campaign_end_date' => '_campaign_end_date',
            'campaign_target_urls' => '_campaign_target_urls',
            'campaign_display_type' => '_campaign_display_type',
            'campaign_display_position' => '_campaign_display_position',
            'campaign_products' => '_campaign_products'
        );
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                if (in_array($field, array('campaign_target_urls', 'campaign_products'))) {
                    update_post_meta($post_id, $meta_key, sanitize_textarea_field($_POST[$field]));
                } else {
                    update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
                }
            }
        }
    }
    
    // Salva dados de campanha por página
    if (isset($_POST['amazon_page_campaign_nonce']) && wp_verify_nonce($_POST['amazon_page_campaign_nonce'], 'amazon_page_campaign_meta')) {
        if (isset($_POST['amazon_page_campaign'])) {
            update_post_meta($post_id, '_amazon_page_campaign', sanitize_text_field($_POST['amazon_page_campaign']));
        }
        
        if (isset($_POST['amazon_custom_products'])) {
            update_post_meta($post_id, '_amazon_custom_products', sanitize_textarea_field($_POST['amazon_custom_products']));
        }
    }
}
} // Fecha a classe AmazonAffiliatePopup

// Inicializa o plugin
$amazon_affiliate_popup = new AmazonAffiliatePopup();

// Hook de ativação
register_activation_hook(__FILE__, 'amazon_affiliate_activation');
function amazon_affiliate_activation() {
    // Força o WordPress a reescrever as URLs
    flush_rewrite_rules();
}

// Hook de desativação
register_deactivation_hook(__FILE__, 'amazon_affiliate_deactivation');
function amazon_affiliate_deactivation() {
    // Limpa as URLs reescritas
    flush_rewrite_rules();
}

// AJAX para verificar todos os produtos
add_action('wp_ajax_scan_all_amazon_products', 'scan_all_amazon_products_callback');
function scan_all_amazon_products_callback() {
    // Log para debug
    error_log('Função scan_all_amazon_products_callback chamada');
    
    check_ajax_referer('scan_all_amazon_products', 'nonce');
    
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    error_log('Offset recebido: ' . $offset);
    
    $plugin = new AmazonAffiliatePopup();
    $result = $plugin->scan_all_amazon_products($offset);
    
    error_log('Resultado da verificação: ' . print_r($result, true));
    
    // Calcula estatísticas para a barra de progresso
    $batch_size = 10; // Tamanho do lote processado
    $processed_in_batch = $result['processed'];
    
    // Mensagem mais informativa
    if ($processed_in_batch > 0) {
        $message = 'Encontrados ' . $processed_in_batch . ' novos links Amazon neste lote.';
    } else {
        $message = 'Lote processado - nenhum novo link Amazon encontrado.';
    }
    
    wp_send_json_success(array(
        'message' => $message,
        'continue' => $result['continue'],
        'offset' => $result['offset'],
        'processed' => $processed_in_batch,
        'batch_size' => $batch_size,
        'current_offset' => $offset
    ));
}