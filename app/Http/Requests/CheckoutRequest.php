<?php

namespace App\Http\Requests;

use App\Models\Bouquet;
use App\Services\Cart;
use App\Services\DeliverySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_anonymous' => $this->boolean('is_anonymous'),
            // "0812 3456-7890" → "081234567890"
            'recipient_phone' => preg_replace('/[\s\-\.]/', '', (string) $this->input('recipient_phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_phone' => ['required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'delivery_city' => ['required', 'string', 'max:100'],
            'delivery_address' => ['required', 'string', 'min:10', 'max:500'],
            'delivery_date' => ['required', 'date_format:Y-m-d'],
            'delivery_slot' => ['required', Rule::in(array_keys(DeliverySchedule::SLOTS))],
            'occasion' => ['nullable', Rule::in(Bouquet::OCCASIONS)],
            'card_message' => ['nullable', 'string', 'max:200'],
            'sender_name' => ['nullable', 'string', 'max:100'],
            'is_anonymous' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required' => 'Nama penerima wajib diisi.',
            'recipient_phone.required' => 'Nomor telepon penerima wajib diisi.',
            'recipient_phone.regex' => 'Nomor telepon tidak valid (contoh: 081234567890).',
            'delivery_city.required' => 'Kota wajib diisi.',
            'delivery_address.required' => 'Alamat lengkap wajib diisi.',
            'delivery_address.min' => 'Alamat terlalu singkat, tulis lebih lengkap.',
            'delivery_date.required' => 'Pilih tanggal pengiriman.',
            'delivery_date.date_format' => 'Format tanggal tidak valid.',
            'delivery_slot.required' => 'Pilih slot waktu pengiriman.',
            'delivery_slot.in' => 'Slot waktu tidak valid.',
            'occasion.in' => 'Occasion tidak valid.',
            'card_message.max' => 'Pesan kartu maksimal 200 karakter.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['delivery_date', 'delivery_slot'])) {
                return;
            }

            $problem = DeliverySchedule::problem(
                $this->input('delivery_date'),
                $this->input('delivery_slot'),
                app(Cart::class)->lines()
            );

            if ($problem) {
                $validator->errors()->add($problem[0], $problem[1]);
            }
        }];
    }
}