<?php

return [
    'mail' => [
        'footer' => 'This is an automated message from ASELCO.',
    ],
    'ticket' => [
        'submitted_title' => 'Ticket submitted',
        'submitted_body' => 'Your ticket :ticket_no has been recorded.',
        'assigned_title' => 'Ticket in progress',
        'assigned_body' => 'Your ticket :ticket_no has been endorsed to :department and is now in progress.',
        'feedback_title' => 'Ticket needs your confirmation',
        'feedback_body' => 'Please confirm whether ticket :ticket_no has been resolved.',
        'resolved_title' => 'Ticket resolved',
        'resolved_body' => 'Ticket :ticket_no has been marked resolved by our team.',
        'reopened_title' => 'Ticket reopened',
        'reopened_body' => 'Ticket :ticket_no needs further action and has been reopened.',
        'closed_title' => 'Ticket closed',
        'closed_body' => 'Ticket :ticket_no has been closed.',
        'staff_assigned_title' => 'New ticket assigned',
        'staff_assigned_body' => 'Ticket :ticket_no was assigned to :department.',
        'staff_escalated_title' => 'SLA breach escalated',
        'staff_escalated_body' => 'Ticket :ticket_no breached SLA and requires attention.',
    ],
    'wallet' => [
        'loaded_title' => 'AST loaded',
        'loaded_body' => ':amount AST was added to your wallet. New balance: :balance AST.',
        'payment_title' => 'Bill paid with AST',
        'payment_body' => ':amount AST was applied to your bill. Receipt: :reference.',
        'payment_failed_title' => 'AST payment failed',
        'payment_failed_body' => 'Your AST bill payment could not be completed: :reason.',
        'load_failed_title' => 'Wallet load failed',
        'load_failed_body' => 'A wallet load request could not be completed: :reason.',
        'load_approval_title' => 'Wallet load approval needed',
        'load_approval_body' => 'Load request :reference_no for :amount AST is waiting for approval.',
    ],
];
