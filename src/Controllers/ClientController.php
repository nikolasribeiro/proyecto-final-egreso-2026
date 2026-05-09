<?php

namespace Controllers;

use Core\View;
use Models\ClientModel;

class ClientController
{
    public function index()
    {
        $clientName = ClientModel::create();
        View::render('client/index', [
            'clientName' => $clientName,
        ]);
    }
}
