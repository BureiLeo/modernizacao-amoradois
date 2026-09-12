@include('livewire.vendas._form', [
    'titulo' => 'Editar venda #'.$venda->id,
    'subtitulo' => 'Ajuste os itens, o frete e o pagamento desta venda.',
    'cancelarUrl' => route('vendas.show', $venda),
    'finalizarLabel' => 'Salvar alterações',
    'confirmarLabel' => 'Confirmar alterações',
])
