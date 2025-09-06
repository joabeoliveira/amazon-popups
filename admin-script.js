jQuery(document).ready(function($) {
    // Adiciona funcionalidade ao botão de verificação
    $('#scan-all-products').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('Verificando...');
        $('#scan-results').show();
        $('#scan-progress').html('<div class="notice notice-info"><p>Iniciando verificação de conteúdo...</p></div>');
        
        // Chamada AJAX para verificar produtos
        checkProducts(0);
        
        function checkProducts(offset) {
            $.post(ajaxurl, {
                action: 'scan_all_amazon_products',
                offset: offset,
                nonce: amazon_affiliate_admin.nonce
            }, function(response) {
                if (response.success) {
                    $('#scan-progress').append('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    
                    if (response.data.continue) {
                        checkProducts(response.data.offset);
                    } else {
                        $('#scan-progress').append('<div class="notice notice-success"><p><strong>Verificação concluída!</strong></p></div>');
                        button.prop('disabled', false).text('Verificar Todos os Conteúdos');
                        
                        // Recarrega a página após 2 segundos para atualizar estatísticas
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    $('#scan-progress').append('<div class="notice notice-error"><p>Erro: ' + response.data + '</p></div>');
                    button.prop('disabled', false).text('Verificar Todos os Conteúdos');
                }
            }).fail(function() {
                $('#scan-progress').append('<div class="notice notice-error"><p><strong>Ocorreu um erro crítico no servidor. Tente novamente.</strong></p></div>');
                button.prop('disabled', false).text('Verificar Todos os Conteúdos');
            });
        }
    });
    
    // Teste do popup
    $('#test-popup').on('click', function(e) {
        e.preventDefault();
        
        // Cria um popup de teste
        var testPopup = $(
            '<div class="amazon-popup-overlay active" style="position:fixed; z-index:999999;">' +
            '<div class="amazon-popup" style="position:relative">' +
            '<button class="close-amazon-popup">&times;</button>' +
            '<div class="amazon-popup-product">' +
            '<h3 class="amazon-popup-headline">Teste de Popup</h3>' +
            '<div class="amazon-product-image"><img src="https://via.placeholder.com/300x200?text=Produto+Teste" alt="Produto Teste"></div>' +
            '<h4>Produto de Teste do Plugin Amazon</h4>' +
            '<p>Este é um popup de teste. Quando configurado corretamente, produtos reais serão exibidos aqui.</p>' +
            '<a href="#" class="amazon-popup-cta">Ver na Amazon</a>' +
            '<p class="amazon-disclaimer">* Este é um link de afiliado de teste.</p>' +
            '</div></div></div>'
        );
        
        $('body').append(testPopup);
        
        // Fecha o popup
        testPopup.find('.close-amazon-popup').on('click', function() {
            testPopup.remove();
        });
        
        testPopup.on('click', function(e) {
            if (e.target === testPopup[0]) {
                testPopup.remove();
            }
        });
    });
    
    // Melhoria para a metabox
    $('#scan-amazon-links').on('click', function() {
        var button = $(this);
        var postId = $('#post_ID').val();
        
        button.text('Escaneando...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'scan_amazon_links',
                post_id: postId,
                nonce: amazon_affiliate_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Foram encontrados ' + response.data.count + ' links Amazon.');
                    location.reload();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            complete: function() {
                button.text('Procurar Links Amazon').prop('disabled', false);
            }
        });
    });
});