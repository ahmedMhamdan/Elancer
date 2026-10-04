<?php

use App\Actions\Contracts\CancelContract;
use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\ContractCancellation;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Covers a payer who never returns from checkout and a refund the provider had not finished.
Artisan::command('payments:reconcile', function (ContractFunding $funding, CancelContract $cancellation) {
    $attempts = PaymentAttempt::query()->where('status', 'pending')->whereNotNull('provider_reference')->orderBy('id')->get();
    foreach ($attempts as $attempt) {
        try {
            $funding->reconcile($attempt);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
    $refunds = ContractCancellation::query()->whereNotNull('open_contract_id')->where('status', 'accepted')->where('refund_status', 'pending')->orderBy('id')->get();
    foreach ($refunds as $refund) {
        $cancellation->refund(Contract::query()->findOrFail($refund->contract_id));
    }
    $this->info('Checked '.$attempts->count().' payment attempts and '.$refunds->count().' refunds.');
})->purpose('Ask the payment providers about pending payments and refunds');

Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
