<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LoginController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('admin.auth.login');
    }
}
