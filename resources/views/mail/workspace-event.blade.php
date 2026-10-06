@php($rtl = app()->getLocale() === 'ar')
@php($start = $rtl ? 'right' : 'left')
{{-- Arabic: a font with proper Arabic shapes, no negative letter spacing (it breaks joined letters), and each
     member-entered text keeps its own direction inside the right-to-left layout. --}}
@php($font = $rtl ? "Tahoma, 'Segoe UI', Arial, sans-serif" : 'Arial, Helvetica, sans-serif')
@php($own = fn (?string $text) => preg_match('/\p{Arabic}/u', (string) $text) ? 'rtl' : 'ltr')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-outer { padding: 24px 12px !important; }
            .email-content { padding: 32px 24px !important; }
            .email-title { font-size: 24px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f6f5ef; color: #202d23; font-family: {!! $font !!}; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all;">{{ $sentence }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f6f5ef">
        <tr>
            <td class="email-outer" align="center" style="padding: 40px 16px;">
                <!--[if mso]><table role="presentation" width="560" align="center"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 560px;">
                    <tr>
                        <td align="center" style="padding: 0 0 28px;">
                            <a href="{{ $homeUrl }}" dir="ltr" style="font-family: Arial, Helvetica, sans-serif; color: #202d23; font-size: 26px; font-weight: bold; letter-spacing: -1px; text-decoration: none;">Elancer<span style="color: #315c3d;">.</span></a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #fffefa; border: 1px solid #dce2d5; border-radius: 16px; overflow: hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td height="6" bgcolor="#57d278" style="height: 6px; font-size: 0; line-height: 0; border-radius: 16px 16px 0 0;">&nbsp;</td></tr>
                                <tr>
                                    <td class="email-content" dir="{{ $rtl ? 'rtl' : 'ltr' }}" align="{{ $start }}" style="padding: 40px; text-align: {{ $start }}; font-family: {!! $font !!};">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="{{ $start }}">
                                            <tr><td bgcolor="#e5eddf" style="padding: 8px 12px; border-radius: 6px; color: #315c3d; font-size: {{ $rtl ? 14 : 12 }}px; font-weight: bold; font-family: {!! $font !!};">{{ $label }}</td></tr>
                                        </table>
                                        <div style="clear: both; font-size: 0; line-height: 0;">&nbsp;</div>
                                        <h1 class="email-title" style="margin: 24px 0 20px; color: #202d23; font-size: 28px; font-weight: bold; letter-spacing: {{ $rtl ? '0' : '-0.5px' }}; line-height: {{ $rtl ? '1.45' : '1.25' }}; overflow-wrap: anywhere;"><span dir="{{ $own($title) }}" style="unicode-bidi: isolate;">{{ $title }}</span></h1>
                                        <p style="margin: 0 0 12px; color: #202d23; font-size: 16px; line-height: 1.7; overflow-wrap: anywhere;">{!! __('Hi :name,', ['name' => '<span dir="'.$own($recipientName).'" style="unicode-bidi: isolate;">'.e($recipientName).'</span>']) !!}</p>
                                        <p style="margin: 0 0 28px; color: #626f60; font-size: 16px; line-height: {{ $rtl ? '1.9' : '1.7' }}; overflow-wrap: anywhere;">@if ($actor)<strong dir="{{ $own($actor) }}" style="color: #202d23; unicode-bidi: isolate;">{{ $actor }}</strong> @endif{{ $fragment }}@if ($project) <strong dir="{{ $own($project) }}" style="color: #202d23; unicode-bidi: isolate;">{{ $project }}</strong>@endif</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="{{ $start }}">
                                            <tr>
                                                <td align="center" bgcolor="#315c3d" style="border-radius: 8px; mso-padding-alt: 16px 28px;">
                                                    <a href="{{ $actionUrl }}" style="display: inline-block; border: 1px solid #315c3d; border-radius: 8px; padding: 16px 28px; color: #fffefa; font-size: 16px; font-weight: bold; line-height: 20px; text-decoration: none; mso-padding-alt: 0; font-family: {!! $font !!};">
                                                        <!--[if mso]><i style="mso-font-width: 140%; mso-text-raise: 24pt;" hidden>&emsp;</i><span style="mso-text-raise: 12pt;"><![endif]-->{{ $actionText }}<!--[if mso]></span><i style="mso-font-width: 140%;" hidden>&emsp;&#8203;</i><![endif]-->
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <div style="clear: both; font-size: 0; line-height: 0;">&nbsp;</div>
                                        <p style="margin: 28px 0 0; color: #202d23; font-size: 14px; line-height: 1.7;"><strong>{{ __('The Elancer team') }}</strong></p>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding-top: 28px; text-align: {{ $start }};">
                                                    <p style="margin: 0 0 10px; border-top: 1px solid #dce2d5; padding-top: 24px; color: #626f60; font-size: 12px; line-height: 1.7;">{{ __('Button not working? Copy and paste this link into your browser:') }}</p>
                                                    <a href="{{ $actionUrl }}" dir="ltr" style="color: #315c3d; font-size: 12px; line-height: 1.7; text-decoration: underline; overflow-wrap: anywhere; word-break: break-all;">{{ $actionUrl }}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="padding: 24px 16px 0; font-family: {!! $font !!};">
                            <p style="margin: 0; color: #626f60; font-size: 12px; line-height: 1.7;">{{ __('You can choose which emails you receive under Settings, Notifications.') }}</p>
                            <p style="margin: 6px 0 0; font-size: 12px; line-height: 1.7;"><a href="{{ $preferencesUrl }}" style="color: #315c3d; text-decoration: underline;">{{ __('Notification settings') }}</a></p>
                            <p style="margin: 12px 0 0; color: #626f60; font-size: 12px; line-height: 1.7;" dir="ltr">&copy; {{ date('Y') }} Elancer</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
