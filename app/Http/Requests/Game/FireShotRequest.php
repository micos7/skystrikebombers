<?php

namespace App\Http\Requests\Game;

use App\Enums\MissileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FireShotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'missile_type' => ['required', Rule::enum(MissileType::class)],
            'target_x' => ['required', 'integer', 'min:0', 'max:20'],
            'target_y' => ['required', 'integer', 'min:0', 'max:20'],
            'orientation' => ['nullable', Rule::in(['H', 'V'])],
        ];
    }
}
