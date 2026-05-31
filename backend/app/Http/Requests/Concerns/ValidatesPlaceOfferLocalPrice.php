<?php

namespace App\Http\Requests\Concerns;

use App\Models\Community;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesPlaceOfferLocalPrice
{
    protected function validatePlaceOfferLocalPrice(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $localPrice = $this->input('local_price');
            if ($localPrice === null || $localPrice === '') {
                return;
            }
            if (! is_numeric($localPrice) || (float) $localPrice <= 0) {
                return;
            }
            $community = Community::current();
            $code = $community->local_currency_code;
            if (! is_string($code) || trim($code) === '') {
                $v->errors()->add(
                    'local_price',
                    __('Configure local currency for the community before setting a local price on an offer.'),
                );
            }
        });
    }
}
