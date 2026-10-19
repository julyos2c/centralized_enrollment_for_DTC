<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Update</title>
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

                            <p style="margin:0 0 4px; color:#DC2626; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                                Application Update
                            </p>

                            <h1 style="margin:0 0 20px; color:#1E293B; font-size:22px;">
                                Application Not Approved
                            </h1>

                            <p style="margin:0 0 16px; color:#334155; font-size:15px; line-height:1.6;">
                                Hello {{ $application->first_name }}, after review by the
                                Registrar's Office, your DTC Nexus pre-enrollment application
                                (<strong>{{ $application->reference_no }}</strong>) was not
                                approved at this time.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FEF2F2; border:1px solid #FECACA; border-radius:8px; margin:0 0 24px;">
                                <tr>
                                    <td  style="padding:16px 20px;">
                                        <p style="margin:0 0 8px; color:#B91C1C; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.4px;">
                                            Reason
                                        </p>
                                        <p style="margin:0; color:#1E293B; font-size:14px; line-height:1.65;">
                                            {!! nl2br(e($application->remarks)) !!}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px; color:#334155; font-size:15px; line-height:1.6;">
                                If you have questions or would like clarification about this
                                decision, please contact the Registrar's Office directly. Your
                                reference number above will help them locate your application.
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