{{-- Tempo desde que a mesa entrou na situação atual: todo elemento com data-desde="ISO 8601" mostra
     "há 5 min", "há 1 h 05 min"... e é atualizado a cada 15 s. A diferença do relógio do aparelho para o
     do servidor é descontada (celular com hora errada não mostra tempo errado). --}}
<script>
    window.relogioMesas = (function () {
        const diferenca = Date.now() - {{ now()->getTimestampMs() }};

        function formatar(iso) {
            if (!iso) return '';
            const minutos = Math.max(0, Math.floor((Date.now() - diferenca - new Date(iso).getTime()) / 60000));
            if (minutos < 1) return 'agora';
            if (minutos < 60) return `há ${minutos} min`;
            const horas = Math.floor(minutos / 60);
            return `há ${horas} h ${String(minutos % 60).padStart(2, '0')} min`;
        }

        function atualizar(raiz = document) {
            raiz.querySelectorAll('[data-desde]').forEach(el => {
                el.textContent = formatar(el.dataset.desde);
            });
        }

        atualizar();
        setInterval(atualizar, 15000);

        return { formatar, atualizar };
    })();
</script>
