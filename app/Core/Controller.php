<?php

namespace App\Core;

class Controller {
    protected $request;
    protected $response;
    protected $db;

    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
        $this->db = \Config\Database::getInstance();
    }

    protected function render(string $view, array $data = []) {
        $data['app_name'] = $_ENV['APP_NAME'] ?? 'Facturación';
        $this->response->view($view, $data);
    }

    protected function json(array $data, int $status = 200) {
        $this->response->json($data, $status);
    }

    protected function redirect(string $url) {
        $this->response->redirect($url);
    }

    protected function flash(string $type, string $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}
