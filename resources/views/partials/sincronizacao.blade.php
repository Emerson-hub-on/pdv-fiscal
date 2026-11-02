{{-- Botão "Atualizar Caixa": modal com o andamento e o resultado da sincronização (caixa e painel) --}}
<div id="modal-sinc" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4"
     role="dialog" aria-modal="true" aria-labelledby="sinc-titulo">
    <div class="w-full max-w-lg max-h-[85vh] flex flex-col rounded-2xl bg-white text-gray-800 shadow-xl">
        <div id="sinc-carregando" class="px-6 py-10 text-center">
            <div class="mx-auto h-12 w-12 rounded-full border-4 border-blue-100 border-t-[#163f86] animate-spin" aria-hidden="true"></div>
            <h3 class="mt-5 text-base font-semibold text-gray-900">Sincronizando com o servidor...</h3>
            <p class="mt-1 text-sm text-gray-500">Enviando as vendas e atualizando produtos, clientes e usuários</p>
        </div>

        <div id="sinc-resultado" class="hidden flex-col min-h-0">
            <div class="flex items-start gap-3 px-6 pt-6 pb-4 border-b border-gray-100">
                <span id="sinc-icone" class="h-10 w-10 shrink-0 rounded-full flex items-center justify-center text-lg font-bold"></span>
                <div>
                    <h3 id="sinc-titulo" class="text-base font-semibold text-gray-900"></h3>
                    <p id="sinc-subtitulo" class="text-sm text-gray-500"></p>
                </div>
            </div>
            <div id="sinc-corpo" class="px-6 py-4 space-y-4 overflow-y-auto text-sm"></div>
            <div class="px-6 py-4 border-t border-gray-100 text-right">
                <button type="button" id="sinc-ok" onclick="fecharModalSinc()"
                        class="rounded-lg bg-[#163f86] px-5 py-2 text-sm font-semibold text-white hover:bg-[#12346f] focus:outline-none focus:ring-2 focus:ring-blue-500">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const modalSinc = document.getElementById('modal-sinc');

function fecharModalSinc() {
    modalSinc.classList.add('hidden');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.getElementById('sinc-resultado').classList.contains('hidden')) fecharModalSinc();
});

// Elemento com texto (nunca innerHTML com mensagens vindas do servidor)
function elSinc(tag, classes, texto) {
    const el = document.createElement(tag);
    if (classes) el.className = classes;
    if (texto !== undefined && texto !== null) el.textContent = texto;
    return el;
}

function secaoSinc(titulo) {
    const secao = elSinc('div');
    secao.appendChild(elSinc('h4', 'text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2', titulo));
    document.getElementById('sinc-corpo').appendChild(secao);
    return secao;
}

// Cartão de erro: título, motivo legível e o texto técnico original (quando diferente)
function cartaoErroSinc(titulo, motivo, detalhe) {
    const cartao = elSinc('div', 'rounded-lg border border-red-200 bg-red-50 px-3 py-2');
    cartao.appendChild(elSinc('p', 'font-medium text-red-800', titulo));
    cartao.appendChild(elSinc('p', 'text-red-700', motivo));
    if (detalhe && detalhe !== motivo) {
        cartao.appendChild(elSinc('p', 'mt-1 font-mono text-[11px] text-red-500 break-all', detalhe));
    }
    return cartao;
}

function cabecalhoSinc(tipo, titulo, subtitulo) {
    const icone = document.getElementById('sinc-icone');
    const estilos = {
        ok:    ['bg-emerald-100 text-emerald-700', '✓'],
        aviso: ['bg-amber-100 text-amber-700', '!'],
        erro:  ['bg-red-100 text-red-700', '✕'],
    };
    icone.className = 'h-10 w-10 shrink-0 rounded-full flex items-center justify-center text-lg font-bold ' + estilos[tipo][0];
    icone.textContent = estilos[tipo][1];
    document.getElementById('sinc-titulo').textContent = titulo;
    document.getElementById('sinc-subtitulo').textContent = subtitulo;
}

function mostrarResultadoSinc() {
    document.getElementById('sinc-carregando').classList.add('hidden');
    const resultado = document.getElementById('sinc-resultado');
    resultado.classList.remove('hidden');
    resultado.classList.add('flex');
    document.getElementById('sinc-ok').focus();
}

function plural(n, singular, varios) {
    return `${n} ${n === 1 ? singular : varios}`;
}

function preencherResultadoSinc(r) {
    const vendasErro = r.vendas_com_erro || [];
    const caixasErro = r.caixas_com_erro || [];
    const clientesErro = r.clientes_com_erro || [];
    const partesErro = r.partes_com_erro || [];

    if (r.servidor_fora) {
        cabecalhoSinc('erro', 'Servidor indisponível',
            'Não foi possível falar com o servidor. O caixa continua vendendo em contingência.');
    } else if (vendasErro.length || caixasErro.length || clientesErro.length || partesErro.length) {
        cabecalhoSinc('aviso', 'Sincronização com pendências', 'Parte das informações não foi enviada. Veja os motivos abaixo.');
    } else {
        cabecalhoSinc('ok', 'Sincronização concluída', 'Tudo foi enviado e atualizado com o servidor.');
    }

    // Vendas
    const vendas = secaoSinc('Vendas');
    if (r.vendas_enviadas > 0) {
        vendas.appendChild(elSinc('p', 'text-emerald-700',
            '✓ ' + plural(r.vendas_enviadas, 'venda enviada', 'vendas enviadas') + ' ao servidor.'));
    }
    if (r.vendas_aguardando > 0) {
        vendas.appendChild(elSinc('p', 'text-amber-700', plural(r.vendas_aguardando, 'venda aguardando', 'vendas aguardando')
            + ' envio. Elas estão guardadas neste caixa e sobem sozinhas quando o servidor responder.'));
    }
    if (vendasErro.length) {
        vendas.appendChild(elSinc('p', 'text-red-700 mb-2', plural(vendasErro.length, 'venda não enviada', 'vendas não enviadas') + ':'));
        const lista = elSinc('div', 'space-y-2');
        vendasErro.forEach(v => {
            const nota = v.numero_nfce ? `NFC-e nº ${v.numero_nfce} (série ${v.serie_nfce})` : 'Venda sem NFC-e';
            lista.appendChild(cartaoErroSinc(`${nota} · ${v.vendida_em ?? ''} · ${v.total}`, v.motivo, v.detalhe));
        });
        vendas.appendChild(lista);
    }
    if (!r.vendas_enviadas && !r.vendas_aguardando && !vendasErro.length) {
        vendas.appendChild(elSinc('p', 'text-gray-600', 'Nenhuma venda pendente de envio.'));
    }

    // O que veio do servidor
    if (!r.servidor_fora) {
        const recebido = secaoSinc('Atualizado do servidor');
        recebido.appendChild(elSinc('p', 'text-gray-600',
            `Produtos: ${r.produtos_atualizados} · Usuários: ${r.usuarios_atualizados}`));
    }

    // Outros erros
    if (caixasErro.length || clientesErro.length || partesErro.length) {
        const outros = secaoSinc('Outros problemas');
        const lista = elSinc('div', 'space-y-2');
        caixasErro.forEach(c => lista.appendChild(cartaoErroSinc(`Caixa aberto em ${c.aberto_em ?? ''}`, c.motivo, c.detalhe)));
        clientesErro.forEach(c => lista.appendChild(cartaoErroSinc(`Cliente cadastrado no caixa: ${c.nome} (${c.cpf_cnpj})`, c.motivo, c.detalhe)));
        partesErro.forEach(p => lista.appendChild(cartaoErroSinc(p.parte, p.motivo, p.detalhe)));
        outros.appendChild(lista);
    }
}

async function sincronizarAgora() {
    const btn = document.getElementById('btn-sincronizar');
    const textoOriginal = btn.innerText;

    btn.disabled = true;
    btn.innerText = 'Sincronizando...';

    document.getElementById('sinc-corpo').replaceChildren();
    document.getElementById('sinc-resultado').classList.add('hidden');
    document.getElementById('sinc-resultado').classList.remove('flex');
    document.getElementById('sinc-carregando').classList.remove('hidden');
    modalSinc.classList.remove('hidden');

    try {
        const resp = await fetch('{{ route("sincronizacao.executar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
        });

        if (resp.status === 419) {
            cabecalhoSinc('erro', 'Sessão expirada', 'Recarregue a página (F5) e tente de novo.');
        } else {
            const resultado = await resp.json();

            if (resultado.sucesso) {
                preencherResultadoSinc(resultado);
            } else {
                cabecalhoSinc('erro', 'Erro ao sincronizar', 'A sincronização parou no meio.');
                secaoSinc('Motivo').appendChild(elSinc('p', 'text-red-700', resultado.erro || 'Erro sem mensagem.'));
            }
        }
    } catch (e) {
        cabecalhoSinc('erro', 'Erro de conexão', 'Este computador não conseguiu completar a sincronização. Tente de novo em instantes.');
    }

    mostrarResultadoSinc();
    btn.disabled = false;
    btn.innerText = textoOriginal;
}
</script>
