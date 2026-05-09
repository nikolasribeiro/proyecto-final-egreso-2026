<?php

namespace Models;

class ClientModel
{
    public static function create(): string
    {
        // Implementación para crear un nuevo cliente en la base de datos
        // Ejemplo:
        // $query = "INSERT INTO clients (name, email) VALUES (?, ?)";
        // $stmt = $db->prepare($query);
        // $stmt->execute(['John Doe', 'john.doe@example.com']);
        return "Nicolas Ribeiro.";
    }
}
