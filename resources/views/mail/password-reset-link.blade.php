<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IDOL RANKING - Reset Your Password</title>
</head>
<body style="margin:0;padding:32px;background:#f7f8fc;color:#14224f;font-family:Arial,sans-serif;">
    <div style="max-width:520px;margin:0 auto;padding:28px;background:#fff;border:1px solid #f2ddea;border-radius:12px;">
        <h1 style="margin:0 0 22px;font-size:22px;">IDOL RANKING</h1>
        <p style="margin:0 0 18px;line-height:1.6;">We received a request to reset your password. Use the button below to choose a new password. This link expires in 60 minutes.</p>
        <p style="margin:0 0 20px;"><a href="{{ $resetUrl }}" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#ff379c;color:#fff;text-decoration:none;font-weight:700;">Reset Password</a></p>
        <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#68779e;">If the button does not work, copy and paste this link into your browser:</p>
        <p style="margin:0 0 18px;overflow-wrap:anywhere;font-size:12px;line-height:1.6;"><a href="{{ $resetUrl }}" style="color:#556dab;">{{ $resetUrl }}</a></p>
        <p style="margin:0;color:#68779e;font-size:13px;line-height:1.6;">If you did not request a password reset, you can ignore this email.</p>
    </div>
</body>
</html>
