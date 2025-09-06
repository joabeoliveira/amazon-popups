<?php
/**
 * Plugin Name: Popup de Afiliados Amazon
 * Description: Exibe popups com produtos de afiliado Amazon do WooCommerce e posts comuns
 * Version: 2.0
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
        
        // Shortcode para exibir produtos manualmente
        add_shortcode('amazon_products', array($this, 'amazon_products_shortcode'));
        
        // Hook para verificar links Amazon em produtos WooCommerce
        add_action('save_post', array($this, 'check_amazon_links'), 20, 3);
        
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
            'dicons-cart',
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
                            <p>Procura por links Amazon em todos os produtos e posts existentes</p>
                            <button type="button" class="button button-primary" id="scan-all-products">Verificar Todos os Conteúdos</button>
                            <div id="scan-results" style="margin-top: 20px; display: none;">
                                <h4>Resultado da Verificação</h4>
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
                    <p>O plugin Popup Amazon exibe automaticamente produtos de afiliado Amazon em seu site.</p>
                    
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Popup automático</strong> - Aparece em todos os posts após alguns segundos</li>
                        <li><strong>Shortcode</strong> - Use [amazon_products] para exibir produtos manualmente</li>
                        <li><strong>Detecção automática</strong> - Encontra links Amazon automaticamente</li>
                        <li><strong>Tag de associado</strong> - Adiciona seu ID automaticamente aos links</li>
                    </ul>
                    
                    <h3>Shortcodes Disponíveis</h3>
                    <div class="amazon-code-example">
                        <p><code>[amazon_products]</code> - Exibe 3 produtos</p>
                        <p><code>[amazon_products count="5"]</code> - Exibe 5 produtos</p>
                        <p><code>[amazon_products category="saude"]</code> - Produtos de uma categoria específica</p>
                    </div>
                    
                    <h3>Dicas de Otimização</h3>
                    <ul>
                        <li>Use o popup automático para maximizar conversões</li>
                        <li>Combine com shortcodes estratégicos no conteúdo</li>
                        <li>Verifique regularmente os links Amazon</li>
                        <li>Teste diferentes tempos de exibição do popup</li>
                    </ul>
                </div>
            </div>
        </div>
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
    if (!is_singular('post')) {
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
    
    // SEMPRE carrega os scripts, independentemente de ter produtos Amazon
    wp_enqueue_style('amazon-affiliate-popup', plugin_dir_url(__FILE__) . 'popup-style.css');
    wp_enqueue_script('amazon-affiliate-popup', plugin_dir_url(__FILE__) . 'popup-script.js', array('jquery'), '1.0', true);
    
    $delay = isset($this->options['popup_delay']) ? intval($this->options['popup_delay']) : 15;
    
    wp_localize_script('amazon-affiliate-popup', 'amazon_popup_vars', array(
        'delay' => $delay * 1000,
        'ajax_url' => admin_url('admin-ajax.php'),
        'post_id' => $post->ID,
        'categories' => $category_ids,
        'nonce' => wp_create_nonce('get_amazon_products')
    ));
}
    
    // Adiciona scripts e estilos no admin
    public function admin_enqueue_scripts($hook) {
        wp_enqueue_style('amazon-affiliate-admin', plugin_dir_url(__FILE__) . 'admin-style.css');
        
        if ($hook === 'settings_page_amazon_affiliate_popup' || $hook === 'toplevel_page_amazon_affiliate_popup') {
            wp_enqueue_script('amazon-affiliate-admin', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '1.0', true);
            
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
            ?>
            <div class="amazon-popup-product">
                <h3 class="amazon-popup-headline">Oferta Especial para Leitores!</h3>

                <div class="amazon-product-image">
                    <img src="<?php echo esc_url($random_product['image'] ?: plugin_dir_url(__FILE__) . 'default-product.jpg'); ?>" alt="<?php echo esc_attr($random_product['title']); ?>">
                </div>
                <h4><?php echo esc_html($random_product['title']); ?></h4>

                <p>Encontramos esta oferta na Amazon que pode ser do seu interesse. Confira antes que acabe!</p>

                <a href="<?php echo esc_url($random_product['url']); ?>" target="_blank" class="amazon-popup-cta">
                    Conferir Oferta Agora
                </a>
                <p class="amazon-disclaimer">* Este é um link de afiliado. Podemos receber uma comissão por compras qualificadas.</p>
            </div>
            <?php
            $content = ob_get_clean();

            wp_send_json_success($content);
        }
    }
    
    wp_send_json_error('Nenhum produto relacionado encontrado.');
}
    
    // Shortcode para exibir produtos manualmente
    public function amazon_products_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => 3,
            'post_type' => 'product'
        ), $atts);
        
        // Busca posts com links Amazon
        $args = array(
            'post_type' => $atts['post_type'],
            'posts_per_page' => intval($atts['count']),
            'meta_key' => '_amazon_affiliate_url',
            'meta_compare' => 'EXISTS'
        );
        
        $products = new WP_Query($args);
        
        if (!$products->have_posts()) {
            return '<p>Nenhum produto Amazon encontrado.</p>';
        }
        
        ob_start();
        echo '<div class="amazon-products-list">';
        echo '<h3>Produtos Recomendados</h3>';
        echo '<div class="amazon-products-grid">';
        
        while ($products->have_posts()) {
            $products->the_post();
            $amazon_url = $this->get_amazon_url(get_the_ID());
            $amazon_url = $this->add_associate_tag($amazon_url);
            $image = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            
            if (empty($image)) {
                $image = plugin_dir_url(__FILE__) . 'default-product.jpg';
            }
            
            echo '<div class="amazon-product-item">';
            echo '<div class="amazon-product-image"><img src="' . esc_url($image) . '" alt="' . esc_attr(get_the_title()) . '"></div>';
            echo '<h4>' . esc_html(get_the_title()) . '</h4>';
            echo '<a href="' . esc_url($amazon_url) . '" target="_blank" class="amazon-product-link">Ver na Amazon</a>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<p class="amazon-disclaimer">* Links de afiliado. Podemos receber uma comissão por compras qualificadas.</p>';
        echo '</div>';
        
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    // Verifica todos os produtos existentes
    public function scan_all_amazon_products($offset = 0) {
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


// Inicializa o plugin
new AmazonAffiliatePopup();

// AJAX para verificar todos os produtos
add_action('wp_ajax_scan_all_amazon_products', 'scan_all_amazon_products_callback');
function scan_all_amazon_products_callback() {
    check_ajax_referer('scan_all_amazon_products', 'nonce');
    
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    $plugin = new AmazonAffiliatePopup();
    $result = $plugin->scan_all_amazon_products($offset);
    
    if ($result['processed'] > 0) {
        $message = 'Processados ' . $result['processed'] . ' posts/produtos com links Amazon.';
    } else {
        $message = 'Nenhum link Amazon encontrado no lote atual.';
    }
    
    wp_send_json_success(array(
        'message' => $message,
        'continue' => $result['continue'],
        'offset' => $result['offset']
    ));
}