<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\PaymentAttempt;
use App\Payments\Gateways;
use App\Payments\SimulatorGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentController extends Controller
{
    public function __construct(private ContractFunding $funding, private Gateways $gateways) {}

    public function store(Request $request, Contract $contract): HttpResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        abort_unless($contract->client_id === $request->user()->id, 403);
        // Continuing an open attempt stays possible even if its provider was switched off since.
        $open = PaymentAttempt::query()->where('open_contract_id', $contract->id)->value('provider');
        $data = $request->validate(['provider' => ['required', Rule::in([...$this->gateways->available(), ...($open ? [$open] : [])])], 'client_token' => ['required', 'uuid']]);
        $attempt = $this->funding->start($contract, $data['provider'], $data['client_token']);
        if ($attempt->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => __('This payment request was already used. Reload before trying again.')]);
        }

        // The provider's checkout is another site, so leave the single-page app.
        return Inertia::location($this->funding->checkout($attempt));
    }

    public function simulator(Request $request, PaymentAttempt $attempt): Response
    {
        $this->simulated($request, $attempt);

        return Inertia::render('payments/simulator', ['attempt' => $attempt->summary(), 'open' => $attempt->status === 'pending',
            'contract' => ['id' => $attempt->contract_id, 'project_title' => $attempt->contract->agreement['project_title'] ?? null]]);
    }

    public function decide(Request $request, PaymentAttempt $attempt, SimulatorGateway $simulator): RedirectResponse
    {
        $this->simulated($request, $attempt);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'decline'])]]);
        if ($attempt->status === 'pending') {
            $simulator->decide($attempt, $data['decision'] === 'approve');
        }

        // Like a real provider, the decision only sends the payer back; verification happens there.
        return to_route('payments.return', $attempt);
    }

    public function returned(Request $request, PaymentAttempt $attempt): RedirectResponse
    {
        return $this->check($request, $attempt);
    }

    public function check(Request $request, PaymentAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->contract->contains($request->user()->id), 404);

        return $this->settle($attempt, fn () => $this->funding->reconcile($attempt));
    }

    public function cancel(Request $request, PaymentAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->contract->contains($request->user()->id), 404);
        abort_unless($attempt->payer_id === $request->user()->id, 403);

        return $this->settle($attempt, fn () => $this->funding->cancel($attempt));
    }

    /** @param  \Closure(): mixed  $action */
    private function settle(PaymentAttempt $attempt, \Closure $action): RedirectResponse
    {
        try {
            $action();
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('contracts.show', $attempt->contract_id)->withErrors(['payment' => __('The payment provider could not be reached. The payment status is unchanged; check again shortly.')]);
        }

        return to_route('contracts.show', $attempt->contract_id);
    }

    private function simulated(Request $request, PaymentAttempt $attempt): void
    {
        abort_unless($attempt->payer_id === $request->user()->id && $attempt->provider === 'simulator' && config('payments.simulator.enabled'), 404);
    }
}
