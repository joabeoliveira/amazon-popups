jQuery(document).ready(function($) {
    // Verifica o tipo de exibição
    var displayPosition = amazon_popup_vars.display_position || 'auto';
    var displayType = amazon_popup_vars.display_type || 'popup';
    
    // Se for exibição automática (popup), usa o delay
    if (displayPosition === 'auto') {
        setTimeout(function() {
            // Verifica se já foi exibido nesta sessão
            if (!sessionStorage.getItem('amazonPopupShown')) {
                loadAmazonProducts();
                sessionStorage.setItem('amazonPopupShown', 'true');
            }
        }, amazon_popup_vars.delay);
    }
    
    // Funcionalidade para fechar barra fixa
    $(document).on('click', '.amazon-sticky-close', function() {
        $(this).closest('.amazon-sticky-bar').fadeOut();
    });
    
    // Funcionalidade para cartões dismissíveis
    $(document).on('click', '.amazon-card-close', function() {
        $(this).closest('.amazon-display-card').fadeOut();
    });
    
    function loadAmazonProducts() {
        $.ajax({
            url: amazon_popup_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_amazon_products',
                post_id: amazon_popup_vars.post_id,
                categories: amazon_popup_vars.categories,
                nonce: amazon_popup_vars.nonce,
                display_type: displayType
            },
            success: function(response) {
                if (response.success) {
                    showAmazonContent(response.data, displayType);
                }
            },
            error: function() {
                console.log('Erro ao carregar produtos Amazon');
            }
        });
    }
    
    function showAmazonContent(content, type) {
        if (type === 'popup') {
            showPopup(content);
        } else if (type === 'sticky_bar') {
            showStickyBar(content);
        } else {
            showInlineContent(content, type);
        }
    }
    
    function showPopup(content) {
        // Cria o overlay do popup
        var popupOverlay = $('<div class="amazon-popup-overlay"></div>');
        var popup = $('<div class="amazon-popup"></div>');
        var closeButton = $('<button class="close-amazon-popup">&times;</button>');
        
        popup.append(closeButton);
        popup.append(content);
        popupOverlay.append(popup);
        
        $('body').append(popupOverlay);
        
        // Exibe o popup
        setTimeout(function() {
            popupOverlay.addClass('active');
        }, 100);
        
        // Fecha o popup ao clicar no botão ou fora
        closeButton.on('click', function() {
            closePopup(popupOverlay);
        });
        
        popupOverlay.on('click', function(e) {
            if (e.target === popupOverlay[0]) {
                closePopup(popupOverlay);
            }
        });
        
        // Abre o link Amazon em uma nova janela quando clicar no botão
        $('.amazon-popup-cta').on('click', function(e) {
            e.stopPropagation();
            window.open($(this).attr('href'), '_blank');
            closePopup(popupOverlay);
            return false;
        });
    }
    
    function showStickyBar(content) {
        if ($('.amazon-sticky-bar').length === 0) {
            $('body').append('<div class="amazon-sticky-bar">' + content + '</div>');
            
            // Adiciona margem inferior ao body para compensar a barra fixa
            $('body').css('margin-bottom', '70px');
        }
    }
    
    function showInlineContent(content, type) {
        var containerClass = 'amazon-display amazon-display-' + type;
        var container = $('<div class="' + containerClass + '">' + content + '</div>');
        
        // Adiciona ao final do conteúdo principal
        if ($('.entry-content').length) {
            $('.entry-content').append(container);
        } else if ($('main').length) {
            $('main').append(container);
        } else {
            $('body').append(container);
        }
    }
    
    function closePopup(popup) {
        popup.removeClass('active');
        setTimeout(function() {
            popup.remove();
        }, 300);
    }
    
    // Funções adicionais para melhor UX
    
    // Fecha barra fixa ao clicar no botão X
    $(document).on('click', '.amazon-sticky-close', function() {
        $('.amazon-sticky-bar').fadeOut(function() {
            $(this).remove();
            $('body').css('margin-bottom', '0');
        });
    });
    
    // Adiciona efeitos de hover em cartões
    $(document).on('mouseenter', '.amazon-display-card', function() {
        $(this).css('transform', 'translateY(-5px)');
    }).on('mouseleave', '.amazon-display-card', function() {
        $(this).css('transform', 'translateY(0)');
    });
    
    // Tracking de cliques para analytics (placeholder)
    $(document).on('click', '.amazon-product-link, .amazon-popup-cta, .amazon-banner-cta, .amazon-sticky-cta', function() {
        var url = $(this).attr('href');
        // Aqui seria implementado o tracking de cliques
        console.log('Clique em link Amazon:', url);
    });
});