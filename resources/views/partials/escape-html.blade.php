{{-- escHtml(valor): escapa texto antes de entrar num HTML montado pelo JavaScript (innerHTML, template string).
     Use em TODO dado que veio do banco ou do usuário (nome de produto, descrição, motivo...): sem isso,
     um nome como <img onerror=...> executa no navegador de quem abre a tela (XSS).
     Fica no <head>, antes dos scripts das telas, para estar disponível desde o começo. --}}
<script>
    window.escHtml = function (valor) {
        return String(valor ?? '').replace(/[&<>"'`]/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;',
        })[c]);
    };

    // jsArg(valor): texto como argumento de função dentro de um atributo HTML, ex.: onclick="abrir(${jsArg(nome)})".
    // Vira string JavaScript (JSON, com aspas e barras tratadas) e depois é escapado para o atributo.
    window.jsArg = function (valor) {
        return window.escHtml(JSON.stringify(String(valor ?? '')));
    };
</script>
