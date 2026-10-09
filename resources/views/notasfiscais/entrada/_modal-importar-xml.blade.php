<div id="modal-importar-xml" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Importar XML da NF-e</h2>
            <button type="button" onclick="fecharModalXml()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <form id="form-importar-xml" method="POST" action="{{ route('entradas-nota.importar-xml') }}" enctype="multipart/form-data">
            @csrf

            <div id="dropzone-xml"
                 class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">
                <p class="text-sm text-gray-600">Arraste o XML aqui ou <span class="text-blue-600 font-medium">clique para procurar</span></p>
                <p id="nome-arquivo-xml" class="text-xs text-gray-500 mt-2"></p>
                <input type="file" name="xml" id="input-xml" accept=".xml,text/xml,application/xml" class="hidden">
            </div>

            <p class="text-xs text-gray-400 mt-3">
                Se o fornecedor da nota ainda não estiver cadastrado, ele será cadastrado automaticamente com os dados do XML.
            </p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="fecharModalXml()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancelar</button>
                <button type="submit" id="btn-enviar-xml" disabled
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Importar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const modalXml = document.getElementById('modal-importar-xml');
    const dropzoneXml = document.getElementById('dropzone-xml');
    const inputXml = document.getElementById('input-xml');
    const nomeXml = document.getElementById('nome-arquivo-xml');
    const btnXml = document.getElementById('btn-enviar-xml');

    function abrirModalXml() { modalXml.classList.remove('hidden'); }
    function fecharModalXml() { modalXml.classList.add('hidden'); }

    function atualizarArquivoXml() {
        const arquivo = inputXml.files[0];

        if (!arquivo) {
            nomeXml.textContent = '';
            btnXml.disabled = true;
            return;
        }

        if (!arquivo.name.toLowerCase().endsWith('.xml')) {
            nomeXml.textContent = 'Selecione um arquivo .xml';
            inputXml.value = '';
            btnXml.disabled = true;
            return;
        }

        nomeXml.textContent = arquivo.name;
        btnXml.disabled = false;
    }

    inputXml.addEventListener('click', (e) => e.stopPropagation());
    inputXml.addEventListener('change', atualizarArquivoXml);
    dropzoneXml.addEventListener('click', () => inputXml.click());

    ['dragenter', 'dragover'].forEach(ev => dropzoneXml.addEventListener(ev, (e) => {
        e.preventDefault();
        dropzoneXml.classList.add('border-blue-500', 'bg-blue-50');
    }));

    ['dragleave', 'drop'].forEach(ev => dropzoneXml.addEventListener(ev, (e) => {
        e.preventDefault();
        dropzoneXml.classList.remove('border-blue-500', 'bg-blue-50');
    }));

    dropzoneXml.addEventListener('drop', (e) => {
        if (e.dataTransfer.files.length) {
            inputXml.files = e.dataTransfer.files;
            atualizarArquivoXml();
        }
    });

    document.getElementById('form-importar-xml').addEventListener('submit', () => {
        btnXml.disabled = true;
        btnXml.textContent = 'Importando...';
    });
</script>