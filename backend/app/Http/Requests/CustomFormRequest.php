<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CustomFormRequest extends FormRequest
{
    private $errorCode = Response::HTTP_UNPROCESSABLE_ENTITY;

    protected function setErrorCode($errorCode)
    {
        return $this->errorCode = $errorCode;
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->ajax() || $this->wantsJson()) {
            $errors = $validator->errors();
            $messages = $errors->messages();
            $finalMessage = '';
            foreach ($messages as $key => $message) {
                foreach ($message as $item) {
                    $finalMessage = $item;
                    break;
                }
                break;
            }
            throw new HttpResponseException(response()->json(['message' => $finalMessage], $this->errorCode));
        }
        parent::failedValidation($validator);
    }
}
