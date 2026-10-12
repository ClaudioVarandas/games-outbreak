<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\PcPortCategoryEnum;
use App\Enums\PcPortStatusEnum;
use App\Enums\PcPortTechniqueEnum;
use App\Models\PcPort;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPcPortsRequest extends FormRequest
{
    /**
     * Authorization is handled by the EnsureImportToken middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ports = $this->input('ports');

        if (! is_array($ports)) {
            return;
        }

        $this->merge([
            'ports' => array_map(function (mixed $port): mixed {
                if (is_array($port) && array_key_exists('technique', $port)) {
                    $port['technique'] = PcPortTechniqueEnum::normalize(is_string($port['technique']) ? $port['technique'] : null);
                }

                return $port;
            }, $ports),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'prune' => ['nullable', 'boolean'],
            'ports' => ['required', 'array', 'min:1', 'max:500'],
            'ports.*.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'distinct'],
            'ports.*.game_title' => ['required', 'string', 'max:255'],
            'ports.*.original_platform' => ['nullable', 'string', 'max:255'],
            'ports.*.project_name' => ['nullable', 'string', 'max:255'],
            'ports.*.previous_project_name' => ['nullable', 'string', 'max:255'],
            'ports.*.framework' => ['nullable', 'string', 'max:255'],
            'ports.*.technique' => ['nullable', Rule::enum(PcPortTechniqueEnum::class)],
            'ports.*.category' => ['nullable', Rule::enum(PcPortCategoryEnum::class)],
            'ports.*.native_pc_port' => ['nullable', 'in:yes,no'],
            'ports.*.status' => ['required', Rule::enum(PcPortStatusEnum::class)],
            'ports.*.windows_support' => ['nullable', 'string', 'max:50'],
            'ports.*.linux_support' => ['nullable', 'string', 'max:50'],
            'ports.*.macos_support' => ['nullable', 'string', 'max:50'],
            'ports.*.latest_version' => ['nullable', 'string', 'max:100'],
            'ports.*.latest_public_version' => ['nullable', 'string', 'max:100'],
            'ports.*.last_activity' => ['nullable', 'date_format:Y-m-d'],
            'ports.*.last_checked_at' => ['nullable', 'date_format:Y-m-d'],
            'ports.*.added_at' => ['nullable', 'date_format:Y-m-d'],
            'ports.*.notes' => ['nullable', 'string', 'max:5000'],
            'ports.*.repo_url' => ['nullable', 'url', 'max:500'],
            'ports.*.homepage_url' => ['nullable', 'url', 'max:500'],
            'ports.*.primary_source' => ['nullable', 'url', 'max:500'],
            'ports.*.framework_repo_url' => ['nullable', 'url', 'max:500'],
            'ports.*.cover_source_url' => ['nullable', 'url', 'max:500', $this->steamGridDbHost()],
        ];

        foreach (PcPort::DETAIL_KEYS as $key) {
            $rules["ports.*.{$key}"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'ports.max' => 'Send at most 500 ports per request.',
            'ports.*.slug.regex' => 'Slugs must be lowercase and hyphen-separated.',
            'ports.*.slug.distinct' => 'Each slug may appear only once per request.',
            'ports.*.status.required' => 'Every port needs a status.',
            'ports.*.date_format' => 'Dates must use the Y-m-d format.',
        ];
    }

    /**
     * Covers on the ports page come only from SteamGridDB.
     */
    private function steamGridDbHost(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! PcPort::isSteamGridDbUrl($value)) {
                $fail('Cover images must be SteamGridDB URLs.');
            }
        };
    }
}
