<?php
// csrf.php — Reusable CSRF protection. Isang session token lang, ginagamit sa
// lahat ng state-changing form/AJAX request sa buong system, para hindi na
// kailangang gumawa ng sarili-sariling (at posibleng mali) na check sa bawat
// handler. I-include ito PAGKATAPOS ng session_start().

/**
 * Ibinabalik ang token ng kasalukuyang session — gumagawa ng bago kung wala
 * pa (isang beses lang bawat session, hindi bawat form, para gumana ang
 * maraming bukas na tab nang sabay-sabay).
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * I-echo ang hidden input na ito sa loob ng bawat <form> na nag-POPOST ng
 * state-changing na aksyon.
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * True/false lang na check, walang exit — gamitin kung gusto mong ikaw mismo
 * ang magpasya kung ano ang gagawin kapag hindi valid (hal. AJAX JSON response).
 */
function csrf_valid($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Gamitin sa simula ng bawat POST handler (regular form submissions) —
 * mag-e-exit agad na may 403 kung walang token o mali ang token, bago pa man
 * magbago ng kahit anong data.
 */
function require_csrf() {
    if (!csrf_valid()) {
        http_response_code(403);
        echo "Security check failed (missing or invalid form token). Please go back, reload the page, and try again.";
        exit();
    }
}

/**
 * Kaparehong bagay, pero para sa mga AJAX/JSON endpoint (messages) — nagbabalik
 * ng JSON error response sa halip na plain text, dahil JSON ang inaasahan ng
 * JS caller sa mga file na ito.
 */
function require_csrf_json() {
    if (!csrf_valid()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing security token. Please reload the page.']);
        exit();
    }
}
