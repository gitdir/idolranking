<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.password_reset_subject') }}</title>
</head>
<body style="margin:0;padding:32px;background:#f7f8fc;color:#14224f;font-family:Arial,sans-serif;">
    <div style="max-width:520px;margin:0 auto;padding:28px;background:#fff;border:1px solid #f2ddea;border-radius:12px;">
        <h1 style="margin:0 0 22px;font-size:22px;">IDOL RANKING</h1>
        <p style="margin:0 0 18px;line-height:1.6;">{{ __('messages.password_reset_email_intro') }}</p>
        <p style="margin:0 0 20px;"><a href="{{ $resetUrl }}" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#ff379c;color:#fff;text-decoration:none;font-weight:700;">{{ __('messages.password_reset_email_button') }}</a></p>
        <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#68779e;">{{ __('messages.password_reset_email_fallback') }}</p>
        <p style="margin:0 0 18px;overflow-wrap:anywhere;font-size:12px;line-height:1.6;"><a href="{{ $resetUrl }}" style="color:#556dab;">{{ $resetUrl }}</a></p>
        <p style="margin:0;color:#68779e;font-size:13px;line-height:1.6;">{{ __('messages.password_reset_email_ignore') }}</p>
    </div>
</body>
</html>
