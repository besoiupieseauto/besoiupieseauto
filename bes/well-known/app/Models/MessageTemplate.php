<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'channel',
        'code',
        'name',
        'template',
    ];

    /**
     * Default templates used when there is nothing in DB yet.
     * Keys are in the form "channel.code".
     */
    public const DEFAULTS = [
        // Internal orders – ready for pickup
        'whatsapp.order_pickup' => "Bună ziua {{client_name}},\n\n".
            "Comanda dvs. a ajuns la magazinul din {{store_name}} și poate fi ridicată. ✅\n\n".
            "💰 Total de plată: {{total}} RON\n".
            "📍 Locația noastră: {{store_url}}\n".
            "📞 Contact: 0726 498 573 / 0767 164 728\n".
            "🕒 Program: Luni–Vineri 9:00–17:00, Sâmbătă 9:00–14:00\n\n".
            "Vă așteptăm!\n".
            "Echipa Besoiu Piese Auto & Fun ☀️",

        // External orders – shipped with courier
        'whatsapp.external_order_shipped' => "Bună ziua {{client_name}},\n\n".
            "Comanda dvs. a fost expediată prin {{courier_name}} 🚚\n".
            "O veți primi în 1–2 zile lucrătoare.\n\n".
            "💰 Total de plată (inclusiv transport): {{total}} RON\n".
            "📦 Număr AWB: {{awb}}\n".
            "🔗 Urmăriți coletul aici: {{awb_url}}\n\n".
            "Echipa Besoiu Piese Auto & Fun ☀️",

        // Wishlist/Offer message ({{vin}} from supplier_saved_carts.vin)
        'whatsapp.wishlist_offer' => "Bună ziua 👋😊\n\n".
            "Vă trimitem mai jos oferta de preț pentru piesele auto solicitate 🔧🚗\n".
            "VIN : {{vin}}\n\n".
            "{{product_lines}}\n\n".
            "TOTAL = {{total}} RON\n\n".
            "Suntem la dispoziția dumneavoastră oricând 📲🙂\n".
            "Vă mulțumim și așteptăm cu interes comanda dumneavoastră 🤝✨\n\n".
            "Besoiu Piese Auto & Fun 🚘🔩",

        // SMS templates
        // Internal orders - with total
        'sms.order_pickup_with_total' => "Buna ziua {{client_name}}, comanda a sosit la magazinul din {{store_name}}. Total: {{total}} RON. Locatie: {{store_url}}. Tel: 0726498573",

        // Internal orders - without total (for Avans status)
        'sms.order_pickup_no_total' => "Buna ziua {{client_name}}, comanda a sosit la magazinul din {{store_name}}. Locatie: {{store_url}}. Tel: 0726498573",

        // External orders - Sameday
        'sms.external_order_sameday' => "Buna ziua, comanda a fost trimisa prin Sameday. Total: {{total}} RON. Track: https://sameday.ro/#awb={{awb}}",

        // External orders - FanCourier
        'sms.external_order_fancourier' => "Buna ziua, comanda a fost trimisa prin FanCourier. Total: {{total}} RON. Track: http://www.fancourier.ro/awb-tracking/?tracking={{awb}}",
    ];

    public static function getTemplate(string $code, string $channel = 'whatsapp'): string
    {
        $row = static::where('channel', $channel)
            ->where('code', $code)
            ->first();

        if ($row && $row->template) {
            return $row->template;
        }

        $key = "{$channel}.{$code}";

        return static::DEFAULTS[$key] ?? '';
    }

    /**
     * Return all templates for a channel, merged with defaults.
     *
     * @return array<string,MessageTemplate>
     */
    public static function allWithDefaults(string $channel = 'whatsapp'): array
    {
        $dbTemplates = static::where('channel', $channel)->get()->keyBy('code');

        $result = [];

        foreach (static::DEFAULTS as $key => $defaultText) {
            [$tplChannel, $code] = explode('.', $key, 2);

            if ($tplChannel !== $channel) {
                continue;
            }

            if (isset($dbTemplates[$code])) {
                $result[$code] = $dbTemplates[$code];
                continue;
            }

            $result[$code] = new static([
                'channel'  => $channel,
                'code'     => $code,
                'name'     => ucwords(str_replace('_', ' ', $code)),
                'template' => $defaultText,
            ]);
        }

        return $result;
    }
}


