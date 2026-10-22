<?php

namespace App\Http\Controllers;

use App\Services\Seo\RobotsBuilder;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(RobotsBuilder $builder): Response
    {
        return response($builder->build(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
