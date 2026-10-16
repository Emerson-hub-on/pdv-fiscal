/**
 * Imprime o cupom fiscal (DANFE NFC-e) de uma venda sem sair da tela atual.
 * 1) confere se o cupom pode ser gerado (se não, rejeita com a mensagem do servidor);
 * 2) carrega a página do cupom num iframe invisível, que abre a impressão sozinho (?auto=1);
 * 3) a Promise só termina quando a impressão acaba (ou é cancelada) — assim a tela
 *    não troca de página no meio da impressão.
 * Uso: await imprimirCupomFiscal(uuidDaVenda)
 */
window.imprimirCupomFiscal = async function (uuid) {
    const url = '/pdv/venda/' + encodeURIComponent(uuid) + '/cupom-fiscal';

    const pre = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!pre.ok) {
        const json = await pre.json().catch(() => ({}));
        throw new Error(json.message || ('Erro ' + pre.status + ' ao gerar o cupom.'));
    }

    return new Promise((resolve) => {
        document.getElementById('iframe-cupom-fiscal')?.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'iframe-cupom-fiscal';
        iframe.src = url + '?auto=1';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0;pointer-events:none;';

        const encerrar = () => {
            window.removeEventListener('message', ouvir);
            clearTimeout(limite);
            resolve();
        };

        // O cupom avisa (postMessage) quando a impressão termina ou é cancelada
        const ouvir = (ev) => {
            if (ev.origin === window.location.origin && ev.data && ev.data.tipo === 'cupom-fiscal-impresso') {
                encerrar();
            }
        };

        window.addEventListener('message', ouvir);
        const limite = setTimeout(encerrar, 120000); // rede de segurança

        document.body.appendChild(iframe);
    });
};