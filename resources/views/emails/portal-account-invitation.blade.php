<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>DTC Nexus portal setup</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#1e293b">
<table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="100%" style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:10px"><tr><td style="padding:28px">
<strong style="color:#0f4cdb">Danao Technological College</strong>
<h1 style="font-size:21px">Set up your DTC Nexus portal account</h1>
<p>Hello {{ $profile->first_name }},</p>
<p>The Registrar has invited you to set up portal access associated with student number {{ $profile->student_number }}. Use the button below to verify this email address and choose your own password.</p>
<p><a href="{{ $activationUrl }}" style="display:inline-block;padding:12px 18px;background:#0f4cdb;color:#fff;text-decoration:none;border-radius:6px">Set up account</a></p>
<p>This link expires in {{ ceil($expiresInMinutes / 60) }} hours and can be used once. If you did not expect this message, contact the Registrar.</p>
</td></tr></table></td></tr></table>
</body></html>
