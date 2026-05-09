<?php

namespace Controllers;

use Core\View;
use Models\ClientModel;

class IndexController
{
    public function index(): void
    {
        try {
            $newClient = ClientModel::create();
            View::render('index');
        } catch (\Throwable $th) {
            View::render('errors/server_error');
        }
    }
}
