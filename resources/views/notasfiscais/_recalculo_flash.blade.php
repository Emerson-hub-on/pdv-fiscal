@if (session('recalculo_alteracoes'))
    <div class="bg-blue-50 text-blue-900 border border-blue-200 rounded-lg px-4 py-3 mb-4">
        <p class="font-medium text-sm mb-2">Dados fiscais atualizados:</p>
        <ul class="text-sm space-y-1">
            @foreach (session('recalculo_alteracoes') as $alteracao)
                <li>
                    <strong>{{ $alteracao['produto'] }}</strong>
                    <ul class="list-disc list-inside pl-3 text-blue-800">
                        @foreach ($alteracao['mudancas'] as $mudanca)
                            <li>{{ $mudanca }}</li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </div>
@endif