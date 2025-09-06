jQuery(document).ready(function($) {
    // Exibe o popup após o delay configurado
    setTimeout(function() {
        // Verifica se já foi exibido nesta sessão
        if (!sessionStorage.getItem('amazonPopupShown')) {
            loadAmazonProducts();
            sessionStorage.setItem('amazonPopupShown', 'true');
        }
    }, amazon_popup_vars.delay);
    
    function loadAmazonProducts() {
        $.ajax({
            url: amazon_popup_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_amazon_products',
                post_id: amazon_popup_vars.post_id,
                nonce: amazon_popup_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Cria o overlay do popup
                    var popupOverlay = $('<div class="amazon-popup-overlay"></div>');
                    var popup = $('<div class="amazon-popup"></div>');
                    var closeButton = $('<button class="close-amazon-popup">&times;</button>');
                    
                    popup.append(closeButton);
                    popup.append(response.data);
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
            },
            error: function() {
                console.log('Erro ao carregar produtos Amazon');
            }
        });
    }
    
    function closePopup(popup) {
        popup.removeClass('active');
        setTimeout(function() {
            popup.remove();
        }, 300);
    }
});