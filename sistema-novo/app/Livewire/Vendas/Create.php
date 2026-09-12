<?php

namespace App\Livewire\Vendas;

use App\Models\Cliente;
use App\Models\Produto;
use App\Services\Clientes\ClienteCodeGenerator;
use App\Services\Vendas\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class Create extends Component
{
    public ?int $cliente_id = null;

    public string $buscaProduto = '';

    public string $buscaCliente = '';

    public bool $mostrarNovoCliente = false;

    public string $novoClienteNome = '';

    public string $novoClienteTelefone = '';

    /**
     * Carrinho indexado por chave: "p:{id}" para produtos e "o:{uuid}" para itens avulsos.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $carrinho = [];

    public bool $mostrarOutros = false;

    public string $outroDescricao = '';

    public string $outroValor = '';

    public int $outroQuantidade = 1;

    public string $frete = '';

    public string $desconto = '';

    public string $forma_pagamento = 'pix';

    public bool $pago = true;

    public string $observacoes = '';

    public bool $confirmando = false;

    public ?string $erro = null;

    protected bool $salvando = false;

    // ==================== PRODUTOS ====================

    public function adicionar(int $produtoId): void
    {
        $chave = 'p:'.$produtoId;

        if (isset($this->carrinho[$chave])) {
            $this->carrinho[$chave]['quantidade']++;

            return;
        }

        $produto = Produto::query()->find($produtoId);

        if (! $produto) {
            return;
        }

        $this->carrinho[$chave] = [
            'produto_id' => $produto->id,
            'descricao' => null,
            'nome' => $produto->nome,
            'imagem' => $produto->imagem,
            'quantidade' => 1,
            'preco_unitario' => (float) $produto->preco_venda,
        ];
    }

    public function aumentar(string $chave): void
    {
        if (isset($this->carrinho[$chave])) {
            $this->carrinho[$chave]['quantidade']++;
        }
    }

    public function diminuir(string $chave): void
    {
        if (! isset($this->carrinho[$chave])) {
            return;
        }

        if ($this->carrinho[$chave]['quantidade'] <= 1) {
            $this->remover($chave);

            return;
        }

        $this->carrinho[$chave]['quantidade']--;
    }

    public function remover(string $chave): void
    {
        unset($this->carrinho[$chave]);
    }

    public function limpar(): void
    {
        $this->carrinho = [];
        $this->confirmando = false;
    }

    public function updatedCarrinho(): void
    {
        foreach ($this->carrinho as $chave => $linha) {
            $quantidade = (int) ($linha['quantidade'] ?? 1);
            $this->carrinho[$chave]['quantidade'] = max($quantidade, 1);
            $this->carrinho[$chave]['preco_unitario'] = max($this->numero($linha['preco_unitario'] ?? 0), 0);
        }
    }

    // ==================== CLIENTE ====================

    public function selecionarCliente(int $clienteId): void
    {
        $this->cliente_id = $clienteId;
        $this->buscaCliente = '';
    }

    public function limparCliente(): void
    {
        $this->cliente_id = null;
        $this->buscaCliente = '';
    }

    public function abrirNovoCliente(): void
    {
        $this->novoClienteNome = trim($this->buscaCliente);
        $this->novoClienteTelefone = '';
        $this->resetValidation(['novoClienteNome', 'novoClienteTelefone']);
        $this->mostrarNovoCliente = true;
    }

    public function fecharNovoCliente(): void
    {
        $this->mostrarNovoCliente = false;
        $this->reset('novoClienteNome', 'novoClienteTelefone');
    }

    public function criarClienteRapido(ClienteCodeGenerator $codeGenerator): void
    {
        $this->validate([
            'novoClienteNome' => ['required', 'string', 'max:255'],
            'novoClienteTelefone' => ['nullable', 'string', 'max:20'],
        ]);

        $cliente = $codeGenerator->create([
            'nome' => trim($this->novoClienteNome),
            'telefone' => $this->novoClienteTelefone !== '' ? preg_replace('/\D/', '', $this->novoClienteTelefone) : null,
            'ativo' => true,
        ]);

        $this->cliente_id = $cliente->id;
        $this->buscaCliente = '';
        $this->mostrarNovoCliente = false;
        $this->reset('novoClienteNome', 'novoClienteTelefone');
    }

    // ==================== OUTROS CUSTOS ====================

    public function adicionarOutro(): void
    {
        $descricao = trim($this->outroDescricao);
        $valor = $this->numero($this->outroValor);

        $this->resetValidation();

        if ($descricao === '') {
            $this->addError('outroDescricao', 'Descreva o custo adicional.');

            return;
        }

        if ($valor <= 0) {
            $this->addError('outroValor', 'Informe um valor maior que zero.');

            return;
        }

        $this->carrinho['o:'.Str::uuid()->toString()] = [
            'produto_id' => null,
            'descricao' => $descricao,
            'nome' => $descricao,
            'imagem' => null,
            'quantidade' => max($this->outroQuantidade, 1),
            'preco_unitario' => $valor,
        ];

        $this->reset('outroDescricao', 'outroValor', 'outroQuantidade', 'mostrarOutros');
        $this->outroQuantidade = 1;
    }

    // ==================== TOTAIS ====================

    public function subtotal(): float
    {
        $total = 0.0;

        foreach ($this->carrinho as $linha) {
            $total += (float) $linha['preco_unitario'] * (int) $linha['quantidade'];
        }

        return round($total, 2);
    }

    public function freteValor(): float
    {
        return max($this->numero($this->frete), 0);
    }

    public function descontoValor(): float
    {
        return max($this->numero($this->desconto), 0);
    }

    public function total(): float
    {
        return max(0.0, round($this->subtotal() + $this->freteValor() - $this->descontoValor(), 2));
    }

    public function totalItens(): int
    {
        return array_sum(array_map(fn ($linha) => (int) $linha['quantidade'], $this->carrinho));
    }

    // ==================== FINALIZAÇÃO ====================

    public function revisar(): void
    {
        $this->erro = null;

        if ($this->carrinho === []) {
            $this->erro = 'Adicione pelo menos um item à venda.';

            return;
        }

        if ($this->descontoValor() > $this->subtotal() + $this->freteValor()) {
            $this->erro = 'O desconto não pode deixar o total negativo.';

            return;
        }

        $this->confirmando = true;
    }

    public function salvar(VendaService $vendaService): void
    {
        // Protege contra duplo clique / reenvio.
        if ($this->salvando || $this->carrinho === []) {
            return;
        }

        $this->salvando = true;
        $this->erro = null;

        $this->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'forma_pagamento' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->descontoValor() > $this->subtotal() + $this->freteValor()) {
            $this->erro = 'O desconto não pode deixar o total negativo.';
            $this->salvando = false;
            $this->confirmando = false;

            return;
        }

        $itens = [];

        foreach ($this->carrinho as $linha) {
            $itens[] = [
                'produto_id' => $linha['produto_id'],
                'descricao' => $linha['descricao'],
                'quantidade' => max((int) $linha['quantidade'], 1),
                'preco_unitario' => max((float) $linha['preco_unitario'], 0),
            ];
        }

        try {
            $venda = $vendaService->create([
                'cliente_id' => $this->cliente_id,
                'user_id' => auth()->id(),
                'itens' => $itens,
                'frete' => $this->freteValor(),
                'desconto' => $this->descontoValor(),
                'forma_pagamento' => $this->pago ? $this->forma_pagamento : null,
                'pago' => $this->pago,
                'observacoes' => $this->observacoes !== '' ? $this->observacoes : null,
            ]);
        } catch (Throwable $e) {
            $this->salvando = false;
            $this->confirmando = false;
            $this->erro = $e->getMessage();

            return;
        }

        session()->flash('success', $this->pago ? 'Venda registrada com sucesso.' : 'Venda registrada como pendente.');

        $this->redirectRoute('vendas.show', $venda, navigate: true);
    }

    protected function numero(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return 0.0;
        }

        // Aceita "1.234,50" e "1234.50".
        $texto = str_replace(['R$', ' '], '', $texto);

        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }

        return (float) $texto;
    }

    public function render(): View
    {
        $produtos = Produto::query()
            ->where('ativo', true)
            ->when($this->buscaProduto !== '', function ($query) {
                $termo = '%'.$this->buscaProduto.'%';
                $query->where(fn ($q) => $q->where('nome', 'like', $termo)->orWhere('sku', 'like', $termo));
            })
            ->orderBy('nome')
            ->limit(48)
            ->get(['id', 'nome', 'sku', 'preco_venda', 'imagem']);

        $clienteSelecionado = $this->cliente_id
            ? Cliente::query()->find($this->cliente_id, ['id', 'nome', 'telefone'])
            : null;

        // So busca sugestoes quando ainda nao ha cliente selecionado -
        // depois de escolher, o autocomplete vira um "chip" (ver _form.blade.php).
        $clientes = (! $this->cliente_id && $this->buscaCliente !== '')
            ? Cliente::query()
                ->where(fn ($q) => $q->where('nome', 'like', '%'.$this->buscaCliente.'%')->orWhere('telefone', 'like', '%'.$this->buscaCliente.'%'))
                ->orderBy('nome')
                ->limit(8)
                ->get(['id', 'nome', 'telefone'])
            : collect();

        return view($this->viewName(), compact('produtos', 'clientes', 'clienteSelecionado'));
    }

    protected function viewName(): string
    {
        return 'livewire.vendas.create';
    }
}
