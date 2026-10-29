{{--
    Preenchimento por CEP: ao digitar os 8 dígitos, busca o endereço e preenche logradouro, bairro, município,
    código IBGE e UF, deixando o foco no número. Número e complemento nunca são alterados.
    Só consulta quando o operador digita o CEP (abrir o cadastro para editar não sobrescreve nada).

    Uso (dentro do <form>, depois dos campos):
        @include('partials.cep-autocomplete', [
            'cep'        => '[name="cep"]',
            'logradouro' => '[name="logradouro"]',
            'numero'     => '[name="numero"]',
            'bairro'     => '[name="bairro"]',
            'municipio'  => '[name="municipio"]',
            'codigo'     => '[name="cod_municipio"]',
            'uf'         => '[name="uf"]',
        ])
--}}
@php
    $logradouro = $logradouro ?? null;
    $numero     = $numero ?? null;
    $bairro     = $bairro ?? null;
    $municipio  = $municipio ?? null;
    $codigo     = $codigo ?? null;
    $uf         = $uf ?? null;
@endphp

<script>
    (() => {
        const achar = (seletor) => (seletor ? document.querySelector(seletor) : null);

        const campos = {
            cep:        achar(@json($cep)),
            logradouro: achar(@json($logradouro)),
            numero:     achar(@json($numero)),
            bairro:     achar(@json($bairro)),
            municipio:  achar(@json($municipio)),
            codigo:     achar(@json($codigo)),
            uf:         achar(@json($uf)),
        };
        const ROTA = @json(route('cep.buscar'));

        if (!campos.cep) return;

        let ultimoConsultado = '';

        // Mensagem de status logo abaixo do campo de CEP (criada aqui, sem mexer no HTML da tela)
        const aviso = document.createElement('p');
        aviso.className = 'hidden text-xs mt-1';
        campos.cep.insertAdjacentElement('afterend', aviso);

        function mostrar(texto, tipo) {
            const cores = { info: 'text-gray-400', ok: 'text-green-600', erro: 'text-red-600' };
            aviso.textContent = texto;
            aviso.className = `text-xs mt-1 ${cores[tipo] ?? cores.info}` + (texto ? '' : ' hidden');
        }

        function definir(campo, valor) {
            if (!campo) return;
            campo.value = valor ?? '';
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        }

        async function consultar(cep) {
            mostrar('Buscando endereço...', 'info');

            try {
                const resp = await fetch(`${ROTA}?cep=${cep}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const dados = await resp.json();

                if (!resp.ok) {
                    mostrar(dados.erro || 'Não foi possível consultar o CEP.', 'erro');
                    return;
                }

                // O operador já trocou o CEP enquanto a resposta vinha: descarta
                if (campos.cep.value.replace(/\D/g, '') !== cep) return;

                definir(campos.logradouro, dados.logradouro);
                definir(campos.bairro, dados.bairro);
                definir(campos.municipio, dados.municipio);
                definir(campos.codigo, dados.cod_municipio);
                definir(campos.uf, dados.uf);

                // Avisa o autocompletar de município qual nome corresponde ao código preenchido
                campos.municipio?.dispatchEvent(new CustomEvent('municipio-definido', { detail: { nome: dados.municipio } }));

                // CEP genérico (cidade pequena) vem sem rua/bairro: o operador completa
                const faltou = [!dados.logradouro && 'logradouro', !dados.bairro && 'bairro', !dados.cod_municipio && 'código IBGE'].filter(Boolean);

                mostrar(
                    faltou.length ? `Endereço preenchido. Complete: ${faltou.join(', ')}.` : 'Endereço preenchido. Informe o número.',
                    faltou.length ? 'info' : 'ok'
                );

                (dados.logradouro ? campos.numero : campos.logradouro)?.focus();
            } catch (e) {
                mostrar('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.', 'erro');
            }
        }

        campos.cep.setAttribute('inputmode', 'numeric');

        campos.cep.addEventListener('input', () => {
            const digitos = campos.cep.value.replace(/\D/g, '').slice(0, 8);
            campos.cep.value = digitos.length > 5 ? `${digitos.slice(0, 5)}-${digitos.slice(5)}` : digitos;

            if (digitos.length < 8) {
                ultimoConsultado = '';
                mostrar('', 'info');
                return;
            }

            if (digitos !== ultimoConsultado) {
                ultimoConsultado = digitos;
                consultar(digitos);
            }
        });
    })();
</script>
