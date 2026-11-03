<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is the signature, enforced by the VerifySignature middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string'],
            'command' => ['required', 'string'],
            'timestamp' => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ];
    }
}
