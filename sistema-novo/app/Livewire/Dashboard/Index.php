<?php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardPeriod;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public string $period = DashboardPeriod::MES_ATUAL;

    public ?string $customStart = null;

    public ?string $customEnd = null;

    /**
     * Preferencia de exibir/ocultar o saldo em caixa (indicador
     * financeiro sensivel) - persistida na sessao do usuario, entao a
     * escolha se mantem entre acessos (Etapa 5: pedido do usuario).
     */
    #[Session]
    public bool $showSaldoCaixa = true;

    public function toggleSaldoCaixa(): void
    {
        $this->showSaldoCaixa = ! $this->showSaldoCaixa;
    }

    public function updatedPeriod(): void
    {
        $this->dispatchChartUpdate();
    }

    public function updatedCustomStart(): void
    {
        if ($this->period === DashboardPeriod::PERSONALIZADO) {
            $this->dispatchChartUpdate();
        }
    }

    public function updatedCustomEnd(): void
    {
        if ($this->period === DashboardPeriod::PERSONALIZADO) {
            $this->dispatchChartUpdate();
        }
    }

    private function dispatchChartUpdate(): void
    {
        $service = app(DashboardService::class);
        $currentPeriod = DashboardPeriod::resolve($this->period, $this->customStart, $this->customEnd);
        $vendasPorDia = $service->vendasPorDia($currentPeriod);

        $this->dispatch(
            'dashboard-chart-updated',
            categories: $vendasPorDia->pluck('data'),
            values: $vendasPorDia->pluck('total'),
        );
    }

    public function render()
    {
        $service = app(DashboardService::class);
        $currentPeriod = DashboardPeriod::resolve($this->period, $this->customStart, $this->customEnd);

        $vendasPorDia = $service->vendasPorDia($currentPeriod);

        return view('livewire.dashboard.index', [
            'periodOptions' => DashboardPeriod::options(),
            'currentPeriod' => $currentPeriod,
            'faturamento' => $service->faturamento($currentPeriod),
            'vendasCount' => $service->vendasCount($currentPeriod),
            'ticketMedio' => $service->ticketMedio($currentPeriod),
            'lucroEstimado' => $service->lucroEstimado($currentPeriod),
            'clientesCadastrados' => $service->clientesCadastrados(),
            'valorPendente' => $service->valorPendente(),
            'saldoCaixa' => $service->saldoCaixa(),
            'materiaisEstoqueBaixo' => $service->materiaisEstoqueBaixo(),
            'materiaisEstoqueBaixoCount' => $service->materiaisEstoqueBaixoCount(),
            'vendasPorDia' => $vendasPorDia,
            'chartCategories' => $vendasPorDia->pluck('data'),
            'chartValues' => $vendasPorDia->pluck('total'),
            'produtosMaisVendidos' => $service->produtosMaisVendidos($currentPeriod),
            'vendasRecentes' => $service->vendasRecentes(),
        ]);
    }
}
