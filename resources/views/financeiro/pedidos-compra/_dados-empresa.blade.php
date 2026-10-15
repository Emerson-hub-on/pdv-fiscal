@php
    $cnpjNum = preg_replace('/\D/', '', (string) $empresa->cnpj);
    $cnpjFmt = strlen($cnpjNum) === 14
        ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjNum)
        : $cnpjNum;

    $cepNum = preg_replace('/\D/', '', (string) $empresa->cep);
    $cepFmt = strlen($cepNum) === 8 ? substr($cepNum, 0, 5) . '-' . substr($cepNum, 5) : $cepNum;

    $enderecoLinha = collect([
        trim(($empresa->logradouro ?? '') . ', ' . ($empresa->numero ?? ''), ', '),
        $empresa->complemento,
        $empresa->bairro,
    ])->filter()->implode(' — ');

    $crtTexto = [
        1 => 'Simples Nacional',
        2 => 'Simples Nacional — excesso de sublimite',
        3 => 'Regime Normal',
    ][(int) $empresa->crt] ?? '—';

    $dtCls = 'text-xs text-gray-500 uppercase';
    $ddCls = 'text-sm font-medium text-gray-800';
@endphp

<dl class="grid grid-cols-3 gap-x-4 gap-y-3">
    <div class="col-span-2">
        <dt class="{{ $dtCls }}">Razão social</dt>
        <dd class="{{ $ddCls }}">{{ $empresa->razao_social ?: '—' }}</dd>
    </div>
    <div>
        <dt class="{{ $dtCls }}">CNPJ</dt>
        <dd class="{{ $ddCls }}">{{ $cnpjFmt ?: '—' }}</dd>
    </div>

    <div class="col-span-2">
        <dt class="{{ $dtCls }}">Nome fantasia</dt>
        <dd class="{{ $ddCls }}">{{ $empresa->nome_fantasia ?: '—' }}</dd>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <dt class="{{ $dtCls }}">Insc. estadual</dt>
            <dd class="{{ $ddCls }}">{{ $empresa->ie ?: '—' }}</dd>
        </div>
        <div>
            <dt class="{{ $dtCls }}">Insc. municipal</dt>
            <dd class="{{ $ddCls }}">{{ $empresa->im ?: '—' }}</dd>
        </div>
    </div>

    <div class="col-span-3">
        <dt class="{{ $dtCls }}">Endereço</dt>
        <dd class="{{ $ddCls }}">{{ $enderecoLinha ?: '—' }}</dd>
    </div>

    <div>
        <dt class="{{ $dtCls }}">Município / UF</dt>
        <dd class="{{ $ddCls }}">
            {{ $empresa->municipio ? $empresa->municipio . ' / ' . $empresa->uf : '—' }}
            @if ($empresa->cod_municipio)
                <span class="text-xs font-normal text-gray-400">(IBGE {{ $empresa->cod_municipio }})</span>
            @endif
        </dd>
    </div>
    <div>
        <dt class="{{ $dtCls }}">CEP</dt>
        <dd class="{{ $ddCls }}">{{ $cepFmt ?: '—' }}</dd>
    </div>
    <div>
        <dt class="{{ $dtCls }}">Regime tributário</dt>
        <dd class="{{ $ddCls }}">{{ $crtTexto }}</dd>
    </div>
</dl>
