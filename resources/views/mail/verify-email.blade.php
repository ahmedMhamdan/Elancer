<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your email — Elancer</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-outer { padding: 24px 12px !important; }
            .email-content { padding: 32px 24px !important; }
            .email-title { font-size: 32px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f6f5ef; color: #202d23; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all;">One click to verify your email and get started on Elancer.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f6f5ef">
        <tr>
            <td class="email-outer" align="center" style="padding: 40px 16px;">
                <!--[if mso]><table role="presentation" width="560" align="center"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 560px;">
                    <tr>
                        <td align="center" style="padding: 0 0 28px;">
                            <a href="{{ $homeUrl }}" style="color: #202d23; font-size: 26px; font-weight: bold; letter-spacing: -1px; text-decoration: none;">Elancer<span style="color: #315c3d;">.</span></a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #fffefa; border: 1px solid #dce2d5; border-radius: 16px; overflow: hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td height="6" bgcolor="#57d278" style="height: 6px; font-size: 0; line-height: 0; border-radius: 16px 16px 0 0;">&nbsp;</td></tr>
                                <tr>
                                    <td class="email-content" style="padding: 40px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr><td bgcolor="#e5eddf" style="padding: 8px 12px; border-radius: 6px; color: #315c3d; font-size: 12px; font-weight: bold;">Email verification</td></tr>
                                        </table>
                                        <h1 class="email-title" style="margin: 24px 0 20px; color: #202d23; font-size: 38px; font-weight: bold; letter-spacing: -1.5px; line-height: 1.12;">Your next chapter.<br>One click away.</h1>
                                        <p style="margin: 0 0 12px; color: #202d23; font-size: 16px; line-height: 1.7; overflow-wrap: anywhere;">Hi {{ $recipientName }},</p>
                                        <p style="margin: 0 0 28px; color: #626f60; font-size: 16px; line-height: 1.7;">Welcome to Elancer. Your skills, ideas, and next opportunity have a place here. Verify your email to get started.</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td align="center" bgcolor="#315c3d" style="border-radius: 8px; mso-padding-alt: 16px 28px;">
                                                    <a href="{{ $verificationUrl }}" style="display: inline-block; border: 1px solid #315c3d; border-radius: 8px; padding: 16px 28px; color: #fffefa; font-size: 16px; font-weight: bold; line-height: 20px; text-decoration: none; mso-padding-alt: 0;">
                                                        <!--[if mso]><i style="mso-font-width: 140%; mso-text-raise: 24pt;" hidden>&emsp;</i><span style="mso-text-raise: 12pt;"><![endif]-->Verify my email<!--[if mso]></span><i style="mso-font-width: 140%;" hidden>&emsp;&#8203;</i><![endif]-->
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin: 16px 0 28px; color: #626f60; font-size: 13px; line-height: 1.6;">This link expires in {{ $expiresInMinutes }} minutes. If it expires, request a fresh link from the verification page.</p>
                                        <p style="margin: 0; color: #202d23; font-size: 14px; line-height: 1.7;">See you inside,<br><strong>The Elancer team</strong></p>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding-top: 28px;">
                                                    <p style="margin: 0 0 10px; border-top: 1px solid #dce2d5; padding-top: 24px; color: #626f60; font-size: 12px; line-height: 1.7;">Button not working? Copy and paste this link into your browser:</p>
                                                    <a href="{{ $verificationUrl }}" style="color: #315c3d; font-size: 12px; line-height: 1.7; text-decoration: underline; overflow-wrap: anywhere; word-break: break-all;">{{ $verificationUrl }}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 24px 16px 0;">
                            <p style="margin: 0; color: #626f60; font-size: 12px; line-height: 1.7;">If you didn’t create an Elancer account, you can ignore this email.</p>
                            <p style="margin: 12px 0 0; color: #626f60; font-size: 12px; line-height: 1.7;">&copy; {{ date('Y') }} Elancer</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>