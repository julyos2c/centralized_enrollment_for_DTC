<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Your DTC Nexus Application Reference</title>
</head>
<body style="margin:0; padding:0; background-color:#F3F6FB; font-family:Arial, Helvetica, sans-serif; color:#1E293B;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">Your DTC Nexus application was received. Keep your reference number to check its status.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3F6FB;">
        <tr>
            <td align="center" style="padding:36px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; background-color:#FFFFFF; border:1px solid #E2E8F0; border-radius:14px; overflow:hidden;">
                    <tr>
                        <td style="height:5px; background-color:#FFC72C; font-size:0; line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:25px 32px; background-color:#0F4CDB;">
                            <p style="margin:0; color:#FFFFFF; font-size:20px; line-height:1.3; font-weight:700;">DTC Nexus</p>
                            <p style="margin:4px 0 0; color:#DCE7FF; font-size:12px; line-height:1.5; letter-spacing:.5px;">DANAO TECHNOLOGICAL COLLEGE</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 32px 30px;">
                            <p style="margin:0 0 9px; color:#0F4CDB; font-size:12px; line-height:1.4; font-weight:700; letter-spacing:.7px; text-transform:uppercase;">Application received</p>
                            <h1 style="margin:0 0 14px; color:#17233C; font-size:25px; line-height:1.3; font-weight:700;">Thank you for applying</h1>
                            <p style="margin:0 0 24px; color:#475569; font-size:15px; line-height:1.7;">We’ve received your application. Save the reference number below; you’ll need it and the email address on your application to check your status.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 23px; background-color:#F4F7FC; border:1px solid #DCE5F2; border-radius:10px;">
                                <tr>
                                    <td align="center" style="padding:20px 18px 19px;">
                                        <p style="margin:0 0 8px; color:#64748B; font-size:11px; line-height:1.4; font-weight:700; letter-spacing:.7px; text-transform:uppercase;">Your application reference</p>
                                        <p style="margin:0; color:#173B86; font-size:24px; line-height:1.35; font-weight:700; letter-spacing:.5px; word-break:break-word;">{{ $referenceNo }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 19px; color:#475569; font-size:14px; line-height:1.7;">You can use the status page to follow your application and read any updates from the Registrar.</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" bgcolor="#0F4CDB" style="border-radius:8px;">
                                        <a href="{{ route('public.application.status.form') }}" style="display:inline-block; padding:13px 22px; border:1px solid #0F4CDB; border-radius:8px; color:#FFFFFF; font-size:14px; line-height:1.4; font-weight:700; text-decoration:none;">Check application status</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0; color:#64748B; font-size:13px; line-height:1.65;">If you did not submit this application, please contact the Registrar’s Office.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px; border-top:1px solid #E2E8F0; background-color:#FAFBFD;">
                            <p style="margin:0; color:#8491A5; font-size:11px; line-height:1.6;">&copy; {{ date('Y') }} Danao Technological College · DTC Nexus: Admissions &amp; Enrollment System</p>
                            <p style="margin:5px 0 0; color:#8491A5; font-size:11px; line-height:1.6;">This is an automated message. Please do not reply directly to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
