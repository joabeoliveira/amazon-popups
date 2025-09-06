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
        var stickyBar = $(this).closest('.amazon-sticky-bar');
        stickyBar.removeClass('active');
        setTimeout(function() {
            stickyBar.remove();
        }, 400);
    });
    
    // Funcionalidade para cartões dismissíveis
    $(document).on('click', '.amazon-card-close', function() {
        $(this).closest('.amazon-display-card').fadeOut(300, function() {
            $(this).remove();
        });
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
        console.log('Exibindo conteúdo Amazon - tipo:', type);
        
        switch(type) {
            case 'popup':
                showPopup(content);
                break;
            case 'sticky_bar':
                showStickyBar(content);
                break;
            case 'banner_horizontal':
                showBanner(content, 'horizontal');
                break;
            case 'banner_vertical':
                showBanner(content, 'vertical');
                break;
            case 'card':
                showCard(content);
                break;
            default:
                showPopup(content);
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
        // Remove sticky bar existente
        $('.amazon-sticky-bar').remove();
        
        // Extrai dados do conteúdo
        var $tempDiv = $('<div>').html(content);
        var title = $tempDiv.find('h4').text() || 'Oferta Especial';
        var amazonUrl = $tempDiv.find('a').attr('href') || '#';
        
        // Cria sticky bar
        var stickyBar = $('<div class="amazon-sticky-bar">' +
            '<span class="amazon-sticky-text">🎯 ' + title + '</span>' +
            '<a href="' + amazonUrl + '" target="_blank" class="amazon-sticky-cta">Ver na Amazon</a>' +
            '<button class="amazon-sticky-close">&times;</button>' +
        '</div>');
        
        $('body').append(stickyBar);
        
        // Anima a entrada
        setTimeout(function() {
            stickyBar.addClass('active');
        }, 100);
        
        // Fecha automaticamente após 15 segundos
        setTimeout(function() {
            if (stickyBar.hasClass('active')) {
                stickyBar.removeClass('active');
                setTimeout(function() {
                    stickyBar.remove();
                }, 400);
            }
        }, 15000);
    }
    
    function showBanner(content, orientation) {
        // Extrai dados do conteúdo
        var $tempDiv = $('<div>').html(content);
        var title = $tempDiv.find('h4').text() || 'Oferta Especial';
        var image = $tempDiv.find('img').attr('src') || '';
        var amazonUrl = $tempDiv.find('a').attr('href') || '#';
        
        var bannerClass = 'amazon-banner amazon-banner-' + orientation;
        var bannerHtml;
        
        if (orientation === 'horizontal') {
            bannerHtml = '<div class="' + bannerClass + '" style="background: linear-gradient(135deg, #FF9900 0%, #FF6600 100%); color: white;">' +
                '<div class="amazon-banner-content">' +
                    '<div class="amazon-banner-text">' +
                        '<h3>' + title + '</h3>' +
                        '<p>Confira esta oferta especial na Amazon!</p>' +
                        '<a href="' + amazonUrl + '" target="_blank" class="amazon-banner-cta">Ver Oferta</a>' +
                    '</div>' +
                    (image ? '<div class="amazon-banner-image"><img src="' + image + '" alt="' + title + '"></div>' : '') +
                '</div>' +
            '</div>';
        } else {
            bannerHtml = '<div class="' + bannerClass + '" style="background: linear-gradient(135deg, #FF9900 0%, #FF6600 100%); color: white;">' +
                (image ? '<div class="amazon-banner-image"><img src="' + image + '" alt="' + title + '"></div>' : '') +
                '<div class="amazon-banner-text">' +
                    '<h3>' + title + '</h3>' +
                    '<p>Oferta especial</p>' +
                    '<a href="' + amazonUrl + '" target="_blank" class="amazon-banner-cta">Ver na Amazon</a>' +
                '</div>' +
            '</div>';
        }
        
        // Adiciona após o conteúdo principal
        if ($('.entry-content').length > 0) {
            $('.entry-content').after(bannerHtml);
        } else if ($('.post-content').length > 0) {
            $('.post-content').after(bannerHtml);
        } else {
            $('main').append(bannerHtml);
        }
    }
    
    function showCard(content) {
        // Extrai dados do conteúdo
        var $tempDiv = $('<div>').html(content);
        var title = $tempDiv.find('h4').text() || 'Produto Amazon';
        var image = $tempDiv.find('img').attr('src') || '';
        var amazonUrl = $tempDiv.find('a').attr('href') || '#';
        
        var cardHtml = '<div class="amazon-display-card">' +
            '<button class="amazon-card-close">&times;</button>' +
            '<div class="amazon-card-content">' +
                (image ? '<div class="amazon-card-image"><img src="' + image + '" alt="' + title + '"></div>' : '') +
                '<div class="amazon-card-text">' +
                    '<h4>' + title + '</h4>' +
                    '<p>Encontramos este produto que pode interessar você. Confira na Amazon!</p>' +
                    '<a href="' + amazonUrl + '" target="_blank" class="amazon-card-cta">Ver na Amazon</a>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        // Adiciona após o conteúdo principal
        if ($('.entry-content').length > 0) {
            $('.entry-content').after(cardHtml);
        } else if ($('.post-content').length > 0) {
            $('.post-content').after(cardHtml);
        } else {
            $('main').append(cardHtml);
        }
    }
    
    function closePopup(popup) {
        popup.removeClass('active');
        setTimeout(function() {
            popup.remove();
        }, 300);
    }
    
    // Tracking de cliques para analytics (placeholder)
    $(document).on('click', '.amazon-product-link, .amazon-popup-cta, .amazon-banner-cta, .amazon-sticky-cta, .amazon-card-cta', function() {
        var url = $(this).attr('href');
        var type = $(this).closest('.amazon-popup-overlay').length > 0 ? 'popup' :
                   $(this).closest('.amazon-sticky-bar').length > 0 ? 'sticky_bar' :
                   $(this).closest('.amazon-banner').length > 0 ? 'banner' :
                   $(this).closest('.amazon-display-card').length > 0 ? 'card' : 'other';
        
        console.log('Clique em link Amazon (' + type + '):', url);
        
        // Fecha popup se for clique de dentro do popup
        if (type === 'popup') {
            var popupOverlay = $(this).closest('.amazon-popup-overlay');
            setTimeout(function() {
                closePopup(popupOverlay);
            }, 100);
        }
    });
    
    // ============================================
    // === FUNCIONALIDADE REMOVIDA - CARROSSEL ===
    // ============================================
    
    // Carrossel Bootstrap foi removido para evitar conflitos
    // Substituído por cards simples que funcionam melhor
    console.log('Amazon Plugin: Funcionalidade de carrossel removida');
    
    // ============================================
    // === FUNCIONALIDADE SHORTCODE DETALHADO ===
    // ============================================
    
    // Funcionalidade para abas do shortcode detalhado
    $(document).on('click', '.amazon-tab-btn', function() {
        var $this = $(this);
        var $container = $this.closest('.amazon-info-tabs');
        var targetTab = $this.data('tab');
        
        // Remove active de todos os botões e adiciona no clicado
        $container.find('.amazon-tab-btn').removeClass('active');
        $this.addClass('active');
        
        // Hide todas as abas e mostra a selecionada
        $container.find('.amazon-tab-pane').removeClass('active');
        $container.find('#' + targetTab).addClass('active');
    });
    
    // Funcionalidade para galeria de imagens
    $(document).on('click', '.amazon-gallery-thumb', function() {
        var $this = $(this);
        var newSrc = $this.attr('src');
        var $mainImage = $this.closest('.amazon-product-gallery').find('.amazon-product-image-main');
        
        // Troca a imagem principal
        $mainImage.attr('src', newSrc);
        
        // Remove active de todas as thumbs e adiciona na clicada
        $this.siblings('.amazon-gallery-thumb').removeClass('active');
        $this.addClass('active');
    });
    
    console.log('Amazon Plugin: Funcionalidade de shortcode detalhado carregada');
});