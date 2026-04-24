<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Videos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVideoImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'url',
                'max:2000',
                'regex:/(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|youtube\.com\/embed\/)/i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'url.regex' => 'The URL must be a YouTube link (youtube.com/watch?v=..., youtu.be/..., shorts, or embed).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $url = $this->input('url', '');
            $host = parse_url($url, PHP_URL_HOST);

            if (! $host) {
                $v->errors()->add('url', 'Could not parse the URL host.');

                return;
            }

            $ip = gethostbyname($host);

            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $v->errors()->add('url', 'The URL resolves to a private or reserved IP address.');
            }
        });
    }
}
