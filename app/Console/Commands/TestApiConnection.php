<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class TestApiConnection extends Command
{
    protected $signature = 'app:test-api-connection {api=both : The API to test (deepseek/openai/both)}';
    protected $description = 'Test connection to DeepSeek and OpenAI APIs';

    public function handle()
    {
        $api = $this->argument('api');

        if ($api === 'deepseek' || $api === 'both') {
            $this->testDeepSeekConnection();
        }

        if ($api === 'openai' || $api === 'both') {
            $this->testOpenAIConnection();
        }

        return Command::SUCCESS;
    }

    private function testDeepSeekConnection()
    {
        try {
            $this->info('Testing DeepSeek API connection...');
            $apiKey = Config::get('services.deepseek.api_key');
            $baseUrl = Config::get('services.deepseek.base_url', 'https://api.deepseek.com');

            if (empty($apiKey)) {
                $this->error('DeepSeek API key is not set in configuration');
                return;
            }

            $this->info('DeepSeek API Key: ' . substr($apiKey, 0, 5) . '...' . substr($apiKey, -5));
            $this->info('DeepSeek Base URL: ' . $baseUrl);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/v1/chat/completions', [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => 'Say hello'],
                ],
                'model' => 'deepseek-chat',
                'max_tokens' => 10,
            ]);

            if ($response->successful()) {
                $this->info('DeepSeek API connection successful!');
                $this->info('Response: ' . json_encode($response->json()));
            } else {
                $this->error('DeepSeek API connection failed!');
                $this->error('Status code: ' . $response->status());
                $this->error('Response: ' . $response->body());
            }
        } catch (Exception $e) {
            $this->error('Exception when testing DeepSeek API: ' . $e->getMessage());
        }
    }

    private function testOpenAIConnection()
    {
        try {
            $this->info('Testing OpenAI API connection...');
            $apiKey = Config::get('services.openai.api_key');
            $baseUrl = Config::get('services.openai.base_url', 'https://api.openai.com');

            if (empty($apiKey)) {
                $this->error('OpenAI API key is not set in configuration');
                return;
            }

            $this->info('OpenAI API Key: ' . substr($apiKey, 0, 5) . '...' . substr($apiKey, -5));

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => 'Say hello'],
                ],
                'max_tokens' => 10,
            ]);

            if ($response->successful()) {
                $this->info('OpenAI API connection successful!');
                $this->info('Response: ' . json_encode($response->json()));
            } else {
                $this->error('OpenAI API connection failed!');
                $this->error('Status code: ' . $response->status());
                $this->error('Response: ' . $response->body());
            }
        } catch (Exception $e) {
            $this->error('Exception when testing OpenAI API: ' . $e->getMessage());
        }
    }
}
