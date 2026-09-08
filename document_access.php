<?php
// document_access.php — Centralized na authorization helper para sa access
// sa documents (dashboard stats, listing, view modal, file download, exports,
// tracking). Iisang lugar lang ito dapat baguhin kapag nagbago man ang access
// rules — huwag nang mag-duplicate ng sariling scoping/confidentiality check
// sa bawat file, dahil dito na dapat sila lahat tumawag.
//
// PERMISSION MODEL:
//   - "Master scope" (Admin, o kahit sinong user na ang department ay
//     "Records Unit" mismo): laging pwede, kahit anong document, kahit
//     confidential man o hindi, kahit anong department pa ito.
//   - Lahat ng iba pa (SOIT, GSU, OP, at ibang department accounts):
//     "department-scoped" — pwede lang nila buksan/i-download/makita ang
//     isang document kung SILA MISMO ang nag-submit nito (created_by), O
//     kung ang department nila (mula sa session, HINDI mula sa kahit anong
//     GET/POST/browser input) ay eksaktong tugma sa department kung saan
//     naka-assign/naka-route ang document sa ngayon. Wala nang special-case
//     para sa non-confidential documents — kung hindi sakop ng scope na ito,
//     403/hindi makikita, kahit hindi pa naman confidential.

/**
 * @return bool True kung dapat makakita ang session na ito ng LAHAT ng
 *              documents sa buong ASCOT (unscoped/master view).
 */
function is_master_scope_user() {
    $role = strtolower(trim($_SESSION['user_type'] ?? ''));
    $department = trim($_SESSION['department'] ?? '');
    return $role === 'admin' || strcasecmp($department, 'Records Unit') === 0;
}

/**
 * Pangunahing tanong: pwede bang buksan/i-download/makita ng session na ito
 * ang document row na ito? Isang consistent na panuntunan ito, ginagamit sa
 * lahat ng endpoint na naglalabas ng document content (dashboard, listing,
 * view, download, export, atbp.) — walang hiwalay na mas maluwag na rule
 * para sa non-confidential documents.
 *
 * @param array $doc_row Isang row mula sa `documents` table (dapat may
 *                        department at created_by fields)
 * @return bool
 */
function can_access_document($doc_row) {
    if (is_master_scope_user()) {
        return true;
    }

    $my_department = trim($_SESSION['department'] ?? '');
    $doc_department = trim((string) ($doc_row['department'] ?? ''));
    $my_user_id = intval($_SESSION['user_id'] ?? 0);
    $created_by = intval($doc_row['created_by'] ?? 0);

    $is_my_submission = $my_user_id > 0 && $created_by === $my_user_id;
    $is_my_department_queue = $my_department !== '' && $doc_department !== '' && strcasecmp($my_department, $doc_department) === 0;

    return $is_my_submission || $is_my_department_queue;
}

/**
 * Gamitin sa mga endpoints na direktang naglalabas ng file/content (hal.
 * download_doc.php) — mag-e-exit agad na may 403 kung hindi authorized,
 * bago pa man magbasa/maglabas ng kahit anong sensitibong content.
 *
 * @param array $doc_row
 */
function enforce_document_access($doc_row) {
    if (!can_access_document($doc_row)) {
        http_response_code(403);
        echo "Access denied. This document is outside your authorized department scope.";
        exit();
    }
}

/**
 * Bumubuo ng karagdagang SQL WHERE fragment + bound params para pilitin ang
 * parehong department/ownership scoping sa itaas sa kahit anong query laban
 * sa `documents` (na naka-alias bilang $alias, hal. "d"). Blangko (walang
 * restriction) kung master-scope ang session. Hindi ito kailanman umaasa sa
 * anumang GET/POST na department value — laging mula sa session lang ang
 * basehan, kaya hindi ito magagamit para makita ang ibang department sa
 * pamamagitan lang ng pagpalit ng URL/request.
 *
 * @param string $alias Table alias ng `documents` sa query (default walang alias)
 * @return array{clause: string, params: array, types: string}
 */
function scoped_document_clause($alias = '') {
    $prefix = $alias !== '' ? $alias . '.' : '';

    if (is_master_scope_user()) {
        return ['clause' => '', 'params' => [], 'types' => ''];
    }

    $department = trim($_SESSION['department'] ?? '');
    $user_id = intval($_SESSION['user_id'] ?? 0);

    // My Submissions (na-encode/isinumite ko mismo) O Department Queue
    // (naka-assign sa sarili kong department sa ngayon)
    $clause = "({$prefix}created_by = ? OR {$prefix}department = ?)";
    return ['clause' => $clause, 'params' => [$user_id, $department], 'types' => 'is'];
}
