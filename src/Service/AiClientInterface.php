<?php



namespace App\Service;

interface AiClientInterface
{
    public function generateText(string $prompt): string;
}