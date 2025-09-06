jQuery(document).ready(function($) {
    console.log('Amazon Popup Admin Scripts - Versão 2.6 carregado com sucesso');
    
    // Verifica se as variáveis estão disponíveis
    if (typeof amazon_affiliate_admin === 'undefined') {
        console.error('amazon_affiliate_admin não está definido!');
        return;
    }
    
    // Verifica se ajaxurl está disponível
    if (typeof ajaxurl === 'undefined') {
        console.error('ajaxurl não está definido!');
        return;
    }
    // Adiciona funcionalidade ao botão de verificação
    $(document).on('click', '#scan-all-products', function() {
        console.log('Botão "Verificar Todos os Conteúdos" clicado');
        
        var button = $(this);
        button.prop('disabled', true).text('Verificando...');
        
        // Verifica se o elemento de resultados existe
        if ($('#scan-results').length === 0) {
            console.error('Elemento #scan-results não encontrado!');
            button.prop('disabled', false).text('Verificar Todos os Conteúdos');
            return;
        }
        
        $('#scan-results').show();
        
        console.log('Criando barra de progresso...');
        
        // Cria a barra de progresso
        var progressHtml = '<div class="amazon-scan-progress">' +
            '<h4>Verificando conteúdo...</h4>' +
            '<div class="amazon-progress-bar">' +
                '<div class="amazon-progress-fill" style="width: 0%"></div>' +
            '</div>' +
            '<div class="amazon-progress-stats">' +
                '<span class="amazon-progress-text">Iniciando verificação...</span>' +
                '<span class="amazon-progress-percentage">0%</span>' +
            '</div>' +
            '<div class="amazon-found-counter">' +
                '<strong>Conteúdos com links Amazon encontrados: <span id="found-count">0</span></strong>' +
            '</div>' +
        '</div>';
        
        $('#scan-progress').html(progressHtml);
        console.log('Barra de progresso inserida no DOM');
        
        // Verifica se a barra foi realmente criada
        if ($('.amazon-scan-progress').length > 0) {
            console.log('Barra de progresso criada com sucesso!');
        } else {
            console.error('Falha ao criar barra de progresso!');
        }
        
        // Variáveis de controle
        var totalProcessed = 0;
        var totalFound = 0;
        var estimatedTotal = 100; // Estimativa inicial
        
        // Função para atualizar a barra de progresso
        function updateProgress(processed, found, hasMore, message) {
            var previousTotal = totalFound;
            totalProcessed += processed;
            totalFound += found;
            
            // Atualiza estimativa total se ainda há mais conteúdo
            if (hasMore) {
                estimatedTotal = Math.max(estimatedTotal, totalProcessed + 50);
            } else {
                estimatedTotal = totalProcessed;
            }
            
            var percentage = hasMore ? Math.min(95, (totalProcessed / estimatedTotal) * 100) : 100;
            
            $('.amazon-progress-fill').css('width', percentage + '%');
            $('.amazon-progress-percentage').text(Math.round(percentage) + '%');
            $('.amazon-progress-text').text(message || 'Processando...');
            
            // Anima o contador se novos links foram encontrados
            if (found > 0 && totalFound > previousTotal) {
                $('.amazon-found-counter').addClass('updated');
                setTimeout(function() {
                    $('.amazon-found-counter').removeClass('updated');
                }, 500);
            }
            
            $('#found-count').text(totalFound);
        }
        
        // Chamada AJAX para verificar produtos
        checkProducts(0);
        
        function checkProducts(offset) {
            console.log('Iniciando verificação AJAX - offset:', offset);
            
            $.post(ajaxurl, {
                action: 'scan_all_amazon_products',
                offset: offset,
                nonce: amazon_affiliate_admin.nonce
            }, function(response) {
                console.log('Resposta AJAX recebida:', response);
                if (response.success) {
                    var processed = response.data.batch_size || 10;
                    var found = response.data.processed || 0;
                    
                    updateProgress(
                        processed, 
                        found, 
                        response.data.continue,
                        'Processando conteúdo... (' + totalProcessed + ' itens verificados)'
                    );
                    
                    if (response.data.continue) {
                        // Pequeno delay para visualizar o progresso
                        setTimeout(function() {
                            checkProducts(response.data.offset);
                        }, 100);
                    } else {
                        // Verificação concluída
                        updateProgress(0, 0, false, 'Verificação concluída com sucesso!');
                        
                        setTimeout(function() {
                            $('#scan-progress').append('<div class="amazon-completion-message"><p><strong>✓ Verificação concluída!</strong> Encontrados ' + totalFound + ' conteúdos com links Amazon.</p></div>');
                            button.prop('disabled', false).text('Verificar Todos os Conteúdos');
                            
                            // Recarrega a página após 3 segundos para atualizar estatísticas
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        }, 500);
                    }
                } else {
                    $('.amazon-progress-text').text('Erro durante a verificação');
                    $('#scan-progress').append('<div class="notice notice-error"><p>Erro: ' + response.data + '</p></div>');
                    button.prop('disabled', false).text('Verificar Todos os Conteúdos');
                }
            }).fail(function() {
                $('.amazon-progress-text').text('Erro de conexão');
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
    
    // Forçar limpeza de cache
    $('#force-cache-clear').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('Isto irá forçar o recarregamento de todos os scripts. Deseja continuar?')) {
            // Força limpeza de cache adicionando timestamp
            var timestamp = new Date().getTime();
            window.location.href = window.location.href + '&cache_clear=' + timestamp;
        }
    });
});

// Adiciona listener para quando a página estiver totalmente carregada
jQuery(document).ready(function() {
    console.log('Amazon Popup Admin Scripts carregado - Versão 2.1');
    
    // Adiciona indicator visual de que a nova versão está carregada
    setTimeout(function() {
        if ($('#scan-all-products').length > 0) {
            $('#scan-all-products').attr('title', 'Plugin Amazon v2.1 - Nova barra de progresso disponível');
            console.log('Botão encontrado e marcado com nova versão');
        } else {
            console.warn('Botão #scan-all-products não encontrado na página');
        }
    }, 1000);
});