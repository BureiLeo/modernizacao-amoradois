<?php

namespace App\Services\Vendas;

use App\Enums\CaixaTipo;
use App\Enums\ContaCaixa;
use App\Enums\FormaPagamento;
use App\Models\CaixaFeira;
use App\Models\CaixaMovimento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CaixaFeiraService
{
    public function fechar(int $id, string $dinheiro, int $userId): void
    {
        DB::transaction(function () use ($id, $dinheiro, $userId) {
            $caixa = CaixaFeira::query()->lockForUpdate()->findOrFail($id);

            if ($caixa->aberto) {
                $caixa->update([
                    'aberto' => null,
                    'fechado_em' => now(),
                    'fechado_por' => $userId,
                    'dinheiro_contado' => $dinheiro,
                ]);
            }
        });
    }

    public function resumo(CaixaFeira $caixa): array
    {
        $linhas = array_fill_keys(array_column(ContaCaixa::linhas(), 'value'), 0);
        $formas = array_fill_keys(array_column(FormaPagamento::cases(), 'value'), 0);
        $formasPorLinha = array_fill_keys(array_keys($linhas), $formas);

        $grupos = $caixa->vendas()
            ->join('venda_itens', 'vendas.id', '=', 'venda_itens.venda_id')
            ->groupBy('venda_itens.conta', 'vendas.forma_pagamento')
            ->selectRaw('venda_itens.conta, vendas.forma_pagamento, sum(venda_itens.subtotal) as total')
            ->get();

        foreach ($grupos as $grupo) {
            $centavos = (int) round((float) $grupo->total * 100);
            $linhas[$grupo->conta] += $centavos;
            $formas[$grupo->forma_pagamento] += $centavos;
            $formasPorLinha[$grupo->conta][$grupo->forma_pagamento] += $centavos;
        }

        $transferido = $taxas = array_fill_keys(array_keys($linhas), 0);
        $repasses = $caixa->transferencias()->groupBy('destino')
            ->selectRaw('destino, sum(valor) as total, sum(taxa) as taxas')->get();

        foreach ($repasses as $repasse) {
            $transferido[$repasse->destino] = (int) round((float) $repasse->total * 100);
            $taxas[$repasse->destino] = (int) round((float) $repasse->taxas * 100);
        }

        $pendente = [];
        foreach ($linhas as $conta => $total) {
            $pendente[$conta] = $total - $transferido[$conta] - $taxas[$conta];
        }

        $esperado = (int) round((float) $caixa->troco_inicial * 100) + $formas[FormaPagamento::Dinheiro->value];

        return compact('linhas', 'formas', 'formasPorLinha', 'transferido', 'taxas', 'pendente', 'esperado');
    }

    public function transferir(int $id, ContaCaixa $destino, string $valor, string $taxa, string $token, int $userId): void
    {
        DB::transaction(function () use ($id, $destino, $valor, $taxa, $token, $userId) {
            $caixa = CaixaFeira::query()->lockForUpdate()->findOrFail($id);

            if ($caixa->transferencias()->where('token', $token)->exists()) {
                return;
            }

            if ($caixa->aberto || ! in_array($destino, ContaCaixa::linhas(), true)) {
                throw new InvalidArgumentException('Feche o caixa da feira e selecione uma conta de destino.');
            }

            $resumo = $this->resumo($caixa);
            $disponivel = $resumo['pendente'][$destino->value];
            $centavos = (int) round((float) $valor * 100);
            $centavosTaxa = (int) round((float) $taxa * 100);

            if ($centavos <= 0 || $centavosTaxa < 0 || $centavos + $centavosTaxa > $disponivel) {
                throw new InvalidArgumentException('O repasse precisa ser maior que zero. O valor e a taxa juntos devem caber no saldo a repassar.');
            }

            $transferencia = $caixa->transferencias()->create([
                'token' => $token,
                'user_id' => $userId,
                'destino' => $destino->value,
                'valor' => $centavos / 100,
                'taxa' => $centavosTaxa / 100,
            ]);
            $dados = [
                'data' => now(),
                'ref_tabela' => 'transferencias_feira',
                'ref_id' => $transferencia->id,
                'descricao' => 'Repasse da feira para '.$destino->label(),
            ];

            CaixaMovimento::query()->create($dados + [
                'tipo' => CaixaTipo::TransferenciaSaida->value,
                'conta' => ContaCaixa::Feira->value,
                'valor' => -$centavos / 100,
            ]);
            CaixaMovimento::query()->create($dados + [
                'tipo' => CaixaTipo::TransferenciaEntrada->value,
                'conta' => $destino->value,
                'valor' => $centavos / 100,
            ]);

            if ($centavosTaxa > 0) {
                CaixaMovimento::query()->create(array_replace($dados, [
                    'tipo' => CaixaTipo::Ajuste->value,
                    'descricao' => 'Taxa da maquininha no repasse para '.$destino->label(),
                    'conta' => ContaCaixa::Feira->value,
                    'valor' => -$centavosTaxa / 100,
                ]));
            }
        });
    }
}
