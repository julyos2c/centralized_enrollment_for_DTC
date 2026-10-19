<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Requires Revision</title>
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

                            <p style="margin:0 0 4px; color:#B8860B; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                                Revision Required
                            </p>

                            <h1 style="margin:0 0 20px; color:#1E293B; font-size:22px;">
                                Your Application Needs a Small Update
                            </h1>

                            <p style="margin:0 0 16px; color:#334155; font-size:15px; line-height:1.6;">
                                Hello {{ $application->first_name }}, your DTC Nexus pre-enrollment
                                application (<strong>{{ $application->reference_no }}</strong>) has
                                been reviewed by the Registrar's Office and requires revision
                                before it can proceed.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF7E0; border:1px solid #F5DFA0; border-radius:8px; margin:0 0 24px;">
                                <tr>
                                    <td  style="padding:16px 20px;">
                                        <p style="margin:0 0 8px; color:#92650A; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.4px;">
                                            Registrar's Remarks
                                        </p>
                                        <p style="margin:0; color:#1E293B; font-size:14px; line-height:1.65;">
                                            {!! nl2br(e($application->remarks)) !!}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px; color:#334155; font-size:15px; line-height:1.6;">
                                Please review the requested corrections above. You may check the
                                current status of your application at any time — no account is
                                required — using your reference number and email address:
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                <tr>
                                    <td style="border-radius:8px; background-color:#0F4CDB;">
                                        <a href="{{ route('public.application.status.form') }}"
                                           style="display:inline-block; padding:14px 28px; color:#FFFFFF; font-size:15px; font-weight:bold; text-decoration:none;">
                                            Check Application Status
                                        </a>
                                    </td>
                                </tr>
                            </table>

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