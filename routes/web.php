<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\VendaController;
use App\Http\Controllers\FiscalController;
use App\Http\Controllers\PdvController;
use App\Http\Controllers\ContingenciaController;
use App\Services\FiscalEmissorService;
use App\Http\Controllers\InutilizacaoController;
use App\Http\Controllers\CancelamentoController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SincronizacaoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\NcmController;
use App\Http\Controllers\TributacaoController;
use App\Http\Controllers\CestController;
use App\Http\Controllers\ClassificacaoTributariaController;
use App\Http\Controllers\ClassificacaoPisCofinsController;
use App\Http\Controllers\ClassificacaoIpiController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\NotaFiscalController;
use App\Http\Controllers\CfopSaidaController;
use App\Http\Controllers\FormaPagamentoController;
use App\Http\Controllers\SerieNfeController;
use App\Http\Controllers\InutilizacaoNfeController;
use App\Http\Controllers\TransportadorController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PdvEmissaoController;
use App\Http\Controllers\EntradaNotaController;
use App\Http\Controllers\FornecedorController;



Route::get('/', [AuthController::class, 'tela'])->name('auth.escolha');
Route::get('/login', [AuthController::class, 'formulario'])->name('auth.login');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('login/usuario', [AuthController::class, 'nomePorCodigo'])
    ->middleware('throttle:60,1')
    ->name('auth.usuario');


    // =====================================================================
    // CAIXA — Operador de Caixa (e admin)
    // =====================================================================
    Route::middleware(['auth:caixa', 'acesso:caixa'])->group(function () {
        Route::get('caixa/abrir', [CaixaController::class, 'abrirForm'])->name('caixa.abrir-form');
        Route::post('caixa/abrir', [CaixaController::class, 'abrir'])->name('caixa.abrir');
        Route::get('caixa/fechar', [CaixaController::class, 'fecharForm'])->name('caixa.fechar-form');
        Route::post('caixa/fechar', [CaixaController::class, 'fechar'])->name('caixa.fechar');

        Route::get('pdv', [VendaController::class, 'pdv'])->name('vendas.pdv');
        Route::get('pdv/buscar-produto', [VendaController::class, 'buscarProduto'])->name('vendas.buscar-produto');
        Route::post('pdv/finalizar', [VendaController::class, 'finalizar'])->name('vendas.finalizar');
        Route::post('pdv/pagamento/preparar', [VendaController::class, 'prepararPagamento'])->name('vendas.preparar-pagamento');
        Route::get('pdv/pagamento', [VendaController::class, 'telaPagamento'])->name('vendas.pagamento');
        Route::get('pdv/venda/{uuid}/comprovante', [FiscalController::class, 'comprovante'])->name('vendas.comprovante');
        Route::post('pdv/venda/{uuid}/emitir', [FiscalController::class, 'emitir'])->name('vendas.emitir');
        Route::post('pdv/limpar-sessao', [VendaController::class, 'limparSessaoCarrinho'])->name('vendas.limpar-sessao');

        Route::get('contingencias', [ContingenciaController::class, 'listar'])->name('contingencias.listar');
        Route::post('contingencias/reenviar', [ContingenciaController::class, 'reenviar'])->name('contingencias.reenviar');
        Route::post('contingencias/{venda}/reenviar', [ContingenciaController::class, 'reenviar'])->name('contingencias.reenviar');
        Route::post('inutilizacao', [InutilizacaoController::class, 'executar'])->name('inutilizacao.executar');
        Route::get('cancelamento/listar', [CancelamentoController::class, 'listar'])->name('cancelamento.listar');
        Route::post('cancelamento/{venda}/cancelar', [CancelamentoController::class, 'cancelar'])->name('cancelamento.cancelar');
        Route::post('supervisor/autorizar', [SupervisorController::class, 'autorizar'])
            ->middleware('throttle:10,1')
            ->name('supervisor.autorizar');
        Route::get('supervisor/usuario', [SupervisorController::class, 'nomePorCodigo'])
            ->middleware('throttle:60,1')
            ->name('supervisor.usuario');

        // Endpoints JSON usados pelo modal "Adicionar consumidor" no caixa
        Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
        Route::post('/clientes/criar-rapido', [ClienteController::class, 'criarRapido'])->name('clientes.criarRapido');
    });

    // =====================================================================
    // USADAS PELOS DOIS SISTEMAS (caixa e cadastros)
    // =====================================================================
    Route::middleware(['auth:web,caixa', 'acesso:caixa,fiscal'])->group(function () {
        Route::post('sincronizar-agora', [SincronizacaoController::class, 'executar'])->name('sincronizacao.executar');
        Route::get('/api/consulta-cnpj/{cnpj}', [ClienteController::class, 'consultarCnpj'])
            ->name('clientes.consultarCnpj');
    });

    // =====================================================================
    // SISTEMA DE CADASTROS E FATURAMENTO — Operador Fiscal (e admin)
    // =====================================================================
    Route::middleware(['auth:web', 'acesso:fiscal'])->group(function () {

        // ---------------- PRODUTOS E CLASSIFICAÇÕES FISCAIS ----------------
        Route::middleware('permissao:produtos')->group(function () {
            Route::resource('produtos', ProdutoController::class)->except(['destroy', 'show']);
            Route::patch('produtos/{produto}/toggle-ativo', [ProdutoController::class, 'toggleAtivo'])
                ->name('produtos.toggle-ativo');
            Route::get('/produtos/verificar-codigo-barras', [ProdutoController::class, 'verificarCodigoBarras'])
                ->name('produtos.verificarCodigoBarras');

            Route::get('catalogo/listar', [CatalogoController::class, 'listar'])->name('catalogo.listar');
            Route::post('catalogo/criar', [CatalogoController::class, 'criar'])->name('catalogo.criar');
            Route::post('catalogo/editar', [CatalogoController::class, 'editar'])->name('catalogo.editar');
            Route::post('catalogo/excluir', [CatalogoController::class, 'excluir'])->name('catalogo.excluir');
            Route::get('ncm/listar', [NcmController::class, 'listar'])->name('ncm.listar');
            Route::post('ncm/criar', [NcmController::class, 'criar'])->name('ncm.criar');
            Route::post('ncm/editar', [NcmController::class, 'editar'])->name('ncm.editar');
            Route::post('ncm/excluir', [NcmController::class, 'excluir'])->name('ncm.excluir');
            Route::get('tributacao/listar', [TributacaoController::class, 'listar'])->name('tributacao.listar');
            Route::get('/cests/buscar', [CestController::class, 'buscar'])->name('cests.buscar');
            Route::get('/cest/listar', [CestController::class, 'listar'])->name('cest.listar');
            Route::post('/cest/criar', [CestController::class, 'criar'])->name('cest.criar');
            Route::post('/cest/editar', [CestController::class, 'editar'])->name('cest.editar');
            Route::post('/cest/excluir', [CestController::class, 'excluir'])->name('cest.excluir');
            Route::get('/classificacao-tributaria/listar', [ClassificacaoTributariaController::class, 'listar'])->name('classtrib.listar');
            Route::post('/classificacao-tributaria/criar', [ClassificacaoTributariaController::class, 'criar'])->name('classtrib.criar');
            Route::post('/classificacao-tributaria/editar', [ClassificacaoTributariaController::class, 'editar'])->name('classtrib.editar');
            Route::post('/classificacao-tributaria/excluir', [ClassificacaoTributariaController::class, 'excluir'])->name('classtrib.excluir');
            Route::get('/pis-cofins/listar', [ClassificacaoPisCofinsController::class, 'listar'])->name('piscofins.listar');
            Route::post('/pis-cofins/criar', [ClassificacaoPisCofinsController::class, 'criar'])->name('piscofins.criar');
            Route::post('/pis-cofins/editar', [ClassificacaoPisCofinsController::class, 'editar'])->name('piscofins.editar');
            Route::post('/pis-cofins/excluir', [ClassificacaoPisCofinsController::class, 'excluir'])->name('piscofins.excluir');
            Route::get('/ipi/listar', [ClassificacaoIpiController::class, 'listar'])->name('ipi.listar');
            Route::post('/ipi/criar', [ClassificacaoIpiController::class, 'criar'])->name('ipi.criar');
            Route::post('/ipi/editar', [ClassificacaoIpiController::class, 'editar'])->name('ipi.editar');
            Route::post('/ipi/excluir', [ClassificacaoIpiController::class, 'excluir'])->name('ipi.excluir');
        });

        // ---------------- EMPRESA ----------------
        Route::middleware('permissao:empresa')->group(function () {
            Route::get('empresa', [EmpresaController::class, 'editar'])->name('empresa.editar');
            Route::post('empresa', [EmpresaController::class, 'salvar'])->name('empresa.salvar');
        });

        // ---------------- PDVS ----------------
        Route::middleware('permissao:pdvs')->group(function () {
            Route::resource('pdvs', PdvController::class)->except(['destroy', 'show']);
            Route::patch('pdvs/{pdv}/toggle-ativo', [PdvController::class, 'toggleAtivo'])->name('pdvs.toggle-ativo');
        });

        // ---------------- CLIENTES E FORNECEDORES ----------------
        Route::middleware('permissao:clientes')->group(function () {
            Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
            Route::get('/clientes/criar', [ClienteController::class, 'create'])->name('clientes.create');
            Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
            Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
            Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
            Route::post('/clientes/{cliente}/toggle-ativo', [ClienteController::class, 'toggleAtivo'])->name('clientes.toggleAtivo');

            // Rotas de Fornecedores
            Route::get('fornecedores', [FornecedorController::class, 'index'])->name('fornecedores.index');
            Route::get('fornecedores/criar', [FornecedorController::class, 'create'])->name('fornecedores.create');
            Route::post('fornecedores/criar-rapido', [FornecedorController::class, 'criarRapido'])->name('fornecedores.rapido');
            Route::post('fornecedores', [FornecedorController::class, 'store'])->name('fornecedores.store');
            Route::get('fornecedores/{fornecedor}/editar', [FornecedorController::class, 'edit'])->name('fornecedores.edit');
            Route::put('fornecedores/{fornecedor}', [FornecedorController::class, 'update'])->name('fornecedores.update');
            Route::post('fornecedores/{fornecedor}/toggle-ativo', [FornecedorController::class, 'toggleAtivo'])->name('fornecedores.toggleAtivo');
        });

        // ---------------- NOTA FISCAL - SAÍDA (NF-e modelo 55) ----------------
        Route::middleware('permissao:notas')->group(function () {

            // ---------------- FATURAMENTO - ENTRADAS DE NOTA ----------------
            Route::prefix('faturamento/entradas-nota')->name('entradas-nota.')->group(function () {
                Route::get('produtos', [EntradaNotaController::class, 'buscarProdutos'])->name('produtos');
                Route::post('importar-xml/analisar', [EntradaNotaController::class, 'analisarXml'])->name('importar-xml.analisar');
                Route::post('importar-xml/produto', [ProdutoController::class, 'storeJson'])->middleware('permissao:produtos')->name('importar-xml.produto');
                Route::post('importar-xml/confirmar', [EntradaNotaController::class, 'confirmarImportacaoXml'])->name('importar-xml.confirmar');

                Route::get('/', [EntradaNotaController::class, 'index'])->name('index');
                Route::get('criar', [EntradaNotaController::class, 'create'])->name('create');
                Route::post('/', [EntradaNotaController::class, 'store'])->name('store');
                Route::get('{entrada}/editar', [EntradaNotaController::class, 'edit'])->name('edit');
                Route::put('{entrada}', [EntradaNotaController::class, 'update'])->name('update');
                Route::delete('{entrada}', [EntradaNotaController::class, 'destroy'])->name('destroy');
            });

            // IMPORTANTE: as buscas precisam vir ANTES do Route::resource, senão
            // "notasfiscais/buscar-produto" é capturado por "notasfiscais/{notaFiscal}".
            Route::get('notasfiscais/buscar-produto', [NotaFiscalController::class, 'buscarProduto'])
                ->name('notasfiscais.buscar-produto');
            Route::get('notasfiscais/buscar-cliente', [NotaFiscalController::class, 'buscarCliente'])
                ->name('notasfiscais.buscar-cliente');
            Route::get('notasfiscais/buscar-operador', [NotaFiscalController::class, 'buscarOperador'])
                ->name('notasfiscais.buscar-operador');
            Route::post('notasfiscais/autorizar-operador', [NotaFiscalController::class, 'autorizarOperador'])
                ->middleware('throttle:10,1') // evita tentativa em massa de senha
                ->name('notasfiscais.autorizar-operador');
            Route::resource('notasfiscais', NotaFiscalController::class)
                ->parameters(['notasfiscais' => 'notaFiscal']);
            Route::post('notasfiscais/{notaFiscal}/itens', [NotaFiscalController::class, 'adicionarItem'])
                ->name('notasfiscais.itens.adicionar');
            Route::delete('notasfiscais/{notaFiscal}/itens/{item}', [NotaFiscalController::class, 'removerItem'])
                ->name('notasfiscais.itens.remover');
            Route::post('notasfiscais/{notaFiscal}/emitir', [NotaFiscalController::class, 'emitir'])
                ->name('notasfiscais.emitir');
            Route::get('notasfiscais/{notaFiscal}/cancelar', [NotaFiscalController::class, 'formCancelar'])
                ->name('notasfiscais.cancelar-form');
            Route::post('notasfiscais/{notaFiscal}/cancelar', [NotaFiscalController::class, 'cancelar'])
                ->name('notasfiscais.cancelar');
            Route::get('notasfiscais/{notaFiscal}/danfe', [NotaFiscalController::class, 'danfe'])
                ->name('notasfiscais.danfe');
            Route::get('notasfiscais/{notaFiscal}/xml', [NotaFiscalController::class, 'xml'])
                ->name('notasfiscais.xml');
            Route::get('notasfiscais/{notaFiscal}/previsualizar', [NotaFiscalController::class, 'previsualizar'])
                ->name('notasfiscais.previsualizar');
            Route::post('notasfiscais/{notaFiscal}/recalcular', [NotaFiscalController::class, 'recalcular'])
                ->name('notasfiscais.recalcular');

            Route::get('cfop-saida/listar', [CfopSaidaController::class, 'listar'])->name('cfop-saida.listar');
            Route::post('cfop-saida/criar', [CfopSaidaController::class, 'criar'])->name('cfop-saida.criar');
            Route::post('cfop-saida/editar', [CfopSaidaController::class, 'editar'])->name('cfop-saida.editar');
            Route::get('formas-pagamento/listar', [FormaPagamentoController::class, 'listar'])->name('formas-pagamento.listar');
            Route::post('formas-pagamento/criar', [FormaPagamentoController::class, 'criar'])->name('formas-pagamento.criar');
            Route::post('formas-pagamento/editar', [FormaPagamentoController::class, 'editar'])->name('formas-pagamento.editar');
            Route::post('inutilizacao-nfe/executar', [InutilizacaoNfeController::class, 'executar'])
                ->name('inutilizacao-nfe.executar');

            // Buscas usadas dentro do modal "Confirmar Nota Fiscal" (só leitura)
            Route::get('transportadores/listar', [TransportadorController::class, 'listar'])->name('transportadores.listar');
            Route::get('veiculos/listar', [VeiculoController::class, 'listar'])->name('veiculos.listar');
        });

        // ---------------- NUMERAÇÃO DE NF-e ----------------
        Route::middleware('permissao:series')->group(function () {
            Route::get('series-nfe', [SerieNfeController::class, 'index'])->name('series-nfe.index');
            Route::get('series-nfe/{serieNfe}/editar', [SerieNfeController::class, 'edit'])->name('series-nfe.edit');
            Route::put('series-nfe/{serieNfe}', [SerieNfeController::class, 'update'])->name('series-nfe.update');
        });

        // ---------------- TRANSPORTADORAS E VEÍCULOS ----------------
        Route::middleware('permissao:transportadoras')->group(function () {
            Route::post('transportadores/criar', [TransportadorController::class, 'criar'])->name('transportadores.criar');
            Route::post('transportadores/editar', [TransportadorController::class, 'editar'])->name('transportadores.editar');
            Route::resource('transportadores', TransportadorController::class)
                ->except(['show', 'destroy'])
                ->parameters(['transportadores' => 'transportador']);
            Route::post('transportadores/{transportador}/toggle-ativo', [TransportadorController::class, 'toggleAtivo'])
                ->name('transportadores.toggleAtivo');

            Route::resource('veiculos', VeiculoController::class)->except(['show', 'destroy']);
            Route::post('veiculos/{veiculo}/toggle-ativo', [VeiculoController::class, 'toggleAtivo'])
                ->name('veiculos.toggleAtivo');
        });
    });

    // =====================================================================
    // EMISSÃO DE PDV — Exclusivo para Administradores
    // =====================================================================
    Route::middleware(['auth:web', 'acesso:admin'])->group(function () {
        Route::get('pdvs/{pdv}/emissao', [PdvEmissaoController::class, 'mostrar'])->name('pdvs.emissao');
        Route::post('pdvs/{pdv}/emissao/servidor', [PdvEmissaoController::class, 'passarParaServidor'])->name('pdvs.emissao.servidor');
        Route::post('pdvs/{pdv}/emissao/caixa', [PdvEmissaoController::class, 'devolverAoCaixa'])->name('pdvs.emissao.caixa');
    });

    // =====================================================================
    // ADMINISTRAÇÃO — gestão de usuários (só admin)
    // =====================================================================
     Route::middleware(['auth:web', 'acesso:admin'])
        ->prefix('usuarios')
        ->where(['perfil' => 'caixa|fiscal|supervisor'])
        ->group(function () {
            Route::get('{perfil}', [UsuarioController::class, 'index'])->name('usuarios.index');
            Route::get('{perfil}/create', [UsuarioController::class, 'create'])->name('usuarios.create');
            Route::post('{perfil}', [UsuarioController::class, 'store'])->name('usuarios.store');
            Route::get('{perfil}/{usuario}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit');
            Route::put('{perfil}/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
            Route::post('{perfil}/{usuario}/revogar', [UsuarioController::class, 'revogar'])->name('usuarios.revogar');
            Route::get('{perfil}/{usuario}/permissoes', [UsuarioController::class, 'permissoes'])->name('usuarios.permissoes');
            Route::put('{perfil}/{usuario}/permissoes', [UsuarioController::class, 'salvarPermissoes'])->name('usuarios.permissoes.salvar');
            Route::post('{perfil}/vincular', [UsuarioController::class, 'vincular'])->name('usuarios.vincular');
        
        });