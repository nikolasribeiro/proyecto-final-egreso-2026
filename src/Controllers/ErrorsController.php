<?php

namespace Controllers;

use Core\View;

class ErrorsController
{
    public function notFound(): void
    {
        View::render('errors/not_found');
    }

    public function serverError(): void
    {
        View::render('errors/server_error');
    }
}
