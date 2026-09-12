<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-brand-text-muted">
        Obrigado por se cadastrar! Antes de começar, você pode confirmar seu e-mail clicando no link que acabamos de enviar? Caso não tenha recebido o e-mail, teremos prazer em enviar outro.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-brand-success">
            Um novo link de verificação foi enviado para o e-mail informado no cadastro.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button wire:click="sendVerification">
            Reenviar e-mail de verificação
        </x-primary-button>

        <button wire:click="logout" type="submit" class="underline text-sm text-brand-text-muted hover:text-brand-text rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-primary">
            Sair
        </button>
    </div>
</div>
