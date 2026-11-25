<?php

namespace App\Http\Requests\Game;

use App\Enums\Orientation;
use App\Enums\PlaneType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceFleetRequest extends FormRequest
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
            'planes' => ['required', 'array', 'min:1', 'max:6'],
            'planes.*.plane_type' => ['required', Rule::enum(PlaneType::class)],
            'planes.*.origin_x' => ['required', 'integer', 'min:0', 'max:20'],
            'planes.*.origin_y' => ['required', 'integer', 'min:0', 'max:20'],
            'planes.*.orientation' => ['required', Rule::enum(Orientation::class)],
        ];
    }
}
