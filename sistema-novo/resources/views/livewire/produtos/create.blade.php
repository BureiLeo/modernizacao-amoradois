<div>
    <x-ui.page-header title="Novo produto" subtitle="Cadastre um novo produto." />

    <form wire:submit="salvar" enctype="multipart/form-data">
        @include('livewire.produtos._form')
    </form>
</div>
