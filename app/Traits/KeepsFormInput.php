<?php

namespace App\Traits;

trait KeepsFormInput
{
    /**
     * Redirect back with errors and keep all input fields
     *
     * @param \Illuminate\Contracts\Validation\Validator|array $errors
     * @param array|null $except Fields to exclude from input (e.g., ['password', 'password_confirmation'])
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function backWithInput($errors, ?array $except = null)
    {
        $request = request();

        // Get all input except sensitive fields
        $input = $request->all();

        // Default fields to exclude for security
        $defaultExcept = ['password', 'password_confirmation', '_token', 'g-recaptcha-response'];

        // Merge with custom exceptions
        $exceptFields = $except ? array_merge($defaultExcept, $except) : $defaultExcept;

        // Remove excluded fields
        foreach ($exceptFields as $field) {
            unset($input[$field]);
        }

        // Handle validator errors
        if ($errors instanceof \Illuminate\Contracts\Validation\Validator) {
            return redirect()->back()
                ->withErrors($errors)
                ->withInput($input);
        }

        // Handle array of errors
        if (is_array($errors)) {
            return redirect()->back()
                ->withErrors($errors)
                ->withInput($input);
        }

        // Handle string error message
        return redirect()->back()
            ->withErrors(['error' => $errors])
            ->withInput($input);
    }

    /**
     * Validate request and redirect back with input on failure
     *
     * @param \Illuminate\Http\Request $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateWithInput($request, array $rules, array $messages = [], ?array $customAttributes = [])
    {
        try {
            $request->validate($rules, $messages, $customAttributes);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Laravel's validate() automatically redirects with input, but we ensure it
            throw $e;
        }
    }
}

