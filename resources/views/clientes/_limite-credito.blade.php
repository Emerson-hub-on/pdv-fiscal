@php
    extract(config('estilo.formulario'));

    // Aba "Limite de crédito" do cadastro do cliente: convênio (venda fiado) concedido manualmente.
    // O uso do limite são as contas a receber de convênio em aberto (vendas do caixa pagas no convênio).
    $cliente = $cliente ?? null;
    $existe = (bool) $cliente?->exists;
    $resumoConvenio = $existe
        ? app(\App\Services\ConvenioService::class)->resumo($cliente)
        : ['ativo' => false, 'limite' => 0, 'usado' => 0, 'disponivel' => 0, 'dias' => 30];

    $contasConvenio = $existe
        ? \App\Models\ContaReceber::with(['parcelas', 'venda:id,numero_nfce,serie_nfce,created_at'])
            ->where('cliente_id', $cliente->id)->where('origem', 'convenio')
            ->orderByRaw("status = 'aberta' desc")->orderByDesc('data_conta')->orderByDesc('id')
            ->limit(50)->get()
        : collect();

    $historicoLimite = $existe
        ? $cliente->historicoLimiteCredito()->with('usuario:id,name')->limit(20)->get()
        : collect();

    $ativoForm = (bool) old('limite_credito_ativo', $cliente->limite_credito_ativo ?? false);
    $moedaLc = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $percentualUso = $resumoConvenio['limite'] > 0 ? min(100, round($resumoConvenio['usado'] / $resumoConvenio['limite'] * 100)) : 0;
    $abaComErro = $errors->hasAny(['limite_credito', 'limite_credito_dias', 'limite_credito_observacao', 'limite_credito_ativo']);
@endphp

<input type="hidden" name="limite_credito_form" value="1">

<div class="flex flex-col gap-6">
    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
        <strong>Convênio (venda fiado).</strong> Com o limite concedido, o caixa aceita a forma de pagamento
        <strong>Convênio</strong> para este cliente até o valor disponível. Cada venda vira uma conta a receber,
        baixada em <em>Financeiro → Contas a receber</em>; quando ela é baixada, o limite volta a ficar disponível.
        É diferente do crédito em loja, que nasce de uma devolução.
    </div>

    <div class="grid grid-cols-2 gap-5">
        <div class="col-span-2">
            <input type="hidden" name="limite_credito_ativo" value="0">
            <label class="inline-flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="limite_credito_ativo" value="1" id="limite-credito-ativo"
                       class="w-4 h-4 rounded border-slate-300 text-[#10284A] focus:ring-[#10284A]/30"
                       onchange="alternarLimiteCredito()" @checked($ativoForm)>
                <span class="text-sm font-semibold text-slate-800">Conceder limite de crédito (convênio) a este cliente</span>
            </label>
        </div>

        <div>
            <label class="{{ $labelCls }}">Limite total (R$) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0" name="limite_credito" id="limite-credito-valor"
                   value="{{ old('limite_credito', $cliente?->limite_credito !== null ? number_format((float) $cliente->limite_credito, 2, '.', '') : '') }}"
                   placeholder="0,00" class="{{ $inputCls }} campo-limite-credito">
            @error('limite_credito') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelCls }}">Prazo para pagar (dias) <span class="text-red-500">*</span></label>
            <input type="number" min="1" max="365" name="limite_credito_dias"
                   value="{{ old('limite_credito_dias', $cliente->limite_credito_dias ?? 30) }}"
                   class="{{ $inputCls }} campo-limite-credito">
            <p class="text-xs text-slate-400 mt-1">Vencimento da conta a receber gerada por cada venda no convênio.</p>
            @error('limite_credito_dias') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>

        <div class="col-span-2">
            <label class="{{ $labelCls }}">Observação <span class="{{ $opcionalCls }}">(opcional)</span></label>
            <input type="text" name="limite_credito_observacao" maxlength="255"
                   value="{{ old('limite_credito_observacao', $cliente->limite_credito_observacao ?? '') }}"
                   placeholder="Ex.: convênio da empresa X, autorizado por..." class="{{ $inputCls }} campo-limite-credito">
            @error('limite_credito_observacao') <p class="{{ $erroCls }}">{{ $message }}</p> @enderror
        </div>
    </div>

    @if ($existe)
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-xl bg-[#10284A] px-5 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Disponível para compras</p>
                <p class="text-2xl font-bold text-amber-50 mt-1">{{ $moedaLc($resumoConvenio['disponivel']) }}</p>
                <p class="text-xs text-slate-300 mt-0.5">
                    {{ $resumoConvenio['ativo'] ? 'No caixa, forma de pagamento "Convênio".' : 'Convênio não concedido.' }}
                </p>
            </div>
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-5 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Limite total</p>
                <p class="text-xl font-bold text-slate-800 mt-1">{{ $moedaLc($resumoConvenio['limite']) }}</p>
                <div class="mt-2 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                    <div class="h-full rounded-full {{ $percentualUso >= 90 ? 'bg-red-500' : 'bg-[#10284A]' }}
                                {{ ['w-0','w-1/12','w-1/6','w-1/4','w-1/3','w-5/12','w-1/2','w-7/12','w-2/3','w-3/4','w-5/6','w-11/12','w-full'][(int) round($percentualUso / 100 * 12)] }}"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ $percentualUso }}% usado</p>
            </div>
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-5 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Já usado (em aberto)</p>
                <p class="text-xl font-bold {{ $resumoConvenio['usado'] > $resumoConvenio['limite'] && $resumoConvenio['limite'] > 0 ? 'text-red-600' : 'text-slate-800' }} mt-1">
                    {{ $moedaLc($resumoConvenio['usado']) }}
                </p>
                <p class="text-xs text-slate-400 mt-0.5">Contas de convênio ainda não recebidas.</p>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-slate-800">Vendas no convênio</h3>
                <a href="{{ route('contas-receber.index', ['busca' => $cliente->nome]) }}" class="text-xs text-[#10284A] hover:underline">
                    Abrir no Contas a receber
                </a>
            </div>
            <div class="{{ $tabelaCaixaCls }}">
                <table class="w-full text-sm text-slate-700">
                    <thead class="{{ $theadCls }}">
                        <tr>
                            <th class="text-left px-3 py-2.5">Data</th>
                            <th class="text-left px-3 py-2.5">Venda</th>
                            <th class="text-left px-3 py-2.5">Vencimento</th>
                            <th class="text-right px-3 py-2.5">Valor</th>
                            <th class="text-right px-3 py-2.5">Em aberto</th>
                            <th class="text-left px-3 py-2.5">Situação</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($contasConvenio as $conta)
                            @php
                                $situacao = $conta->situacao();
                                [$rotulo, $cor] = match ($situacao) {
                                    'recebida' => ['Recebida', 'bg-green-100 text-green-800'],
                                    'vencida'  => ['Vencida', 'bg-red-100 text-red-700'],
                                    default    => ['Em aberto', 'bg-amber-100 text-amber-800'],
                                };
                                $proxima = $conta->proximaParcela();
                                $recebida = $conta->parcelas->filter->isRecebida()->sortByDesc('data_recebimento')->first();
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2.5">{{ $conta->data_conta?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2.5">{{ $conta->numero_documento ?: $conta->numeroExibicao() }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($proxima)
                                        {{ $proxima->vencimento->format('d/m/Y') }}
                                    @elseif ($recebida)
                                        <span class="text-slate-400">recebida em {{ $recebida->data_recebimento->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">{{ $moedaLc($conta->valor_total) }}</td>
                                <td class="px-3 py-2.5 text-right font-bold text-[#10284A]">{{ $moedaLc($conta->saldoAberto()) }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cor }}">{{ $rotulo }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ route('contas-receber.edit', $conta) }}" class="text-xs text-[#10284A] hover:underline">
                                        {{ $situacao === 'recebida' ? 'Ver' : 'Dar baixa' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400">Nenhuma venda no convênio ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($historicoLimite->isNotEmpty())
            <div>
                <h3 class="text-sm font-semibold text-slate-800 mb-2">Histórico do limite</h3>
                <div class="{{ $tabelaCaixaCls }}">
                    <table class="w-full text-sm text-slate-700">
                        <thead class="{{ $theadCls }}">
                            <tr>
                                <th class="text-left px-3 py-2.5">Data</th>
                                <th class="text-left px-3 py-2.5">Alteração</th>
                                <th class="text-left px-3 py-2.5">Prazo</th>
                                <th class="text-left px-3 py-2.5">Observação</th>
                                <th class="text-left px-3 py-2.5">Usuário</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($historicoLimite as $h)
                                <tr>
                                    <td class="px-3 py-2.5 whitespace-nowrap">{{ $h->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2.5">
                                        @if ($h->ativo)
                                            {{ $moedaLc($h->limite_anterior) }} → <strong>{{ $moedaLc($h->limite_novo) }}</strong>
                                        @else
                                            <span class="text-red-600 font-medium">Convênio suspenso</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">{{ $h->dias }} dias</td>
                                    <td class="px-3 py-2.5 text-slate-500">{{ $h->observacao ?: '—' }}</td>
                                    <td class="px-3 py-2.5">{{ $h->usuario?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>

<script>
function alternarLimiteCredito() {
    const ativo = document.getElementById('limite-credito-ativo').checked;
    // readOnly (e não disabled): os valores continuam indo no formulário e não se perdem ao suspender
    document.querySelectorAll('.campo-limite-credito').forEach(el => {
        el.readOnly = !ativo;
        el.classList.toggle('opacity-60', !ativo);
    });
}
alternarLimiteCredito();
@if ($abaComErro)
    document.addEventListener('DOMContentLoaded', () => mudarAba('limite-credito'));
@endif
</script>
