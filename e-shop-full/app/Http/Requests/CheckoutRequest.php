<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer.first_name' => ['required', 'string', 'max:100'],
            'customer.last_name' => ['required', 'string', 'max:100'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'delivery_method' => ['sometimes', Rule::enum(DeliveryMethod::class)],
            'delivery_address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:255'],
            'delivery_city' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $shop = $this->route('shop');
                $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));

                if ($shop instanceof Shop && $method && ! $shop->acceptsPaymentMethod($method)) {
                    $validator->errors()->add('payment_method', 'Ce mode de paiement n’est pas activé pour cette boutique.');
                }

                $delivery = (string) $this->input('delivery_method', DeliveryMethod::Delivery->value);

                if ($shop instanceof Shop && ! $validator->errors()->has('delivery_method') && ! in_array($delivery, $shop->availableDeliveryMethods(), true)) {
                    $validator->errors()->add('delivery_method', 'Ce mode de livraison n’est pas proposé par cette boutique.');
                }
            },
        ];
    }
}
