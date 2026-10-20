<?php

use App\Actions\Contracts\CancelContract;
use App\Actions\Demo\DemoBaseline;
use App\Actions\Demo\DemoResetRefused;
use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\ContractCancellation;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Passport\Client;

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

// Plan 027: the demo accounts' starting point and the reset to it. Neither touches a row outside the demo.
Artisan::command('demo:save', function () {
    try {
        DemoBaseline::save(null);
    } catch (DemoResetRefused $refusal) {
        $this->error('Nothing was saved: '.$refusal->getMessage());

        return 1;
    }
    $this->info('The demo accounts as they are now are the starting point.');

    return 0;
})->purpose('Save the demo accounts as they are now as the starting point a reset returns to');

Artisan::command('demo:reset {--due : Only when the demo has been left changed and quiet, or has stayed changed for long}', function () {
    if ($this->option('due')) {
        DemoBaseline::restoreWhenDue();

        return 0;
    }
    try {
        $counts = DemoBaseline::restore(null);
    } catch (DemoResetRefused $refusal) {
        $this->error('Nothing was reset: '.$refusal->getMessage());

        return 1;
    }
    $this->info('Removed '.$counts['removed'].', returned '.$counts['returned'].', restored '.$counts['restored'].' rows.');

    return 0;
})->purpose('Return the demo accounts to the saved starting point');

// Covers a host that stays awake; a sleeping one resets when the next visitor enters.
Schedule::command('demo:reset --due')->everyTenMinutes()->withoutOverlapping();

// Plan 033: expired and revoked OAuth tokens and codes go after a day, and so do apps that registered
// themselves but were never approved by anyone (registration is open to any app that asks).
Artisan::command('oauth:prune', function () {
    $this->call('passport:purge', ['--hours' => 24]);
    $clients = Client::query()->where('created_at', '<', now()->subDay())->doesntHave('tokens')->doesntHave('authCodes')->delete();
    $this->info('Removed '.$clients.' unused apps.');
})->purpose('Remove expired OAuth tokens and apps that were never connected');

Schedule::command('oauth:prune')->daily()->withoutOverlapping();
