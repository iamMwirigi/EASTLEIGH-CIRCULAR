<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authorize($allowed_roles = []) {
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(array("message" => "Authorization header not found.", "response" => "error"));
        exit();
    }

    $auth_header = $headers['Authorization'];
    list($jwt) = sscanf($auth_header, 'Bearer %s');

    if (!$jwt) {
        http_response_code(401);
        echo json_encode(array("message" => "Access denied. No token provided.", "response" => "error"));
        exit();
    }

    try {
        $secret_key = $_ENV['JWT_SECRET'];
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));

        $user_role = $decoded->data->role;

        if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            echo json_encode(array("message" => "Forbidden. You don't have permission to access this resource.", "response" => "error"));
            exit();
        }
        
        return $decoded->data;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(array("message" => "Access denied. " . $e->getMessage(), "response" => "error"));
        exit();
    }
} 