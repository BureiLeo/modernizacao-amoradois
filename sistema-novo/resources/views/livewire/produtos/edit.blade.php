<div>
    <x-ui.page-header title="Editar produto" :subtitle="$produto->nome" />

    <form wire:submit="salvar" enctype="multipart/form-data">
        @include('livewire.produtos._form')
    </form>
</div>
