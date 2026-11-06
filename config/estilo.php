<?php

/*
 * Classes Tailwind dos formulários do painel (mesmo visual da tela de NF-e):
 * azul-marinho #10284A com detalhes em âmbar claro.
 * Uso na view: @php extract(config('estilo.formulario')); @endphp  →  $inputCls, $labelCls, ...
 * Este arquivo está no @source do resources/css/app.css: o Tailwind gera as classes daqui.
 */

return [
    'formulario' => [
        'inputCls' => 'w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 hover:border-slate-300 focus:bg-white focus:border-[#10284A]/40 focus:ring-2 focus:ring-[#10284A]/30 outline-none transition disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed',
        'labelCls' => 'block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5',
        'opcionalCls' => 'text-slate-400 font-normal normal-case tracking-normal',
        'erroCls' => 'text-red-600 text-xs mt-1',
        'cartaoCls' => 'bg-white rounded-xl shadow-sm ring-1 ring-slate-200',

        // Botão que abre a busca (cliente, fornecedor...): ícone à esquerda e setas à direita
        'seletorCls' => 'group w-full min-w-0 flex items-center gap-2.5 text-left bg-slate-50 border border-slate-200 rounded-xl pl-1.5 pr-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-white hover:border-[#10284A]/40 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[#10284A]/30 transition',
        'seletorIconeCls' => 'shrink-0 w-6 h-6 rounded-lg bg-[#10284A] text-amber-50 flex items-center justify-center',
        'limparCls' => 'shrink-0 w-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-lg leading-none hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition',

        'botaoPrimarioCls' => 'inline-flex items-center justify-center gap-2 bg-[#10284A] text-amber-50 rounded-xl px-6 py-2.5 text-sm font-semibold shadow-sm hover:bg-[#163a68] focus:outline-none focus:ring-2 focus:ring-[#10284A]/40 transition disabled:opacity-60 disabled:cursor-not-allowed',
        'botaoAcaoCls' => 'inline-flex items-center justify-center gap-1.5 bg-[#10284A] text-amber-50 rounded-xl px-4 py-2 text-sm font-medium shadow-sm hover:bg-[#163a68] focus:outline-none focus:ring-2 focus:ring-[#10284A]/40 transition whitespace-nowrap',
        'botaoSecundarioCls' => 'inline-flex items-center justify-center gap-1.5 bg-white border border-slate-200 text-slate-700 rounded-xl px-4 py-2 text-sm font-medium hover:border-[#10284A]/40 hover:text-[#10284A] transition whitespace-nowrap',
        'botaoCancelarCls' => 'inline-flex items-center justify-center rounded-xl px-6 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 transition',

        'theadCls' => 'bg-[#10284A] text-amber-50 text-[11px] uppercase tracking-wider',
        'tabelaCaixaCls' => 'rounded-xl ring-1 ring-slate-200 overflow-hidden',
        'modalTopoCls' => 'bg-[#10284A]',
    ],
];
