<?php



namespace App\Service;

interface AiClientInterface
{
    public function generate(string $prompt): string;
}