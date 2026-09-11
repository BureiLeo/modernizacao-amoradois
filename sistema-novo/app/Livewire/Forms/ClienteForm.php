<?php

namespace App\Livewire\Forms;

use App\Models\Cliente;
use Livewire\Form;

class ClienteForm extends Form
{
    public ?Cliente $cliente = null;

    public string $nome = '';

    public ?string $telefone = null;

    public ?string $email = null;

    public ?string $cpf = null;

    public ?string $rg = null;

    public ?string $cnpj = null;

    public ?string $razao_social = null;

    public ?string $endereco = null;

    public ?string $observacoes = null;

    public bool $ativo = true;

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['nullable', 'string', $this->digitCountRule(11, 'CPF')],
            'rg' => ['nullable', 'string', 'max:20'],
            'cnpj' => ['nullable', 'string', $this->digitCountRule(14, 'CNPJ')],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:1000'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['boolean'],
        ];
    }

    /**
     * Validacao amigavel de formato (Etapa 6 #14): so verifica a
     * quantidade de digitos apos remover mascara, sem checksum
     * completo de CPF/CNPJ (evita superengenharia). Nao afeta dados
     * historicos ja salvos - so valida o que for submetido no formulario.
     */
    private function digitCountRule(int $digits, string $label): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($digits, $label) {
            if ($value === null || $value === '') {
                return;
            }
            $onlyDigits = preg_replace('/\D/', '', $value);
            if (strlen($onlyDigits) !== $digits) {
                $fail("O {$label} deve conter {$digits} dígitos.");
            }
        };
    }

    public function setCliente(Cliente $cliente): void
    {
        $this->cliente = $cliente;
        $this->nome = $cliente->nome;
        $this->telefone = $cliente->telefone;
        $this->email = $cliente->email;
        $this->cpf = $cliente->cpf;
        $this->rg = $cliente->rg;
        $this->cnpj = $cliente->cnpj;
        $this->razao_social = $cliente->razao_social;
        $this->endereco = $cliente->endereco;
        $this->observacoes = $cliente->observacoes;
        $this->ativo = $cliente->ativo;
    }

    /**
     * @return array<string, mixed>
     */
    public function dadosNormalizados(): array
    {
        return [
            'nome' => trim($this->nome),
            // Telefone salvo sempre so com digitos (Etapa 6 #12): mais
            // consistente para buscas futuras; a mascara e so visual.
            'telefone' => $this->telefone ? preg_replace('/\D/', '', $this->telefone) : null,
            'email' => $this->email ? trim($this->email) : null,
            'cpf' => $this->cpf ? preg_replace('/\D/', '', $this->cpf) : null,
            'rg' => $this->rg ? trim($this->rg) : null,
            'cnpj' => $this->cnpj ? preg_replace('/\D/', '', $this->cnpj) : null,
            'razao_social' => $this->razao_social ? trim($this->razao_social) : null,
            'endereco' => $this->endereco,
            'observacoes' => $this->observacoes,
            'ativo' => $this->ativo,
        ];
    }
}
