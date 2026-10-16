<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        return to_route($request->user()->isAdmin() ? 'atendimento.index' : 'blog.index');
    }
}
