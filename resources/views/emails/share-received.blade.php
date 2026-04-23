<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Share Received</title>
</head>
<body style="font-family: Arial, sans-serif; color: #10253d; line-height: 1.5;">
    <h2 style="margin-bottom: 12px;">A new item was shared with you</h2>

    <p>Hello {{ $share->targetUser?->full_name ?: $share->targetUser?->name ?: 'there' }},</p>

    <p>
        <strong>{{ $share->grantedBy?->full_name ?: $share->grantedBy?->name ?: 'A PMS Drive user' }}</strong>
        shared <strong>{{ $shareTitle }}</strong> with you in PMS Drive.
    </p>

    <p>
        Permission: <strong>{{ strtoupper($share->permission) }}</strong><br>
        @if($share->expires_at)
            Expires: <strong>{{ $share->expires_at->format('Y-m-d H:i') }}</strong><br>
        @endif
    </p>

    <p>
        <a href="{{ $shareUrl }}" style="display: inline-block; padding: 10px 16px; background: #12355a; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Open PMS Drive
        </a>
    </p>

    <p style="margin-top: 24px; color: #5f7286;">
        This message was sent automatically by PMS Drive.
    </p>
</body>
</html>
