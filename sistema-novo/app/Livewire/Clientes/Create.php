<?php

namespace App\Livewire\Clientes;

use App\Livewire\Forms\ClienteForm;
use App\Models\Cliente;
use App\Services\Clientes\ClienteCodeGenerator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public ClienteForm $form;

    public function mount(): void
    {
        $this->authorize('create', Cliente::class);
    }

    public function salvar(ClienteCodeGenerator $codeGenerator): void
    {
        $this->authorize('create', Cliente::class);

        $this->form->validate();

        $cliente = $codeGenerator->create($this->form->dadosNormalizados());

        session()->flash('success', "Cliente #{$cliente->codigo} cadastrado com sucesso.");

        $this->redirectRoute('clientes.show', $cliente, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.clientes.create');
    }
}
