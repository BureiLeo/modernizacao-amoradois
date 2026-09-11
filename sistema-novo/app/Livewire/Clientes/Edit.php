<?php

namespace App\Livewire\Clientes;

use App\Livewire\Forms\ClienteForm;
use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Cliente $cliente;

    public ClienteForm $form;

    public function mount(Cliente $cliente): void
    {
        $this->authorize('update', $cliente);

        $this->cliente = $cliente;
        $this->form->setCliente($cliente);
    }

    public function salvar(): void
    {
        $this->authorize('update', $this->cliente);

        $this->form->validate();

        // codigo e id NUNCA sao alterados aqui (Etapa 6 #15) - so os
        // campos cadastrais do dadosNormalizados().
        $this->cliente->update($this->form->dadosNormalizados());

        session()->flash('success', 'Cliente atualizado com sucesso.');

        $this->redirectRoute('clientes.show', $this->cliente, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.clientes.edit');
    }
}
