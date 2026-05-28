<?php

return [
    // Page titles
    'title_list' => 'Project Commitments',
    'title_edit' => 'Edit Commitment',

    // Summary subtitle
    'subtitle_summary' => ':pendientes pending · :vencidos overdue · :cumplidos fulfilled · :rotos broken',

    // Edit subtitle
    'subtitle_edit_type' => 'Type: :tipo',
    'subtitle_edit_state' => 'Status: :estado',
    'subtitle_edit_pending' => 'Only editable while pending.',

    // Actions / buttons
    'save_changes' => 'Save Changes',

    // Filters
    'all_states' => 'All statuses',
    'state_pending' => 'Pending',
    'state_fulfilled' => 'Fulfilled',
    'state_broken' => 'Broken',
    'state_cancelled' => 'Cancelled',
    'any_expiry' => 'Any expiry',
    'filter_active' => 'Active',
    'filter_expired' => 'Overdue',
    'filter_next7d' => 'Next 7 days',
    'all_types' => 'All types',
    'type_promise' => 'Payment promise',
    'type_resolution' => 'Ticket resolution',
    'type_close' => 'Sale closing',
    'type_service' => 'Service action',
    'clear_filters' => 'Clear',
    'results' => ':count results',

    // Table columns
    'col_type' => 'Type',
    'col_state' => 'Status',
    'col_person' => 'Person',
    'col_id_doc' => 'Identification',
    'col_user' => 'User',
    'col_expiry' => 'Expiry',
    'col_resolved' => 'Resolved',

    // Empty state
    'empty_title' => 'No Commitments',
    'empty_desc' => 'No commitments match the current filters.',

    // Form fields — payment promise
    'field_expiry_date' => 'Expiry Date',
    'field_amount' => 'Amount',
    'field_currency' => 'Currency',
    'field_payment_type' => 'Payment Type',
    'no_payment_type' => '— No type —',

    // Form fields — ticket resolution
    'field_committed_action' => 'Committed Action',
    'field_sla_deadline' => 'SLA Deadline',
    'field_escalation_level' => 'Escalation Level',
    'no_escalation' => '— No escalation —',

    // Form fields — sale closing
    'field_close_amount' => 'Closing Amount',
    'field_funnel_stage' => 'Funnel Stage',
    'no_stage' => '— No stage —',

    // Form fields — service action
    'field_action_desc' => 'Action Description',
    'field_scheduled_date' => 'Scheduled Date',
    'field_action_type' => 'Action Type',
    'no_action_type' => '— No type —',
    'field_technician' => 'Assigned Technician (optional)',
];
