@props(['status'])

@php
    // Mapa central de status conhecidos -> (rotulo, variante). Etapa 5 #20:
    // nunca depender so da cor - o texto do status sempre aparece.
    $map = [
        'ativo' => ['label' => 'Ativo', 'variant' => 'success'],
        'inativo' => ['label' => 'Inativo', 'variant' => 'neutral'],
        'pago' => ['label' => 'Pago', 'variant' => 'success'],
        'pendente' => ['label' => 'Pendente', 'variant' => 'warning'],
        'cancelado' => ['label' => 'Cancelado', 'variant' => 'danger'],
        'cancelada' => ['label' => 'Cancelada', 'variant' => 'danger'],
        'concluida' => ['label' => 'Concluída', 'variant' => 'success'],
        'concluido' => ['label' => 'Concluído', 'variant' => 'success'],
        'em_producao' => ['label' => 'Em produção', 'variant' => 'info'],
        // Status de estoque (App\Models\Material::stockStatus()).
        'normal' => ['label' => 'Em estoque', 'variant' => 'success'],
        'baixo' => ['label' => 'Estoque baixo', 'variant' => 'warning'],
        'sem_estoque' => ['label' => 'Sem estoque', 'variant' => 'danger'],
    ];

    $key = is_object($status) && method_exists($status, 'value') ? $status->value : (string) $status;
    $info = $map[$key] ?? ['label' => ucfirst($key), 'variant' => 'neutral'];
@endphp

<x-ui.badge :variant="$info['variant']" {{ $attributes }}>{{ $info['label'] }}</x-ui.badge>
