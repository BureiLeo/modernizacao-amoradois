<div>
    <x-ui.page-header title="Editar cliente" :subtitle="'#'.$cliente->codigo.' — '.$cliente->nome" />

    <form wire:submit="salvar">
        @include('livewire.clientes._form')
    </form>
</div>
