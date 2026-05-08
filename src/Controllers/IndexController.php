<?php

namespace Controllers;

use Core\View;

class IndexController
{
    public function index(): void
    {
        try {
            View::render('index');
        } catch (\Throwable $th) {
            View::render('errors/server_error');
        }
    }
}
