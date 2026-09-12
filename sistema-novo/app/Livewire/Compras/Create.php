<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Material;
use App\Services\Compras\CompraService;
use App\Services\Compras\NfeXmlParser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;

    public string $data = '';

    public string $fornecedor_id = '';

    public string $numero_nota = '';

    public string $chave_nfe = '';

    public string $frete = '0';

    public string $desconto = '0';

    public string $forma_pagamento = '';

    public bool $pago = false;

    public string $observacoes = '';

    /** @var array<int, array<string, mixed>> */
    public array $itens = [];

    public $xml = null;

    public bool $notaDuplicada = false;

    public function mount(): void
    {
        $this->data = now()->toDateString();
        $this->itens = [$this->itemVazio()];
    }

    public function adicionarItem(): void
    {
        $this->itens[] = $this->itemVazio();
    }

    public function removerItem(int $index): void
    {
        unset($this->itens[$index]);

        $this->itens = array_values($this->itens);

        if ($this->itens === []) {
            $this->itens = [$this->itemVazio()];
        }
    }

    /**
     * Le o XML da NF-e e pre-preenche a nota: dados do cabecalho, frete,
     * desconto e um item por produto, ja tentando casar com materiais
     * existentes. Nada e salvo aqui - a pessoa confere antes.
     */
    public function importarXml(NfeXmlParser $parser): void
    {
        $this->validate([
            'xml' => ['required', 'file', 'max:5120'],
        ], [], ['xml' => 'arquivo XML']);

        try {
            $dados = $parser->parse(file_get_contents($this->xml->getRealPath()) ?: '');
        } catch (Throwable $e) {
            $this->addError('xml', $e->getMessage());

            return;
        }

        $this->numero_nota = (string) ($dados['numero_nota'] ?? '');
        $this->chave_nfe = (string) ($dados['chave_nfe'] ?? '');
        $this->data = $dados['data'] ?? $this->data;
        $this->frete = (string) ($dados['frete'] ?? 0);
        $this->desconto = (string) ($dados['desconto'] ?? 0);

        $fornecedor = $this->encontrarFornecedor($dados['fornecedor_cnpj'] ?? null, $dados['fornecedor_nome'] ?? null);
        $this->fornecedor_id = $fornecedor ? (string) $fornecedor->id : '';

        $novos = 0;
        $itens = [];

        foreach ($dados['itens'] as $item) {
            $material = $this->casarMaterial((string) $item['descricao']);

            if (! $material) {
                $novos++;
            }

            $itens[] = [
                'material_id' => $material ? (string) $material->id : '',
                'nome' => $material ? '' : Str::limit((string) $item['descricao'], 120, ''),
                'unidade_base' => $material ? $material->unidade_base : (string) $item['unidade'],
                'fator_embalagem' => 1,
                'estoque_minimo' => '0',
                'quantidade' => (string) $item['quantidade'],
                'valor_unitario' => (string) $item['valor_unitario'],
                'descricao_nota' => (string) $item['descricao'],
            ];
        }

        $this->itens = $itens;
        $this->xml = null;

        $this->notaDuplicada = $this->chave_nfe !== ''
            && Compra::query()->where('chave_nfe', $this->chave_nfe)->exists();

        $encontrados = count($itens) - $novos;

        session()->flash(
            'import',
            count($itens).' item(ns) lido(s) da nota: '.$encontrados.' já cadastrado(s) e '.$novos.' novo(s) para conferir.'
        );
    }

    public function salvar(CompraService $compras): void
    {
        $this->resetErrorBag();

        $this->validate([
            'data' => ['required', 'date'],
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'numero_nota' => ['nullable', 'string', 'max:30'],
            'chave_nfe' => ['nullable', 'string', 'max:44'],
            'forma_pagamento' => ['nullable', 'string', 'max:30'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'itens' => ['required', 'array', 'min:1'],
        ]);

        $itens = [];

        foreach ($this->itens as $index => $item) {
            $materialId = trim((string) ($item['material_id'] ?? ''));
            $nome = trim((string) ($item['nome'] ?? ''));

            if ($materialId === '' && $nome === '') {
                $this->addError("itens.{$index}.nome", 'Escolha um material existente ou informe o nome do novo material.');

                continue;
            }

            $quantidade = $this->numero($item['quantidade'] ?? null);
            $valorUnitario = $this->numero($item['valor_unitario'] ?? null);

            if ($quantidade <= 0) {
                $this->addError("itens.{$index}.quantidade", 'Informe uma quantidade maior que zero.');

                continue;
            }

            if ($valorUnitario < 0) {
                $this->addError("itens.{$index}.valor_unitario", 'O valor não pode ser negativo.');

                continue;
            }

            $itens[] = [
                'material_id' => $materialId !== '' ? (int) $materialId : null,
                'nome' => $nome,
                'unidade_base' => trim((string) ($item['unidade_base'] ?? 'un')) ?: 'un',
                'fator_embalagem' => max((int) ($item['fator_embalagem'] ?? 1), 1),
                'estoque_minimo' => $this->numero($item['estoque_minimo'] ?? null),
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
            ];
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $lote = $compras->registerBatch([
                'data' => $this->data,
                'fornecedor_id' => $this->fornecedor_id !== '' ? (int) $this->fornecedor_id : null,
                'numero_nota' => $this->numero_nota !== '' ? $this->numero_nota : null,
                'chave_nfe' => $this->chave_nfe !== '' ? $this->chave_nfe : null,
                'frete' => $this->numero($this->frete),
                'desconto' => $this->numero($this->desconto),
                'forma_pagamento' => $this->forma_pagamento !== '' ? $this->forma_pagamento : null,
                'pago' => $this->pago,
                'observacoes' => $this->observacoes !== '' ? $this->observacoes : null,
                'itens' => $itens,
            ], auth()->id());
        } catch (Throwable $e) {
            $this->addError('itens', $e->getMessage());

            return;
        }

        session()->flash('success', 'Compra lançada e estoque atualizado ('.$lote->count().' item(ns)).');

        $this->redirectRoute('compras.show', ['lote' => $lote->first()->lote_id], navigate: true);
    }

    public function subtotal(): float
    {
        $total = 0.0;

        foreach ($this->itens as $item) {
            $total += $this->numero($item['quantidade'] ?? null) * $this->numero($item['valor_unitario'] ?? null);
        }

        return round($total, 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->numero($this->frete) - $this->numero($this->desconto), 2);
    }

    public function render(): View
    {
        return view('livewire.compras.create', [
            'materiais' => Material::query()->orderBy('nome')->get(['id', 'nome', 'unidade_base']),
            'fornecedores' => Fornecedor::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemVazio(): array
    {
        return [
            'material_id' => '',
            'nome' => '',
            'unidade_base' => 'un',
            'fator_embalagem' => 1,
            'estoque_minimo' => '0',
            'quantidade' => '1',
            'valor_unitario' => '0',
            'descricao_nota' => null,
        ];
    }

    protected function casarMaterial(string $descricao): ?Material
    {
        $descricao = trim($descricao);

        if ($descricao === '') {
            return null;
        }

        $exato = Material::query()->whereRaw('LOWER(nome) = ?', [Str::lower($descricao)])->first();

        if ($exato) {
            return $exato;
        }

        return Material::query()
            ->where('nome', 'like', '%'.$descricao.'%')
            ->orderBy('nome')
            ->first();
    }

    protected function encontrarFornecedor(?string $cnpj, ?string $nome): ?Fornecedor
    {
        $digitos = $cnpj ? preg_replace('/\D/', '', $cnpj) : null;

        if ($digitos) {
            $porCnpj = Fornecedor::query()
                ->whereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$digitos])
                ->first();

            if ($porCnpj) {
                return $porCnpj;
            }
        }

        if ($nome) {
            return Fornecedor::query()->where('nome', 'like', '%'.trim($nome).'%')->first();
        }

        return null;
    }

    /**
     * Aceita "12,50" e "12.50" - a entrada real vem tanto do teclado
     * brasileiro quanto do XML (que usa ponto).
     */
    protected function numero(mixed $valor): float
    {
        $raw = trim((string) $valor);

        if ($raw === '') {
            return 0.0;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace(['.', ','], ['', '.'], $raw);
        }

        return (float) $raw;
    }
}
