<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email copy of a stored workspace event. Queued on its own so a slow or
 * unavailable mail service never delays the bell or the member's action.
 */
class WorkspaceEventMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $kind, private string $href, private ?string $title = null, private ?string $actor = null)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Rendered in the recipient's saved language. */
    public function toMail(object $notifiable): MailMessage
    {
        $sentence = trim(implode(' ', array_filter([$this->actor, self::sentence($this->kind), $this->title])));
        $subject = __('Elancer: :title', ['title' => $this->title ?? __('workspace update')]);

        // Same layout as the verification email (resources/views/mail/verify-email.blade.php).
        return (new MailMessage)
            ->subject($subject)
            ->line($sentence)
            ->action(__('Open in Elancer'), url($this->href))
            ->line(__('You can choose which emails you receive under Settings, Notifications.'))
            ->view(['html' => 'mail.workspace-event', 'text' => 'mail.workspace-event-text'], [
                'subject' => $subject,
                'label' => match ((new WorkspaceEvent($this->kind, $this->href))->category()) {
                    'messages' => __('New message'),
                    'hiring' => __('Hiring update'),
                    default => __('Contract update'),
                },
                'title' => $this->title ?? __('workspace update'),
                'recipientName' => $notifiable->name ?? '',
                'sentence' => $sentence,
                'actor' => $this->actor,
                'fragment' => self::sentence($this->kind),
                'project' => $this->title,
                'actionText' => __('Open in Elancer'),
                'actionUrl' => url($this->href),
                'preferencesUrl' => route('notification-preferences.edit'),
                'homeUrl' => route('home'),
            ]);
    }

    /** The same wording as the bell, kept as translatable fragments. */
    public static function sentence(string $kind): string
    {
        return match ($kind) {
            'invitation_received' => __('invited you to apply to'),
            'proposal_received' => __('sent a proposal for'),
            'offer_received' => __('sent you a final offer for'),
            'offer_accepted' => __('accepted your offer for'),
            'offer_declined' => __('declined your offer for'),
            'offer_changes_requested' => __('requested changes to your offer for'),
            'offer_withdrawn' => __('withdrew the offer for'),
            'contract_funded' => __('funded the contract for'),
            'payment_verified' => __('Your test payment was verified for'),
            'message_received' => __('sent you a message about'),
            'delivery_submitted' => __('submitted a delivery for'),
            'revision_requested' => __('requested a revision for'),
            'contract_completed' => __('approved your delivery and completed'),
            'review_received' => __('left you a review for'),
            'cancellation_requested' => __('asked to cancel the contract for'),
            'cancellation_accepted' => __('accepted cancelling the contract for'),
            'cancellation_declined' => __('declined cancelling the contract for'),
            'cancellation_withdrawn' => __('withdrew the request to cancel the contract for'),
            'contract_cancelled' => __('cancelled the unfunded contract for'),
            'contract_refunded' => __('The test payment was refunded and the contract cancelled for'),
            'amendment_proposed' => __('proposed a change to the contract for'),
            'amendment_accepted' => __('accepted your proposed change to the contract for'),
            'amendment_declined' => __('declined your proposed change to the contract for'),
            'amendment_withdrawn' => __('withdrew a proposed change to the contract for'),
            default => __('Workspace update for'),
        };
    }
}
