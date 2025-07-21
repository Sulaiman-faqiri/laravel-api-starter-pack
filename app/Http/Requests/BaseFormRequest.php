<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 422,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422));
    }

    /**
     * Modify rules dynamically for PATCH requests.
     *
     * @return array
     */
    protected function adjustRules(array $rules): array
    {
        if ($this->isMethod('patch')) {
            foreach ($rules as $field => $rule) {
                if (is_string($rule)) {
                    // Only replace 'required' when it's a standalone rule
                    $rules[$field] = collect(explode('|', $rule))
                        ->map(function ($r) {
                            return $r === 'required' ? 'sometimes' : $r;
                        })
                        ->implode('|');
                } elseif (is_array($rule)) {
                    // For array rules, filter out 'required' and prepend 'sometimes'
                    $rules[$field] = array_filter($rule, fn($r) => $r !== 'required');
                    array_unshift($rules[$field], 'sometimes');
                }
            }
        }
        return $rules;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return $this->adjustRules($this->baseRules());
    }

    /**
     * Define base validation rules. Child classes should override this method.
     *
     * @return array
     */
    protected function baseRules(): array
    {
        return [];
    }


}
