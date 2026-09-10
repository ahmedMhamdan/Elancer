<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationMailTest extends TestCase
{
    public function test_verification_mail_renders_both_formats_with_the_signed_link(): void
    {
        $this->freezeTime();
        config(['auth.verification.expire' => 25, 'mail.default' => 'array']);

        $user = User::factory()->make([
            'id' => 42,
            'name' => 'Sam <Example> & Co',
            'email' => 'sam@example.test',
        ]);
        $notification = new VerifyEmail;
        $message = $notification->toMail($user);
        $url = $message->viewData['verificationUrl'];

        $this->assertSame('Verify your email — Elancer', $message->subject);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->assertStringContainsString('/email/verify/42/'.sha1($user->email), $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(now()->addMinutes(25)->timestamp, (int) $query['expires']);

        $user->notify($notification);
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $html = $sent->getHtmlBody();
        $text = $sent->getTextBody();

        $this->assertStringContainsString('One click away.', $html);
        $this->assertStringContainsString('Hi Sam &lt;Example&gt; &amp; Co,', $html);
        $this->assertStringNotContainsString('Hi Sam <Example>', $html);
        $this->assertSame(2, substr_count($html, 'href="'.e($url).'"'));
        $this->assertStringContainsString('expires in 25 minutes', $html);
        $this->assertStringContainsString('Hi Sam <Example> & Co,', $text);
        $this->assertStringContainsString($url, $text);
        $this->assertStringContainsString('expires in 25 minutes', $text);
    }
}
