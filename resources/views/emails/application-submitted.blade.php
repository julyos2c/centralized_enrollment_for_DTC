<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your DTC Nexus Application Reference</title>
</head>
<body style="font-family:Arial, Helvetica, sans-serif; color:#1E293B;">
    <h1>DTC Nexus Application Received</h1>
    <p>Your application was received. Keep this reference number to check its status:</p>
    <p style="font-size:20px; font-weight:bold;">{{ $referenceNo }}</p>
    <p>You can check your application status using this reference number and the email address used for your application.</p>
    <p><a href="{{ route('public.application.status.form') }}">Check application status</a></p>
    <p>If you did not submit this application, contact the Registrar's Office.</p>
</body>
</html>
