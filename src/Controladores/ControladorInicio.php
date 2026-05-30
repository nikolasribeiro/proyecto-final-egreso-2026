<?php

namespace Controladores;


class ControladorInicio
{
    public function inicio(): void
    {
        redirigir('/login');
        return;
    }
}
