<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New registration request</title>
</head>
<body style="margin:0;padding:32px;background:#f7f8fc;color:#14224f;font-family:Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;padding:28px;background:#fff;border:1px solid #e7eaf2;border-radius:12px;">
        <h1 style="margin:0 0 22px;font-size:22px;">New IdolRanking registration request</h1>
        <p style="margin:0 0 12px;line-height:1.6;"><strong>Request type:</strong> {{ $requestType === 'group' ? 'Group' : 'Idol' }}</p>
        <p style="margin:0 0 12px;line-height:1.6;"><strong>English name:</strong> {{ $englishName }}</p>
        <p style="margin:0 0 12px;line-height:1.6;"><strong>Applicant:</strong> {{ $applicantNickname }}</p>
        <p style="margin:0 0 20px;line-height:1.6;"><strong>Submitted at:</strong> {{ $submittedAt }}</p>
        <p style="margin:0;line-height:1.6;">
            <a href="{{ $reviewUrl }}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#24386f;color:#fff;text-decoration:none;font-weight:700;">Review request</a>
        </p>
        <p style="margin:16px 0 0;overflow-wrap:anywhere;font-size:12px;line-height:1.6;">
            <a href="{{ $reviewUrl }}" style="color:#556dab;">{{ $reviewUrl }}</a>
        </p>
    </div>
</body>
</html>
