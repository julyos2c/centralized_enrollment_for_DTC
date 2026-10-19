<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approved</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F8FC; font-family:Arial, Helvetica, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F4F8FC; padding:32px 16px;">
        <tr>
            <td align="center">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#FFFFFF; border-radius:12px; overflow:hidden; border:1px solid #E2E8F0;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#0F4CDB; padding:28px 32px;">
                            <span style="color:#FFFFFF; font-size:20px; font-weight:bold;">DTC Nexus</span><br>
                            <span style="color:#DCE6FB; font-size:12px; letter-spacing:0.5px; text-transform:uppercase;">Danao Technological College</span>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">

                            <p style="margin:0 0 4px; color:#0F4CDB; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                                Application Approved
                            </p>

                            <h1 style="margin:0 0 20px; color:#1E293B; font-size:22px;">
                                Congratulations, {{ $application->first_name }}!
                            </h1>

                            <p style="margin:0 0 16px; color:#334155; font-size:15px; line-height:1.6;">
                                Your DTC Nexus pre-enrollment application
                                (<strong>{{ $application->reference_no }}</strong>) has been reviewed
                                and <strong>approved</strong>. An account has been created for you
                                on DTC Nexus, the Admissions &amp; Enrollment System.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F4F8FC; border-radius:8px; margin:0 0 24px;">
                                <tr>
                                    <td  style="padding:16px 20px;">
                                        <p style="margin:0 0 4px; color:#64748B; font-size:12px; text-transform:uppercase; letter-spacing:0.4px;">
                                            Account Email
                                        </p>
                                        <p style="margin:0; color:#1E293B; font-size:15px; font-weight:bold;">
                                            {{ $application->email }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px; color:#334155; font-size:15px; line-height:1.6;">
                                To finish setting up your account, please create your own password
                                using the secure link below. For your security, we do not send or
                                store a password on your behalf.
                            </p>

                            {{-- CTA --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:8px; background-color:#FFC72C;">
                                        <a href="{{ $setupUrl }}"
                                           style="display:inline-block; padding:14px 28px; color:#1E293B; font-size:15px; font-weight:bold; text-decoration:none;">
                                            Set Your Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; color:#64748B; font-size:13px; line-height:1.6;">
                                This link is for initial account activation only and will expire
                                in {{ $expiresInMinutes }} minutes. If it expires before you use it,
                                please contact the Registrar's Office to request a new one.
                            </p>

                            <p style="margin:0; color:#94A3B8; font-size:12px; word-break:break-all;">
                                If the button above doesn't work, copy and paste this link into your browser:<br>
                                {{ $setupUrl }}
                            </p>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #E2E8F0;">
                            <p style="margin:0; color:#94A3B8; font-size:11px;">
                                © {{ date('Y') }} Danao Technological College — DTC Nexus: Admissions &amp; Enrollment System.
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