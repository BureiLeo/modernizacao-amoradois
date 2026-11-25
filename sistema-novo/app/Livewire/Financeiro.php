<?php

namespace App\Livewire;

use App\Enums\ContaCaixa;
use App\Models\CaixaFeira;
use App\Models\CaixaMovimento;
use App\Services\Vendas\CaixaFeiraService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Financeiro extends Component
{
    public string $nome = '';

    public string $troco = '0,00';

    public string $dinheiroContado = '';

    public string $destino = 'canecas';

    public string $valorRepasse = '';

    public string $taxaRepasse = '';

    #[Locked]
    public ?int $caixaId = null;

    #[Locked]
    public string $token;

    public function mount(): void
    {
        $this->caixaId = CaixaFeira::query()->where('aberto', true)->value('id')
            ?? CaixaFeira::query()->latest('id')->value('id');
        $this->token = (string) Str::uuid();
    }

    public function selecionar(int $id): void
    {
        $this->caixaId = CaixaFeira::query()->findOrFail($id)->id;
        $this->reset('dinheiroContado', 'valorRepasse', 'taxaRepasse');
        $this->resetErrorBag();
        $this->token = (string) Str::uuid();
    }

    public function abrir(): void
    {
        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:100'],
            'troco' => ['required', 'regex:/^\d{1,10}([,.]\d{1,2})?$/D'],
        ]);

        try {
            $caixa = CaixaFeira::query()->create([
                'nome' => trim($dados['nome']),
                'troco_inicial' => str_replace(',', '.', $dados['troco']),
                'aberto_por' => auth()->id(),
                'aberto' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->addError('nome', 'Já existe um caixa aberto.');

            return;
        }

        $this->reset('nome', 'troco');
        $this->selecionar($caixa->id);
    }

    public function fechar(CaixaFeiraService $service): void
    {
        abort_if($this->caixaId === null, 404);
        $dados = $this->validate(['dinheiroContado' => ['required', 'regex:/^\d{1,10}([,.]\d{1,2})?$/D']]);
        $service->fechar($this->caixaId, str_replace(',', '.', $dados['dinheiroContado']), auth()->id());
        $this->reset('dinheiroContado');
    }

    public function transferir(CaixaFeiraService $service): void
    {
        abort_if($this->caixaId === null, 404);
        $dados = $this->validate([
            'destino' => ['required', 'in:canecas,impressao_3d'],
            'valorRepasse' => ['required', 'regex:/^\d{1,10}([,.]\d{1,2})?$/D'],
            'taxaRepasse' => ['nullable', 'regex:/^\d{1,10}([,.]\d{1,2})?$/D'],
        ]);

        try {
            $service->transferir(
                $this->caixaId,
                ContaCaixa::from($dados['destino']),
                str_replace(',', '.', $dados['valorRepasse']),
                str_replace(',', '.', $dados['taxaRepasse'] ?? '0'),
                $this->token,
                auth()->id(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('valorRepasse', $exception->getMessage());

            return;
        }

        $this->reset('valorRepasse', 'taxaRepasse');
        $this->token = (string) Str::uuid();
    }

    public function render(CaixaFeiraService $service): View
    {
        $caixa = CaixaFeira::query()->find($this->caixaId);

        return view('livewire.financeiro', [
            'contas' => ContaCaixa::cases(),
            'saldos' => CaixaMovimento::query()->groupBy('conta')->selectRaw('conta, sum(valor) as saldo')->pluck('saldo', 'conta'),
            'caixas' => CaixaFeira::query()->latest('id')->get(['id', 'nome', 'aberto', 'created_at']),
            'aberto' => CaixaFeira::query()->where('aberto', true)->first(),
            'caixa' => $caixa,
            'resumo' => $caixa ? $service->resumo($caixa) : null,
            'repasses' => $caixa?->transferencias()->latest('id')->get(),
        ]);
    }
}
