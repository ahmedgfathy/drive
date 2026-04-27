<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Share Received</title>
</head>
<body style="font-family: Arial, sans-serif; color: #10253d; line-height: 1.5;">
    <h2 style="margin-bottom: 12px;">You have a new file sharing on PMS Drive</h2>

    <p>Hello {{ $share->target_name ?: $share->targetUser?->full_name ?: $share->targetUser?->name ?: 'there' }},</p>

    <p>
        <strong>{{ $share->grantedBy?->full_name ?: $share->grantedBy?->name ?: 'A PMS Drive user' }}</strong>
        shared <strong>{{ $shareTitle }}</strong> with you on PMS Drive.
        @if($share->channel === 'external')
            You can open it without signing in.
        @endif
    </p>

    <p>You have a new file sharing with you on the PMS Drive application.</p>

    <p>
        Permission: <strong>{{ strtoupper($share->permission) }}</strong><br>
        @if($share->expires_at)
            Expires: <strong>{{ $share->expires_at->format('Y-m-d H:i') }}</strong><br>
        @endif
    </p>

    <p>
        <a href="{{ $shareUrl }}" style="display: inline-block; padding: 10px 16px; background: #12355a; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Open Shared Item
        </a>
    </p>

    <p>
        <a href="{{ $loginUrl }}" style="display: inline-block; padding: 10px 16px; background: #eef4fb; color: #12355a; text-decoration: none; border-radius: 6px; border: 1px solid #c9d8e8;">
            Open PMS Drive Login
        </a>
    </p>

    <p style="margin-top: 16px; color: #5f7286;">
        Shared item link: <a href="{{ $shareUrl }}">{{ $shareUrl }}</a><br>
        Login link: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
    </p>

    <p style="margin-top: 24px; color: #5f7286;">
        This message was sent automatically by PMS Drive.
    </p>
</body>
</html>
