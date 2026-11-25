<?php

namespace App\Http\Requests\Game;

use App\Enums\GameMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGameRequest extends FormRequest
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
            'mode' => ['required', Rule::enum(GameMode::class)],
            'is_public' => ['sometimes', 'boolean'],
            'is_vs_ai' => ['sometimes', 'boolean'],
            'turn_timer_seconds' => ['nullable', 'integer', Rule::in([25, 45, 60, 90])],
            'extra_turn_on_kill' => ['sometimes', 'boolean'],
        ];
    }
}
