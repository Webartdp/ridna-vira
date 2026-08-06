<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Нове звернення до Управи</title>
</head>
<body style="margin:0;padding:24px;background:#f3efe7;color:#1c0803;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;background:#ffffff;border:1px solid #ded5c5;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="padding:26px 30px;background:#1c0803;color:#ffffff;">
                        <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#d2a24a;">Духовний центр «Рідна Віра»</div>
                        <h1 style="margin:8px 0 0;font-size:24px;line-height:1.25;">Нове звернення до Управи</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                            <tr>
                                <td style="width:180px;padding:8px 10px 8px 0;border-bottom:1px solid #eee6da;color:#726755;vertical-align:top;">Тема</td>
                                <td style="padding:8px 0;border-bottom:1px solid #eee6da;font-weight:700;">{{ $formData['topic_label'] }}</td>
                            </tr>
                            <tr>
                                <td style="padding:8px 10px 8px 0;border-bottom:1px solid #eee6da;color:#726755;vertical-align:top;">Ім’я та прізвище</td>
                                <td style="padding:8px 0;border-bottom:1px solid #eee6da;">{{ $formData['name'] }}</td>
                            </tr>
                            <tr>
                                <td style="padding:8px 10px 8px 0;border-bottom:1px solid #eee6da;color:#726755;vertical-align:top;">Електронна пошта</td>
                                <td style="padding:8px 0;border-bottom:1px solid #eee6da;"><a href="mailto:{{ $formData['email'] }}" style="color:#8c5e13;">{{ $formData['email'] }}</a></td>
                            </tr>
                            @if (!empty($formData['phone']))
                                <tr>
                                    <td style="padding:8px 10px 8px 0;border-bottom:1px solid #eee6da;color:#726755;vertical-align:top;">Телефон</td>
                                    <td style="padding:8px 0;border-bottom:1px solid #eee6da;">{{ $formData['phone'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($formData['city']))
                                <tr>
                                    <td style="padding:8px 10px 8px 0;border-bottom:1px solid #eee6da;color:#726755;vertical-align:top;">Місто / область</td>
                                    <td style="padding:8px 0;border-bottom:1px solid #eee6da;">{{ $formData['city'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($formData['organization']))
                                <tr>
                                    <td style="padding:8px 10px 8px 0;border-bottom:1px solid #eee6da;color:#726755;vertical-align:top;">Громада / організація</td>
                                    <td style="padding:8px 0;border-bottom:1px solid #eee6da;">{{ $formData['organization'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:8px 10px 8px 0;color:#726755;vertical-align:top;">Надіслано</td>
                                <td style="padding:8px 0;">{{ $formData['submitted_at'] }}</td>
                            </tr>
                        </table>

                        <div style="margin-top:24px;padding:20px;border-left:4px solid #c28b2d;background:#f8f4eb;line-height:1.6;white-space:normal;">
                            {!! nl2br(e($formData['message'])) !!}
                        </div>

                        <p style="margin:24px 0 0;color:#726755;font-size:12px;line-height:1.5;">
                            Джерело: {{ $formData['source_url'] }}<br>
                            IP: {{ $formData['ip'] }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
