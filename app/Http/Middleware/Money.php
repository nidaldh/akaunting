<?php

namespace App\Http\Middleware;

use Closure;
use OutOfBoundsException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnexpectedValueException;

class Money
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (($request->method() != 'POST') && ($request->method() != 'PATCH')) {
            return $next($request);
        }

        $currency_code = setting('default.currency');

        if ($request->has('currency_code')) {
            $currency_code = $request->get('currency_code');
        }

        $parameters = [
            'amount',
            'sale_price',
            'purchase_price',
            'opening_balance',
        ];

        foreach ($parameters as $parameter) {
            if (!$request->has($parameter)) {
                continue;
            }

            $money_format = $request->get($parameter);

            if ($parameter == 'sale_price' || $parameter == 'purchase_price') {
                $money_format = Str::replaceFirst(',', '.', $money_format);
            }

            $amount = $this->getAmount($money_format, $currency_code);

            $request->request->set($parameter, $amount);
        }

        $document_number = $request->get('document_number');
        $items = $request->get('items');

        if (isset($document_number) || !empty($items)) {
            if (!empty($items)) {
                foreach ($items as $key => $item) {
                    if (!isset($item['price'])) {
                        continue;
                    }

                    $amount = $item['price'];

                    if (strpos($item['price'], config('money.' . $currency_code . '.symbol')) !== false) {
                        $amount = $this->getAmount($item['price'], $currency_code);
                    }

                    $items[$key]['price'] = $amount;
                }

                $request->request->set('items', $items);
            }
        }

        return $next($request);
    }

    protected function getAmount($money_format, $currency_code)
    {
        try {
            if (config('money.' . $currency_code . '.decimal_mark') !== '.') {
                $money_format = Str::replaceFirst('.', config('money.' . $currency_code . '.decimal_mark'), $money_format);
            }

            $amount = money($money_format, $currency_code)->getAmount();
        } catch (InvalidArgumentException | OutOfBoundsException | UnexpectedValueException $e) {
            logger($e->getMessage());

            $amount = 0;

            if ($money_format === null) {
                $amount = $money_format;
            }
        }

        return $amount;
    }
}
