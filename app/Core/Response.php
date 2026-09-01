<?php

namespace App\Core;

class Response {
    public function setStatusCode(int $code) {
        http_response_code($code);
    }

    public function setHeader(string $name, string $value) {
        header("{$name}: {$value}");
    }

    public function json(array $data, int $status = 200) {
        $this->setStatusCode($status);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public function redirect(string $url) {
        $this->setHeader('Location', $url);
        exit;
    }

    public function view(string $view, array $data = []) {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new \Exception("View {$view} not found");
        }
    }
}
