<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Нове замовлення {{ $orderNumber }} — Рідна Віра</title>
</head>
<body style="margin:0; background:#f6f2ea; color:#211a13; font-family:Arial,sans-serif; font-size:16px; line-height:1.5;">
    <div style="max-width:600px; margin:0 auto; padding:28px 16px;">
        <div style="padding:24px; background:#ffffff; border:1px solid #e3d8c6; border-radius:10px;">
            <h1 style="margin:0 0 14px; font-size:24px; line-height:1.25;">Нове замовлення на ваші вироби</h1>
            <p style="margin:0 0 20px;"><strong>{{ $orderNumber }}</strong> · {{ $paymentStatus }}</p>

            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f6f2ea;">
                        <th scope="col" style="padding:10px 8px; text-align:left;">Виріб</th>
                        <th scope="col" style="padding:10px 8px; text-align:center;">К-сть</th>
                        <th scope="col" style="padding:10px 8px; text-align:right;">Сума</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td style="padding:10px 8px; border-bottom:1px solid #e3d8c6;">
                                {{ $item['name'] }}
                                @if ($item['made_to_order'])
                                    <br><span style="color:#5d5143;">Під замовлення</span>
                                @endif
                            </td>
                            <td style="padding:10px 8px; border-bottom:1px solid #e3d8c6; text-align:center; white-space:nowrap;">
                                {{ $item['quantity'] }} шт.
                            </td>
                            <td style="padding:10px 8px; border-bottom:1px solid #e3d8c6; text-align:right; white-space:nowrap;">
                                {{ \App\Support\Money::display($item['total_minor']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin:18px 0 24px;"><strong>Сума майстра</strong> · {{ \App\Support\Money::display($totalMinor) }}</p>
            <p style="margin:0;">
                <a href="{{ $orderUrl }}" style="display:inline-block; padding:12px 16px; border-radius:6px; background:#342618; color:#ffffff; font-weight:bold; text-decoration:none;">Відкрити замовлення в кабінеті</a>
            </p>
        </div>
        <p style="margin:14px 0 0; color:#5d5143; font-size:14px; text-align:center;">Духовний центр «Рідна Віра»</p>
    </div>
</body>
</html>
