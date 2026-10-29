{{--
    Autocompletar de município: ao digitar o nome, lista municípios (nome/UF/código IBGE) e, ao escolher,
    preenche o código IBGE e a UF nos campos informados. Não altera o HTML dos campos.

    Uso (dentro do <form>, depois dos campos):
        @include('partials.municipio-autocomplete', [
            'municipio' => '[name="municipio"]',        // seletor CSS do campo do nome
            'codigo'    => '[name="cod_municipio"]',    // seletor CSS do campo do código IBGE
            'uf'        => '[name="uf"]',               // seletor CSS da UF (opcional)
            'exigir'    => true,                        // bloqueia o envio com município sem código (padrão: true)
        ])
--}}
@php
    $uf     = $uf ?? null;
    $exigir = $exigir ?? true;
@endphp

<script>
    (() => {
        const campoMun = document.querySelector(@json($municipio));
        const campoCod = document.querySelector(@json($codigo));
        const campoUf  = @json($uf) ? document.querySelector(@json($uf)) : null;
        const ROTA     = @json(route('municipios.buscar'));
        const EXIGIR   = @json((bool) $exigir);

        if (!campoMun || !campoCod) return;

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        let itens = [];
        let ativo = -1;
        let nomeEscolhido = campoMun.value.trim(); // nome que corresponde ao código que está no campo
        let timeout;

        campoMun.setAttribute('autocomplete', 'off');

        // Outro script (ex.: preenchimento por CEP) definiu município e código juntos
        campoMun.addEventListener('municipio-definido', (e) => { nomeEscolhido = e.detail.nome; });

        // A lista fica no <body> com posição fixa, então não depende do HTML ao redor do campo
        const lista = document.createElement('div');
        lista.className = 'hidden fixed z-50 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto';
        document.body.appendChild(lista);

        function posicionar() {
            const r = campoMun.getBoundingClientRect();
            lista.style.top = `${r.bottom + 4}px`;
            lista.style.left = `${r.left}px`;
            lista.style.width = `${Math.max(r.width, 280)}px`;
        }

        function fechar() {
            lista.classList.add('hidden');
            ativo = -1;
        }

        function desenhar() {
            lista.innerHTML = itens.length
                ? itens.map((m, i) => `
                    <button type="button" data-i="${i}"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 border-b border-gray-100 last:border-0 ${i === ativo ? 'bg-gray-100' : 'hover:bg-gray-50'}">
                        ${esc(m.nome)}<span class="text-gray-400">/${esc(m.uf)}</span>
                        <span class="text-xs text-gray-400 font-mono ml-1">${esc(m.codigo_ibge)}</span>
                    </button>`).join('')
                : '<p class="px-3 py-2 text-sm text-gray-400">Nenhum município encontrado.</p>';

            posicionar();
            lista.classList.remove('hidden');
        }

        function escolher(m) {
            campoMun.value = m.nome;
            campoCod.value = m.codigo_ibge;

            if (campoUf) {
                campoUf.value = m.uf;
                campoUf.dispatchEvent(new Event('change', { bubbles: true }));
            }

            nomeEscolhido = m.nome;
            fechar();
        }

        async function buscar() {
            const termo = campoMun.value.trim();

            if (termo.length < 2) { itens = []; fechar(); return; }

            const params = new URLSearchParams({ termo });
            if (campoUf && campoUf.value) params.set('uf', campoUf.value);

            const resp = await fetch(`${ROTA}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            itens = await resp.json();
            ativo = itens.length ? 0 : -1;
            desenhar();
        }

        campoMun.addEventListener('input', () => {
            // Mudou o nome sem escolher na lista: o código antigo não vale mais
            if (campoMun.value.trim() !== nomeEscolhido) campoCod.value = '';

            clearTimeout(timeout);
            timeout = setTimeout(buscar, 250);
        });

        campoMun.addEventListener('keydown', (e) => {
            const aberta = !lista.classList.contains('hidden');

            if (e.key === 'ArrowDown' && aberta && itens.length) {
                e.preventDefault();
                ativo = (ativo + 1) % itens.length;
                desenhar();
            } else if (e.key === 'ArrowUp' && aberta && itens.length) {
                e.preventDefault();
                ativo = (ativo - 1 + itens.length) % itens.length;
                desenhar();
            } else if (e.key === 'Enter' && aberta && ativo >= 0) {
                e.preventDefault();
                escolher(itens[ativo]);
            } else if (e.key === 'Escape') {
                fechar();
            }
        });

        campoMun.addEventListener('blur', () => setTimeout(fechar, 150));

        // mousedown (e não click) para o campo não perder o foco antes da escolha
        lista.addEventListener('mousedown', (e) => {
            const botao = e.target.closest('button[data-i]');
            if (!botao) return;
            e.preventDefault();
            escolher(itens[botao.dataset.i]);
        });

        window.addEventListener('scroll', fechar, true);
        window.addEventListener('resize', fechar);

        const formulario = campoMun.closest('form');
        if (EXIGIR && formulario) {
            formulario.addEventListener('submit', (e) => {
                if (campoMun.value.trim() !== '' && campoCod.value.trim() === '') {
                    e.preventDefault();
                    campoMun.focus();
                    alert('Selecione o município na lista para preencher o código IBGE.');
                }
            });
        }
    })();
</script>