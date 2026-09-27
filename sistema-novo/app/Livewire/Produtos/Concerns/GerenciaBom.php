<?php

namespace App\Livewire\Produtos\Concerns;

use App\Models\Material;
use App\Models\Produto;
use Illuminate\Support\Collection;

trait GerenciaBom
{
    public function adicionarMaterial(): void
    {
        $this->form->bom[] = [
            'material_id' => null,
            'quantidade' => '1',
        ];
    }

    public function removerMaterial(int $index): void
    {
        if (! array_key_exists($index, $this->form->bom)) {
            return;
        }

        unset($this->form->bom[$index]);
        $this->form->bom = array_values($this->form->bom);
    }

    public function aplicarPrecoSugerido(): void
    {
        if (trim($this->form->margem_lucro) === '') {
            $this->addError('form.margem_lucro', 'Informe a porcentagem para calcular o preço.');

            return;
        }

        $this->form->validateOnly('margem_lucro');

        $custoBom = $this->form->custoBom($this->materiaisDisponiveis());

        if ($custoBom <= 0) {
            $this->addError('form.bom', 'Adicione materiais com custo médio para calcular o preço.');

            return;
        }

        $precoSugerido = $this->form->precoSugerido($custoBom);
        $this->form->preco_venda = number_format((float) $precoSugerido, 2, ',', '');
    }

    /**
     * @return Collection<int, Material>
     */
    protected function materiaisDisponiveis(): Collection
    {
        return Material::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'unidade_base', 'custo_medio']);
    }

    protected function salvarBom(Produto $produto): void
    {
        $produto->bom()->delete();

        if ($this->form->bom !== []) {
            $produto->bom()->createMany($this->form->bomNormalizado());
        }
    }

    /**
     * @param  Collection<int, Material>  $materiais
     * @return array{custoBom: float, precoSugerido: float|null}
     */
    protected function resumoPreco(Collection $materiais): array
    {
        $custoBom = $this->form->custoBom($materiais);

        return [
            'custoBom' => $custoBom,
            'precoSugerido' => $this->form->precoSugerido($custoBom),
        ];
    }
}
