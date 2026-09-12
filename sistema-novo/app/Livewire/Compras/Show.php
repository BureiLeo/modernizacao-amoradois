<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Services\Compras\CompraService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

#[Layout('layouts.app')]
class Show extends Component
{
    public string $lote = '';

    public bool $confirmandoEstorno = false;

    public bool $confirmandoPagamento = false;

    public string $formaPagamento = '';

    public function mount(string $lote): void
    {
        $this->lote = $lote;

        if ($this->compras()->isEmpty()) {
            throw new NotFoundHttpException('Compra não encontrada.');
        }
    }

    /**
     * O identificador da URL pode ser um lote_id (compras lancadas pelo
     * sistema novo) ou o id de uma compra avulsa vinda do legado.
     *
     * @return Collection<int, Compra>
     */
    public function compras(): Collection
    {
        return Compra::query()
            ->with(['material', 'fornecedor'])
            ->where(function ($query) {
                $query->where('lote_id', $this->lote);

                if (ctype_digit($this->lote)) {
                    $query->orWhere(fn ($sub) => $sub->whereNull('lote_id')->where('id', (int) $this->lote));
                }
            })
            ->orderBy('id')
            ->get();
    }

    public function marcarComoPaga(CompraService $compras): void
    {
        $linhas = $this->compras();

        if ($linhas->isEmpty() || $linhas->first()->lote_id === null) {
            $this->addError('pagamento', 'Compras antigas não podem ser alteradas para preservar o histórico.');
            $this->confirmandoPagamento = false;

            return;
        }

        try {
            $compras->markAsPaid((string) $linhas->first()->lote_id, $this->formaPagamento ?: null);
        } catch (Throwable $e) {
            $this->addError('pagamento', $e->getMessage());
            $this->confirmandoPagamento = false;

            return;
        }

        $this->confirmandoPagamento = false;

        session()->flash('success', 'Compra marcada como paga e lançada no caixa.');
    }

    public function estornar(CompraService $compras): void
    {
        $linhas = $this->compras();

        if ($linhas->isEmpty() || $linhas->first()->lote_id === null) {
            $this->addError('estorno', 'Compras antigas não podem ser estornadas para preservar o histórico.');
            $this->confirmandoEstorno = false;

            return;
        }

        try {
            $compras->reverseBatch((string) $linhas->first()->lote_id, auth()->id());
        } catch (Throwable $e) {
            $this->addError('estorno', $e->getMessage());
            $this->confirmandoEstorno = false;

            return;
        }

        session()->flash('success', 'Compra estornada e estoque devolvido.');

        $this->redirectRoute('compras.index', navigate: true);
    }

    public function render(): View
    {
        $compras = $this->compras();
        $primeira = $compras->first();

        $subtotal = round((float) $compras->sum('valor'), 2);
        $frete = round((float) $compras->sum(fn ($c) => (float) $c->frete), 2);
        $desconto = round((float) $compras->sum(fn ($c) => (float) $c->desconto), 2);

        $movimentos = DB::table('stock_movements')
            ->where('source_type', 'compra')
            ->whereIn('source_id', $compras->pluck('id')->all())
            ->count();

        return view('livewire.compras.show', [
            'compras' => $compras,
            'nota' => $primeira,
            'subtotal' => $subtotal,
            'frete' => $frete,
            'desconto' => $desconto,
            'total' => round($subtotal + $frete - $desconto, 2),
            'movimentos' => $movimentos,
        ]);
    }
}
