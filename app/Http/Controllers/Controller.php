<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Traits\KeepsFormInput;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, KeepsFormInput;

    /**
     * Redirect back with errors and keep form input
     * Excludes sensitive fields like password by default
     *
     * @param \Illuminate\Contracts\Validation\Validator|array|string $errors
     * @param array|null $except Additional fields to exclude
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function backWithErrors($errors, ?array $except = null)
    {
        return $this->backWithInput($errors, $except);
    }
}
