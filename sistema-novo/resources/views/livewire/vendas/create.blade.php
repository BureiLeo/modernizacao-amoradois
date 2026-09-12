@include('livewire.vendas._form', [
    'titulo' => 'Nova venda',
    'subtitulo' => 'Toque nos produtos para montar o carrinho.',
    'cancelarUrl' => route('vendas.index'),
    'finalizarLabel' => 'Finalizar venda',
    'confirmarLabel' => 'Confirmar venda',
])
