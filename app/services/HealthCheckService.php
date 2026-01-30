<?php

class HealthCheckService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function check(): bool
    {
        $stmt = $this->db->query('SELECT 1');
        return $stmt !== false;
    }
}