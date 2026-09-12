<?php

namespace App\Livewire\Perdas;

use App\Models\Perda;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render(): View
    {
        $perdas = Perda::query()->with('material')->latest('data')->limit(20)->get();

        return view('livewire.perdas.index', compact('perdas'));
    }
}
