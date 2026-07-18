<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = APP_BASE_URL;
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('La sesión venció o el formulario no es válido. Regrese e inténtelo nuevamente.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function calculate_age(string $birthDate, string $eventDate): int
{
    $birth = new DateTimeImmutable($birthDate);
    $event = new DateTimeImmutable($eventDate);
    return $birth->diff($event)->y;
}

function event_category(PDO $pdo, int $eventId, int $age): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM categorias
         WHERE evento_id = :evento_id
           AND :edad >= edad_min
           AND (edad_max IS NULL OR :edad2 <= edad_max)
         ORDER BY edad_min ASC
         LIMIT 1'
    );
    $stmt->execute(['evento_id' => $eventId, 'edad' => $age, 'edad2' => $age]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function format_money(?string $amount): string
{
    if ($amount === null || $amount === '') {
        return 'Por confirmar';
    }
    return 'B/. ' . number_format((float) $amount, 2);
}

function format_event_date(string $date): string
{
    $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $days = [0=>'domingo',1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado'];
    $d = new DateTimeImmutable($date);
    return ucfirst($days[(int)$d->format('w')]) . ' ' . $d->format('j') . ' de ' . $months[(int)$d->format('n')] . ' de ' . $d->format('Y');
}

function payment_status_label(string $status): string
{
    return match ($status) {
        'confirmado', 'pago_confirmado' => 'Confirmado',
        'rechazado', 'pago_rechazado' => 'Rechazado',
        'kit_entregado' => 'Kit entregado',
        'cancelada' => 'Cancelada',
        default => 'Pendiente de validación',
    };
}

function badge_class(string $status): string
{
    return match ($status) {
        'confirmado', 'pago_confirmado', 'kit_entregado' => 'badge-success',
        'rechazado', 'pago_rechazado', 'cancelada' => 'badge-danger',
        default => 'badge-warning',
    };
}

function audit(PDO $pdo, ?int $userId, string $action, string $entity, ?int $entityId, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle, ip) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $action,
        $entity,
        $entityId,
        json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
