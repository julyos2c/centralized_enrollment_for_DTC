<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password — DTC Nexus</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F8FC; font-family:Arial, Helvetica, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#F4F8FC; padding:32px 16px;">
        <tr>
            <td align="center">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px; background-color:#FFFFFF; border-radius:12px; overflow:hidden; border:1px solid #E2E8F0;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#0F4CDB; padding:28px 32px;">
                            <span style="color:#FFFFFF; font-size:20px; font-weight:bold;">DTC Nexus</span><br>
                            <span style="color:#DCE6FB; font-size:12px; letter-spacing:0.5px; text-transform:uppercase;">
                                Danao Technological College
                            </span>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">

                            <p style="margin:0 0 4px; color:#0F4CDB; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                                Password Reset Request
                            </p>

                            <h1 style="margin:0 0 20px; color:#1E293B; font-size:22px;">
                                Reset your password
                            </h1>

                            <p style="margin:0 0 16px; color:#334155; font-size:15px; line-height:1.6;">
                                We received a request to reset the password for the DTC Nexus account
                                associated with <strong>{{ $email }}</strong>.
                                Click the button below to set a new password.
                            </p>

                            <p style="margin:0 0 24px; color:#334155; font-size:15px; line-height:1.6;">
                                If you did not request a password reset, you can safely ignore this
                                email — your password will not change.
                            </p>

                            {{-- CTA --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:8px; background-color:#FFC72C;">
                                        <a href="{{ $resetUrl }}"
                                           style="display:inline-block; padding:14px 28px; color:#1E293B; font-size:15px; font-weight:bold; text-decoration:none;">
                                            Reset My Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Expiry notice --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="background-color:#FFF7ED; border-radius:8px; border:1px solid #FED7AA; margin:0 0 24px;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <p style="margin:0; color:#92400E; font-size:13px; line-height:1.6;">
                                            <strong>⏱ This link expires in {{ $expiresInMinutes }} minutes.</strong>
                                            After that, you'll need to request a new one.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#94A3B8; font-size:12px; word-break:break-all;">
                                If the button above doesn't work, copy and paste this link into your browser:<br>
                                {{ $resetUrl }}
                            </p>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #E2E8F0;">
                            <p style="margin:0; color:#94A3B8; font-size:11px;">
                                © {{ date('Y') }} Danao Technological College — DTC Nexus: Admissions &amp; Enrollment System.<br>
                                This is an automated message; please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
