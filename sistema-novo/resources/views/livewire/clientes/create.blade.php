<div>
    <x-ui.page-header title="Novo cliente" subtitle="Cadastre um novo cliente." />

    <form wire:submit="salvar">
        @include('livewire.clientes._form')
    </form>
</div>
