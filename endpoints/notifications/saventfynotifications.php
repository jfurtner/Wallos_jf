<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (
        !isset($data["topic"]) || $data["topic"] == "" ||
        !isset($data["host"]) || $data["host"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $enabled = $data["enabled"];
        $host = $data["host"];
        $topic = $data["topic"];
        $headers = $data["headers"];
        $ignore_ssl = $data["ignore_ssl"];

        $url = rtrim($host, '/') . '/' . ltrim($topic, '/');
        // Validate URL scheme
        $parsedUrl = parse_url($url);
        if (
            !isset($parsedUrl['scheme']) ||
            !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
            !filter_var($url, FILTER_VALIDATE_URL)
        ) {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }

        $query = "SELECT COUNT(*) FROM ntfy_notifications WHERE user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result === false) {
            $response = [
                "success" => false,
                "message" => translate('error_saving_notifications', $i18n)
            ];
            echo json_encode($response);
        } else {
            $row = $result->fetchArray();
            $count = $row[0];
            if ($count == 0) {
                $query = "INSERT INTO ntfy_notifications (enabled, host, topic, headers, user_id, ignore_ssl)
                              VALUES (:enabled, :host, :topic, :headers, :userId, :ignore_ssl)";
            } else {
                $query = "UPDATE ntfy_notifications
                              SET enabled = :enabled, host = :host, topic = :topic, headers = :headers, ignore_ssl = :ignore_ssl WHERE user_id = :userId";
            }

            $stmt = $db->prepare($query);
            $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
            $stmt->bindValue(':host', $host, SQLITE3_TEXT);
            $stmt->bindValue(':topic', $topic, SQLITE3_TEXT);
            $stmt->bindValue(':headers', $headers, SQLITE3_TEXT);
            $stmt->bindValue(':ignore_ssl', $ignore_ssl, SQLITE3_INTEGER);
            $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                $response = [
                    "success" => true,
                    "message" => translate('notifications_settings_saved', $i18n)
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "message" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            }
        }
    }

} else {
    $response = [
        "success" => false,
        "message" => translate('invalid_request_method', $i18n)
    ];
    echo json_encode($response);
}

?>