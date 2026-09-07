<?php

/**
 * Extracts AI-Knowledge.md into knowledge-base/*.md using AI-Knowledge-Structure.md.
 * Run: php database/seeders/knowledge-base/_build.php
 */

$root = dirname(__DIR__, 4);
$sourcePath = $root.DIRECTORY_SEPARATOR.'AI-Knowledge.md';
$outDir = __DIR__;

if (! is_file($sourcePath)) {
    fwrite(STDERR, "Missing source: {$sourcePath}\n");
    exit(1);
}

$source = file_get_contents($sourcePath);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$sourcePath}\n");
    exit(1);
}

$source = preg_replace("/\r\n?/", "\n", $source);

preg_match_all('/^# (\d+)\. .+$/m', $source, $matches, PREG_OFFSET_CAPTURE);
$sections = [];
foreach ($matches[1] as $index => [$number]) {
    $start = $matches[0][$index][1];
    $end = $matches[0][$index + 1][1] ?? strlen($source);
    $sections[(int) $number] = trim(substr($source, $start, $end - $start), "\n-");
    $sections[(int) $number] = trim($sections[(int) $number], "\n");
}

$files = [
    'company/company-profile.md' => [1, 2, 3, 4, 52, 53, 77],
    'company/company-services.md' => [6],
    'company/service-area.md' => [5],
    'customer/consumer-account.md' => [7, 8, 9, 47],
    'customer/new-connection.md' => [29],
    'customer/reconnection.md' => [18],
    'customer/account-management.md' => [30, 31, 32],
    'billing/billing.md' => [10, 17],
    'billing/rates-and-charges.md' => [11],
    'billing/billing-disputes.md' => [12, 13],
    'payment/payment.md' => [14, 16],
    'payment/payment-channels.md' => [50],
    'payment/payment-not-reflected.md' => [15],
    'outage/outage-reporting.md' => [23, 24, 25],
    'outage/planned-outages.md' => [28, 51],
    'outage/restoration.md' => [26],
    'outage/outage-safety.md' => [27, 69, 70],
    'meter/meter-reading.md' => [19, 20],
    'meter/meter-concerns.md' => [21],
    'meter/meter-tampering.md' => [22],
    'complaints/complaint-handling.md' => [33, 34, 64, 65, 66],
    'complaints/complaint-categories.md' => [35, 36],
    'complaints/escalation.md' => [62, 63],
    'faq/frequently-asked-questions.md' => [48, 49, 54, 55, 56, 57, 58, 59],
    'ai/ai-response-rules.md' => [39, 40, 41, 42, 43, 44, 60, 61, 68, 76],
    'ai/ai-safety-rules.md' => [45, 46, 71, 75],
    'ai/ai-escalation-rules.md' => [37, 38, 62, 63, 47],
];

$subsections = [
    'customer/new-connection.md' => ['6.2'],
    'customer/reconnection.md' => ['6.3', '6.4'],
];

$sectionSix = $sections[6] ?? '';
$sixParts = [];
if ($sectionSix !== '') {
    preg_match_all('/^## (6\.\d+) .+$/m', $sectionSix, $sixMatches, PREG_OFFSET_CAPTURE);
    foreach ($sixMatches[1] as $index => [$label, $relOffset]) {
        $abs = strpos($sectionSix, $sixMatches[0][$index][0]);
        $nextAbs = isset($sixMatches[0][$index + 1])
            ? strpos($sectionSix, $sixMatches[0][$index + 1][0], $abs + 1)
            : strlen($sectionSix);
        $sixParts[$label] = trim(substr($sectionSix, $abs, $nextAbs - $abs), "\n");
    }
}

$appNotes = [
    'customer/consumer-account.md' => <<<'MD'
## ASELCO App — Membership and Privacy

ASELCO membership accounts may be linked in the mobile app after email verification.
Staff must validate account links before a member can pay bills with ASELCO Tokens.
Customer passwords, OTP codes, and full payment card numbers must never be requested in chat.
Unverified customer statements are not company policy. Only published ASELCO documents apply.
Personal membership data is used for service delivery and billing support only.
MD,
    'customer/new-connection.md' => <<<'MD'
## ASELCO App — Service Connection

New service connection and reconnection requests must be filed as a concern in the app or logged by CSR intake.
Membership setup requires linking the electric account number for validation by staff.
Only validated account links can be used for AST bill payment.
Service documentation does not authorize the AI to approve connections or change membership status.
MD,
    'payment/payment.md' => <<<'MD'
## ASELCO Tokens (AST)

ASELCO Tokens (AST) are closed-loop credits used to pay electric bills inside the cooperative app. AST is not cash and cannot be withdrawn.
Members review amount due on Home and pay from the Pay tab using a validated account link.
If a bill amount looks wrong, members must file a billing concern so Customer Service can verify against CIS. The assistant cannot change billed amounts.
Payment attempts are recorded in wallet transactions. Failed payments do not reduce AST until a successful debit completes.
MD,
    'complaints/complaint-handling.md' => <<<'MD'
## ASELCO App — Filing a Concern

To submit a complaint, open Report a Concern, choose one of the six flowchart categories, and describe the issue.
Tickets start as new until endorsed into the owning department queue. The assistant never creates ticket numbers.
Customers track progress under My Tickets. Staff notes stay internal.
Escalations follow the ticket SLA and second-tier rules. Do not bypass ticket routing.
MD,
    'outage/outage-reporting.md' => <<<'MD'
## ASELCO App — Field Service

Power interruption, brownout, blackout, sparking lines, and downed wires should be reported as a Distribution Line or Power Interruption concern.
The assistant cannot dispatch a crew. Filing a ticket routes the case through TSD/COMD assignment rules.
Members should stay clear of damaged lines and wait for official advisories in Notifications when available.
MD,
    'faq/frequently-asked-questions.md' => <<<'MD'
## ASELCO App — Customer Service Hours

Office hours for Customer Service are Monday to Friday, 8:00 AM to 5:00 PM, excluding holidays.
Members can use the mobile Support screen for contact channels and FAQs.
For amount due, open the Home screen after linking a validated account.
Push notifications cover outage advisories when enabled in notification preferences.
The AI assistant can only use approved knowledge; it cannot invent office policies.
MD,
    'ai/ai-response-rules.md' => <<<'MD'
## ASELCO App — Assistance Guidelines

Customer Service staff and the assistant must be clear, respectful, and escalate when unsure.
Never claim that AST was loaded, a bill was changed, or a ticket was closed unless the backend workflow did so.
Guide members to Report a Concern for issues needing routing, and to My Tickets for status.
Human agents remain responsible for ticket endorsement and assignment under existing business rules.
When approved knowledge does not cover a question, say so clearly and escalate to Support or Report a Concern.
MD,
];

$written = 0;
foreach ($files as $relative => $numbers) {
    $parts = [];
    foreach ($numbers as $number) {
        if (! isset($sections[$number])) {
            fwrite(STDERR, "Missing section {$number} for {$relative}\n");
            continue;
        }
        $parts[] = $sections[$number];
    }
    foreach ($subsections[$relative] ?? [] as $label) {
        if (isset($sixParts[$label])) {
            array_unshift($parts, $sixParts[$label]);
        }
    }
    if (isset($appNotes[$relative])) {
        $parts[] = $appNotes[$relative];
    }

    $path = $outDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0777, true) && ! is_dir(dirname($path))) {
        fwrite(STDERR, "Unable to create ".dirname($path)."\n");
        exit(1);
    }

    $body = trim(implode("\n\n", $parts))."\n";
    file_put_contents($path, $body);
    $written++;
    echo $relative.' ('.strlen($body)." bytes)\n";
}

echo "Wrote {$written} documents.\n";
