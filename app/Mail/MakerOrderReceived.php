<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class MakerOrderReceived extends Mailable
{
    /**
     * @param  list<array{name: string, quantity: int, made_to_order: bool, total_minor: int}>  $items
     * @param  array<string, string>  $deliveryDetails
     */
    public function __construct(
        public string $orderNumber,
        public string $paymentStatus,
        public string $orderUrl,
        public array $items,
        public int $totalMinor,
        public array $deliveryDetails = [],
    ) {}

    public function build(): self
    {
        return $this->subject('Нове замовлення '.$this->orderNumber.' — Рідна Віра')
            ->view('mail.maker-order-received');
    }
}
