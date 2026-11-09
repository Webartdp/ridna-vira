<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;

class OrderPaid extends Mailable
{
    public function __construct(public Order $order) {}

    public function build(): self
    {
        return $this->subject('Оплату замовлення підтверджено — Рідна Віра')->view('mail.order-paid');
    }
}
